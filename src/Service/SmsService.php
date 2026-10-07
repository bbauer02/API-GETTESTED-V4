<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

/**
 * Envoi de SMS via Symfony Notifier (TEXTER_DSN : null://null en dev, twilio://... en prod).
 */
class SmsService
{
    public function __construct(
        private readonly TexterInterface $texter,
        private readonly LoggerInterface $logger,
        private readonly string $texterDsn,
    ) {
    }

    /**
     * Fournisseur configuré : "null", "twilio", ...
     */
    public function getProvider(): string
    {
        $scheme = parse_url($this->texterDsn, PHP_URL_SCHEME);

        return $scheme ? strtolower((string) $scheme) : 'null';
    }

    public function isNullTransport(): bool
    {
        return $this->getProvider() === 'null';
    }

    /**
     * @param string $phone numéro au format international (+33612345678)
     */
    public function send(string $phone, string $message): void
    {
        $this->logger->info('SMS envoyé', [
            'provider' => $this->getProvider(),
            'to' => $phone,
            'message' => $message,
        ]);

        $this->texter->send(new SmsMessage($phone, $message));
    }

    /**
     * Construit le numéro international à partir de l'indicatif et du numéro local.
     */
    public static function formatPhone(?string $countryCode, ?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '' || $phone === null) {
            return null;
        }

        $countryCode = trim((string) $countryCode);
        if ($countryCode === '') {
            return '+' . ltrim($phone, '0');
        }

        // Numéro local commençant par 0 → on retire le 0 (format FR)
        if (str_starts_with($phone, '0') && strlen($phone) >= 9) {
            $phone = substr($phone, 1);
        }

        return $countryCode . $phone;
    }
}
