<?php

namespace App\Service;

use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
        private readonly int $applicationFeePercent,
    ) {
        $this->stripe = new StripeClient([
            'api_key' => $this->stripeSecretKey,
            'stripe_version' => '2026-02-25.clover',
        ]);
    }

    /**
     * Create a Stripe Connect account for an institute.
     */
    public function createConnectedAccount(string $country = 'FR', ?string $email = null): \Stripe\Account
    {
        $params = [
            'country' => $country,
            'controller' => [
                'fees' => ['payer' => 'application'],
                'losses' => ['payments' => 'stripe'],
                'stripe_dashboard' => ['type' => 'none'],
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ];

        if ($email) {
            $params['email'] = $email;
        }

        return $this->stripe->accounts->create($params);
    }

    /**
     * Create an Account Session for embedded components (onboarding, payments, payouts, balances).
     */
    public function createAccountSession(string $stripeAccountId): \Stripe\AccountSession
    {
        return $this->stripe->accountSessions->create([
            'account' => $stripeAccountId,
            'components' => [
                'account_onboarding' => ['enabled' => true],
                'payments' => ['enabled' => true],
                'payouts' => ['enabled' => true],
                'balances' => ['enabled' => true],
                'notification_banner' => ['enabled' => true],
            ],
        ]);
    }

    /**
     * Retrieve the Stripe account to check its status.
     */
    public function retrieveAccount(string $stripeAccountId): \Stripe\Account
    {
        return $this->stripe->accounts->retrieve($stripeAccountId);
    }

    /**
     * Calculate the application fee amount in cents for a checkout.
     *
     * The commission is based on the HT amount (not TTC).
     * The fee sent to Stripe = commission HT + TVA 20% = commission TTC.
     *
     * @param int $totalHTInCents  The invoice total HT in cents
     * @param float $tvaRate       The TVA rate applied to the commission (default 20%)
     */
    public function calculateApplicationFee(int $totalHTInCents, ?string $instituteCommission = null, float $tvaRate = 20.0): int
    {
        $rate = $instituteCommission !== null ? (float) $instituteCommission : $this->applicationFeePercent;
        $commissionHT = $totalHTInCents * $rate / 100;
        $commissionTTC = $commissionHT * (1 + $tvaRate / 100);
        return (int) round($commissionTTC);
    }

    /**
     * Create a Checkout Session with application fee (destination charge).
     */
    public function createCheckoutSession(
        string $stripeAccountId,
        int $amountInCents,
        int $totalHTInCents,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        ?string $instituteCommission = null,
    ): \Stripe\Checkout\Session {
        return $this->stripe->checkout->sessions->create([
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => ['name' => $productName],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'payment_intent_data' => [
                'application_fee_amount' => $this->calculateApplicationFee($totalHTInCents, $instituteCommission),
                'transfer_data' => [
                    'destination' => $stripeAccountId,
                ],
                'metadata' => $metadata,
            ],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Retrieve a Checkout Session to check its payment status.
     */
    public function retrieveCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Retrieve a PaymentIntent to check its status.
     */
    public function retrievePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Refund a PaymentIntent created by createCheckoutSession().
     *
     * The charge is a destination charge created on the platform account
     * (payment_intent_data.transfer_data.destination), so the refund is issued on the
     * platform account: the transfer to the connected account is reversed and the
     * application fee is refunded.
     */
    public function refundPaymentIntent(string $paymentIntentId, string $reason = 'requested_by_customer'): \Stripe\Refund
    {
        $params = [
            'payment_intent' => $paymentIntentId,
            'refund_application_fee' => true,
            'reverse_transfer' => true,
        ];
        if (in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true)) {
            $params['reason'] = $reason;
        }

        return $this->stripe->refunds->create($params);
    }

    /**
     * Construct and verify a webhook event from Stripe.
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
    }

    public function getApplicationFeePercent(): int
    {
        return $this->applicationFeePercent;
    }
}
