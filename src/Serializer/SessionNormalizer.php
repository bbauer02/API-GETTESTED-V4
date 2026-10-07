<?php

namespace App\Serializer;

use App\Entity\Session;
use App\Entity\User;
use App\Security\Voter\SessionVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Protège les données personnelles des inscrits : la liste `enrollments` (utilisateur, factures…)
 * et `refundErrors` ne sont exposées qu'aux gestionnaires de l'institut (SESSION_VIEW_ALL).
 * Tout le monde reçoit en revanche des compteurs et l'indicateur `isEnrolledByMe`.
 */
class SessionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'SESSION_NORMALIZER_ALREADY_CALLED';
    private const PRIVATE_FIELDS = ['enrollments', 'refundErrors'];

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        /** @var Session $data */
        $context[self::ALREADY_CALLED][spl_object_id($data)] = true;

        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (!is_array($normalized)) {
            return $normalized;
        }

        $enrollments = $data->getEnrollments();
        $enrolledCount = $enrollments->count();

        $normalized['enrollmentsCount'] = $enrolledCount;
        $normalized['placesRemaining'] = $data->getPlacesAvailable() !== null
            ? max(0, $data->getPlacesAvailable() - $enrolledCount)
            : null;

        $user = $this->security->getUser();
        $normalized['isEnrolledByMe'] = $user instanceof User && $enrollments->exists(
            fn ($key, $enrollment) => $enrollment->getUser()?->getId()?->equals($user->getId())
        );

        if (!$this->security->isGranted(SessionVoter::SESSION_VIEW_ALL, $data)) {
            foreach (self::PRIVATE_FIELDS as $field) {
                unset($normalized[$field]);
            }
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Session
            && !isset($context[self::ALREADY_CALLED][spl_object_id($data)]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Session::class => false];
    }
}
