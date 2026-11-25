<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * POST /subjects/{subjectId}/questions/reorder — corps { "order": ["<id SubjectQuestion>", ...] }.
 * L'ordre doit contenir exactement les questions du sujet ; les positions deviennent 1..N.
 * La contrainte unique (subject_id, position) impose de passer par des positions temporaires.
 */
class SubjectQuestionReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $subjectId = $uriVariables['subjectId'] ?? null;
        if ($subjectId instanceof Uuid) {
            $subjectId = $subjectId->toRfc4122();
        }
        $subject = is_string($subjectId) && Uuid::isValid($subjectId)
            ? $this->entityManager->getRepository(Subject::class)->find($subjectId)
            : null;

        if (!$subject) {
            throw new NotFoundHttpException('Sujet introuvable.');
        }

        if (!$this->security->isGranted('SUBJECT_EDIT', $subject)) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier ce sujet (droits insuffisants ou sujet verrouillé).');
        }

        $order = $this->readOrder($context['request'] ?? null);

        $byId = [];
        foreach ($subject->getSubjectQuestions() as $sq) {
            $byId[$sq->getId()->toRfc4122()] = $sq;
        }

        $normalized = [];
        foreach ($order as $id) {
            $key = is_string($id) && Uuid::isValid($id) ? Uuid::fromString($id)->toRfc4122() : null;
            if ($key === null || !isset($byId[$key]) || isset($normalized[$key])) {
                throw new UnprocessableEntityHttpException('L\'ordre envoyé ne correspond pas aux questions du sujet.');
            }
            $normalized[$key] = true;
        }

        if (count($normalized) !== count($byId)) {
            throw new UnprocessableEntityHttpException('L\'ordre envoyé doit contenir toutes les questions du sujet.');
        }

        $this->entityManager->wrapInTransaction(function () use ($normalized, $byId): void {
            // 1. Positions temporaires négatives (aucune collision avec les positions finales)
            $i = 0;
            foreach ($byId as $sq) {
                $sq->setPosition(-1 - $i++);
            }
            $this->entityManager->flush();

            // 2. Positions finales 1..N
            $position = 1;
            foreach (array_keys($normalized) as $key) {
                $byId[$key]->setPosition($position++);
            }
            $this->entityManager->flush();
        });

        return null;
    }

    /** @return array<mixed> */
    private function readOrder(?Request $request): array
    {
        if ($request === null || $request->getContent() === '') {
            throw new UnprocessableEntityHttpException('L\'ordre des questions est requis.');
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UnprocessableEntityHttpException('Le corps de la requête est invalide.');
        }

        if (!is_array($payload) || !is_array($payload['order'] ?? null)) {
            throw new UnprocessableEntityHttpException('L\'ordre des questions est requis.');
        }

        return array_values($payload['order']);
    }
}
