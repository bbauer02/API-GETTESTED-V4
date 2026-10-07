<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Subject;
use App\Enum\SubjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubjectArchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subject
    {
        /** @var Subject $subject */
        $subject = $data;

        if ($subject->getStatus() !== SubjectStatusEnum::LOCKED) {
            throw new UnprocessableEntityHttpException('Seul un sujet en statut LOCKED peut être archivé.');
        }

        $subject->setStatus(SubjectStatusEnum::ARCHIVED);

        $this->entityManager->flush();

        return $subject;
    }
}
