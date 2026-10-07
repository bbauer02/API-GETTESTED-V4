<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /api/enrollment-sessions/{id}/transfer
 */
final class EnrollmentTransferInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $targetSessionId = null;
}
