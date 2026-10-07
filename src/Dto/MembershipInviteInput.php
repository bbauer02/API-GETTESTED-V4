<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /api/institutes/{instituteId}/memberships/invite
 */
final class MembershipInviteInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $firstname = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $lastname = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ADMIN', 'TEACHER', 'STAFF'], message: 'Le rôle doit être ADMIN, TEACHER ou STAFF.')]
    public ?string $role = null;
}
