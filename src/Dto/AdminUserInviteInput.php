<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /api/admin/users/invite
 */
final class AdminUserInviteInput
{
    #[Assert\NotBlank(message: 'L\'email est requis.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Le prénom est requis.')]
    #[Assert\Length(max: 100)]
    public ?string $firstname = null;

    #[Assert\NotBlank(message: 'Le nom est requis.')]
    #[Assert\Length(max: 100)]
    public ?string $lastname = null;

    #[Assert\NotBlank(message: 'Le rôle plateforme est requis.')]
    #[Assert\Choice(choices: ['USER', 'ADMIN'], message: 'Le rôle plateforme doit être USER ou ADMIN.')]
    public ?string $platformRole = 'USER';

    /** Numéro national, chiffres uniquement (ex. 612345678). */
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Le numéro de téléphone ne doit contenir que des chiffres (sans indicatif).')]
    #[Assert\Length(max: 20)]
    public ?string $phone = null;

    /** Indicatif international (ex. +33). */
    #[Assert\Regex(pattern: '/^\+\d{1,4}$/', message: 'Indicatif téléphonique invalide (ex. +33).')]
    public ?string $phoneCountryCode = null;
}
