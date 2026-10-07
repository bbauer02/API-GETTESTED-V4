<?php

namespace App\Controller;

use App\Entity\Institute;
use App\Entity\StripeAccount;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/institutes/{id}/stripe')]
class StripeConnectController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Create a Stripe Connect account for the institute.
     */
    #[Route('/connect', name: 'api_stripe_connect_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createConnectedAccount(Institute $institute): JsonResponse
    {
        if (!$this->canManageInstitute($institute)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, 'You are not allowed to manage this institute.');
        }

        // Check if already connected
        if ($institute->getStripeAccount() !== null) {
            return $this->hydraError(
                Response::HTTP_CONFLICT,
                'This institute already has a Stripe account.'
            );
        }

        try {
            $stripeAccount = $this->stripeService->createConnectedAccount(
                country: $institute->getAddress()->getCountryCode() ?? 'FR',
                email: $institute->getEmail(),
            );

            $account = new StripeAccount();
            $account->setStripeId($stripeAccount->id);
            $account->setIsActivated(false);
            $account->setInstitute($institute);

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            return new JsonResponse([
                '@context' => '/api/contexts/StripeAccount',
                '@type' => 'StripeAccount',
                'stripeId' => $stripeAccount->id,
                'isActivated' => false,
                'chargesEnabled' => false,
                'payoutsEnabled' => false,
                'onboardingComplete' => false,
            ], Response::HTTP_CREATED, ['Content-Type' => 'application/ld+json']);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->hydraError(Response::HTTP_BAD_GATEWAY, 'Stripe error: ' . $e->getMessage());
        }
    }

    /**
     * Create an Account Session for embedded components (onboarding hub).
     */
    #[Route('/session', name: 'api_stripe_connect_session', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createAccountSession(Institute $institute): JsonResponse
    {
        if (!$this->canManageInstitute($institute)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, 'You are not allowed to manage this institute.');
        }

        $stripeAccount = $institute->getStripeAccount();
        if ($stripeAccount === null) {
            return $this->hydraError(
                Response::HTTP_NOT_FOUND,
                'This institute has no Stripe account. Create one first.'
            );
        }

        try {
            $accountSession = $this->stripeService->createAccountSession($stripeAccount->getStripeId());

            return new JsonResponse([
                'client_secret' => $accountSession->client_secret,
            ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->hydraError(Response::HTTP_BAD_GATEWAY, 'Stripe error: ' . $e->getMessage());
        }
    }

    /**
     * Get the current Stripe account status for the institute.
     */
    #[Route('/status', name: 'api_stripe_connect_status', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAccountStatus(Institute $institute): JsonResponse
    {
        if (!$this->canManageInstitute($institute)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, 'You are not allowed to manage this institute.');
        }

        $stripeAccount = $institute->getStripeAccount();
        if ($stripeAccount === null) {
            return new JsonResponse([
                'connected' => false,
            ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        try {
            // Fetch fresh status from Stripe
            $account = $this->stripeService->retrieveAccount($stripeAccount->getStripeId());

            // Update local state
            $stripeAccount->setChargesEnabled($account->charges_enabled);
            $stripeAccount->setPayoutsEnabled($account->payouts_enabled);
            $stripeAccount->setOnboardingComplete($account->details_submitted);
            $stripeAccount->setIsActivated($account->charges_enabled && $account->payouts_enabled);

            $this->entityManager->flush();

            return new JsonResponse([
                'connected' => true,
                'stripeId' => $stripeAccount->getStripeId(),
                'isActivated' => $stripeAccount->isActivated(),
                'chargesEnabled' => $stripeAccount->isChargesEnabled(),
                'payoutsEnabled' => $stripeAccount->isPayoutsEnabled(),
                'onboardingComplete' => $stripeAccount->isOnboardingComplete(),
            ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->hydraError(Response::HTTP_BAD_GATEWAY, 'Stripe error: ' . $e->getMessage());
        }
    }

    private function canManageInstitute(Institute $institute): bool
    {
        /** @var User $user */
        $user = $this->getUser();

        // Platform admin can manage everything
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        // Check institute membership with ADMIN role
        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }

    private function hydraError(int $status, string $detail): JsonResponse
    {
        return new JsonResponse([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => $status,
        ], $status, ['Content-Type' => 'application/ld+json']);
    }
}
