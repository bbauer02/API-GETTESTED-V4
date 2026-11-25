<?php

namespace App\Serializer;

use App\Entity\Institute;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Fiche institut (GET /api/institutes et /api/institutes/{id}) : le détail complet (`institute:read` :
 * email, SIREN/SIRET, TVA, forme juridique, compte Stripe et commission…) n'est servi qu'à
 * l'administrateur plateforme et aux membres actifs de l'institut (administrateur, enseignant, personnel).
 * Les autres lecteurs (anonymes, candidats, autres instituts) reçoivent le groupe public réduit
 * `institute:read:public` (+ `address:read` pour l'adresse).
 */
class InstituteNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const PUBLIC_GROUPS = ['institute:read:public', 'address:read'];

    private const ALREADY_CALLED = 'INSTITUTE_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly Security $security)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        /** @var Institute $data */
        $context[self::ALREADY_CALLED][spl_object_id($data)] = true;

        $groups = (array) ($context['groups'] ?? []);
        if (in_array('institute:read', $groups, true) && !self::canReadPrivate($this->security->getUser(), $data)) {
            $context['groups'] = self::PUBLIC_GROUPS;
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Institute
            && !isset($context[self::ALREADY_CALLED][spl_object_id($data)]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Institute::class => false];
    }

    /** Administrateur plateforme ou membre actif (hors candidat) de l'institut. */
    public static function canReadPrivate(mixed $user, Institute $institute): bool
    {
        if (!$user instanceof User) {
            return false;
        }
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($user->getMemberships() as $membership) {
            if ($membership->isActive()
                && $membership->getRole() !== InstituteRoleEnum::CUSTOMER
                && $membership->getInstitute()?->getId()?->equals($institute->getId())
            ) {
                return true;
            }
        }

        return false;
    }
}
