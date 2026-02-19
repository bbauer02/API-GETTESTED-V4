<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class TokenService
{
    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
    ) {
    }

    public function generateVerificationToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'verify_email',
            'exp' => time() + 86400, // 24h
        ]);
    }

    public function generateResetToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'reset_password',
            'exp' => time() + 3600, // 1h
        ]);
    }

    public function validateToken(string $token): ?array
    {
        try {
            return $this->jwtEncoder->decode($token);
        } catch (\Exception) {
            return null;
        }
    }
}
