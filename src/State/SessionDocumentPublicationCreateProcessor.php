<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Entity\SessionDocumentPublication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SessionDocumentPublicationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SessionDocumentPublication
    {
        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->em->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        $institute = $session->getInstitute();
        if (!$this->security->isGranted('INSTITUTE_EDIT', $institute)) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        if (!$data instanceof SessionDocumentPublication) {
            throw new UnprocessableEntityHttpException('Données invalides.');
        }

        $documentType = $data->getDocumentType();
        if (!$documentType) {
            throw new UnprocessableEntityHttpException('Le type de document est requis.');
        }

        // Check if already published
        $existing = $this->em->getRepository(SessionDocumentPublication::class)->findOneBy([
            'session' => $session,
            'documentType' => $documentType,
        ]);

        if ($existing) {
            throw new ConflictHttpException('Ce type de document est déjà publié pour cette session.');
        }

        $data->setSession($session);
        $data->setIsAutoPublished(false);
        $data->setPublishedAt(new \DateTimeImmutable());

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
