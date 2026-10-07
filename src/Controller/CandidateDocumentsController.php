<?php

namespace App\Controller;

use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\SessionDocumentPublication;
use App\Security\Voter\EnrollmentVoter;
use App\Security\Voter\SessionVoter;
use App\Service\DocumentAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Documents candidats : liste par inscription et génération par lot (le front rend/imprime le HTML).
 */
class CandidateDocumentsController extends AbstractController
{
    use HydraResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentAccessService $documentAccessService,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * GET /api/enrollment-sessions/{id}/documents
     */
    #[Route('/api/enrollment-sessions/{id}/documents', name: 'api_enrollment_documents', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function enrollmentDocuments(EnrollmentSession $enrollment): JsonResponse
    {
        if (!$this->isGranted(EnrollmentVoter::ENROLLMENT_VIEW, $enrollment)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, "Vous n'avez pas accès à cette inscription.");
        }

        $session = $enrollment->getSession();
        $publications = $this->publicationsByCode($session);
        $items = [];

        foreach ($this->documentTypes() as $documentType) {
            $code = $documentType->getCode();
            $publication = $publications[$code] ?? null;
            $template = $this->resolveTemplate($documentType, $session);

            $items[] = [
                'documentType' => $this->normalizeDocumentType($documentType),
                'publication' => $publication ? [
                    'id' => (string) $publication->getId(),
                    'publishedAt' => $publication->getPublishedAt()?->format(\DateTimeInterface::ATOM),
                    'isAutoPublished' => $publication->isAutoPublished(),
                ] : null,
                'availability' => $this->documentAccessService->availability($documentType, $session, $enrollment),
                'template' => $template ? $this->normalizer->normalize($template, 'json', ['groups' => ['document_template:read']]) : null,
            ];
        }

        return $this->hydraJson('EnrollmentDocuments', [
            'enrollmentId' => (string) $enrollment->getId(),
            'sessionId' => (string) $session->getId(),
            'sessionStatus' => $session->getStatus()->value,
            'items' => $items,
        ]);
    }

    /**
     * GET /api/sessions/{sessionId}/documents/batch?documentType=CODE&enrollmentIds=a,b,c[&force=1]
     */
    #[Route('/api/sessions/{sessionId}/documents/batch', name: 'api_session_documents_batch', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function batch(string $sessionId, Request $request): JsonResponse
    {
        $session = Uuid::isValid($sessionId) ? $this->entityManager->getRepository(Session::class)->find($sessionId) : null;
        if (!$session) {
            return $this->hydraError(Response::HTTP_NOT_FOUND, 'Session introuvable.');
        }

        if (!$this->isGranted(SessionVoter::SESSION_VIEW_ALL, $session)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, "Vous n'avez pas accès aux inscriptions de cette session.");
        }

        $code = strtoupper(trim((string) $request->query->get('documentType', '')));
        if ($code === '') {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Le paramètre "documentType" est requis.');
        }

        $documentType = $this->entityManager->getRepository(DocumentType::class)->findOneBy(['code' => $code]);
        if (!$documentType) {
            return $this->hydraError(Response::HTTP_NOT_FOUND, sprintf('Type de document "%s" introuvable.', $code));
        }

        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        $enrollments = array_values($session->getActiveEnrollments()->toArray());
        $idsParam = trim((string) $request->query->get('enrollmentIds', ''));
        if ($idsParam !== '') {
            $wanted = array_filter(array_map('trim', explode(',', $idsParam)));
            $enrollments = array_values(array_filter(
                $enrollments,
                static fn (EnrollmentSession $e) => in_array((string) $e->getId(), $wanted, true)
            ));
        }

        $template = $this->resolveTemplate($documentType, $session);
        $publication = $this->publicationsByCode($session)[$code] ?? null;

        $items = [];
        $skipped = [];
        foreach ($enrollments as $enrollment) {
            $availability = $this->documentAccessService->availability($documentType, $session, $enrollment);
            if (!$availability['available'] && !$force) {
                $skipped[] = ['enrollmentId' => (string) $enrollment->getId(), 'reason' => $availability['reason']];
                continue;
            }
            $items[] = [
                'enrollment' => $this->normalizer->normalize($enrollment, 'json', ['groups' => ['enrollment:read']]),
                'availability' => $availability,
            ];
        }

        return $this->hydraJson('SessionDocumentsBatch', [
            'sessionId' => (string) $session->getId(),
            'documentType' => $this->normalizeDocumentType($documentType),
            'publication' => $publication ? [
                'id' => (string) $publication->getId(),
                'publishedAt' => $publication->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            ] : null,
            'template' => $template ? $this->normalizer->normalize($template, 'json', ['groups' => ['document_template:read']]) : null,
            'force' => $force,
            'items' => $items,
            'skipped' => $skipped,
        ]);
    }

    /** @return DocumentType[] triés dans l'ordre métier (confirmation → paiement), puis les autres */
    private function documentTypes(): array
    {
        $all = $this->entityManager->getRepository(DocumentType::class)->findAll();
        $order = array_flip(array_keys(DocumentAccessService::TYPES));
        usort($all, static function (DocumentType $a, DocumentType $b) use ($order) {
            $ra = $order[$a->getCode()] ?? PHP_INT_MAX;
            $rb = $order[$b->getCode()] ?? PHP_INT_MAX;

            return $ra <=> $rb ?: strcmp((string) $a->getLabel(), (string) $b->getLabel());
        });

        return $all;
    }

    /** @return array<string, SessionDocumentPublication> indexées par code */
    private function publicationsByCode(Session $session): array
    {
        $byCode = [];
        foreach ($session->getDocumentPublications() as $publication) {
            $code = $publication->getDocumentType()?->getCode();
            if ($code) {
                $byCode[$code] = $publication;
            }
        }

        return $byCode;
    }

    /** Template de l'institut pour ce type (défaut en priorité), sinon template master. */
    private function resolveTemplate(DocumentType $documentType, Session $session): ?DocumentTemplate
    {
        $repo = $this->entityManager->getRepository(DocumentTemplate::class);
        $institute = $session->getInstitute();

        if ($institute) {
            $template = $repo->findOneBy(['documentType' => $documentType, 'institute' => $institute, 'isDefault' => true])
                ?? $repo->findOneBy(['documentType' => $documentType, 'institute' => $institute], ['createdAt' => 'ASC']);
            if ($template) {
                return $template;
            }
        }

        return $repo->findOneBy(['documentType' => $documentType, 'institute' => null], ['createdAt' => 'ASC']);
    }

    private function normalizeDocumentType(DocumentType $documentType): array
    {
        return [
            'id' => (string) $documentType->getId(),
            'code' => $documentType->getCode(),
            'label' => $documentType->getLabel(),
        ];
    }
}
