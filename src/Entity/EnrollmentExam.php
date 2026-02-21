<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Enum\EnrollmentExamStatusEnum;
use App\Repository\EnrollmentExamRepository;
use App\State\EnrollmentExamScoreProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EnrollmentExamRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('ENROLLMENT_EXAM_VIEW', object)",
            normalizationContext: ['groups' => ['enrollment_exam:read']],
        ),
        new Patch(
            uriTemplate: '/enrollment-exams/{id}/score',
            security: "is_granted('ENROLLMENT_EXAM_SCORE', object)",
            denormalizationContext: ['groups' => ['enrollment_exam:score']],
            normalizationContext: ['groups' => ['enrollment_exam:read']],
            processor: EnrollmentExamScoreProcessor::class,
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/enrollments/{enrollmentSessionId}/enrollment-exams',
    operations: [
        new GetCollection(
            security: "is_granted('ENROLLMENT_VIEW', object)",
            normalizationContext: ['groups' => ['enrollment_exam:read']],
        ),
    ],
    uriVariables: [
        'enrollmentSessionId' => new Link(
            fromProperty: 'enrollmentExams',
            fromClass: EnrollmentSession::class,
        ),
    ],
)]
class EnrollmentExam
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['enrollment_exam:read', 'enrollment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['enrollment_exam:read', 'enrollment_exam:score', 'enrollment:read'])]
    private ?int $finalScore = null;

    #[ORM\Column(enumType: EnrollmentExamStatusEnum::class)]
    #[Groups(['enrollment_exam:read', 'enrollment:read'])]
    private EnrollmentExamStatusEnum $status = EnrollmentExamStatusEnum::REGISTERED;

    #[ORM\ManyToOne(targetEntity: EnrollmentSession::class, inversedBy: 'enrollmentExams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment_exam:read'])]
    private ?EnrollmentSession $enrollmentSession = null;

    #[ORM\ManyToOne(targetEntity: ScheduledExam::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment_exam:read', 'enrollment:read'])]
    private ?ScheduledExam $scheduledExam = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFinalScore(): ?int
    {
        return $this->finalScore;
    }

    public function setFinalScore(?int $finalScore): static
    {
        $this->finalScore = $finalScore;
        return $this;
    }

    public function getStatus(): EnrollmentExamStatusEnum
    {
        return $this->status;
    }

    public function setStatus(EnrollmentExamStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getEnrollmentSession(): ?EnrollmentSession
    {
        return $this->enrollmentSession;
    }

    public function setEnrollmentSession(?EnrollmentSession $enrollmentSession): static
    {
        $this->enrollmentSession = $enrollmentSession;
        return $this;
    }

    public function getScheduledExam(): ?ScheduledExam
    {
        return $this->scheduledExam;
    }

    public function setScheduledExam(?ScheduledExam $scheduledExam): static
    {
        $this->scheduledExam = $scheduledExam;
        return $this;
    }
}
