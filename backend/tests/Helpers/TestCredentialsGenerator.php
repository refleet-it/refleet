<?php

declare(strict_types=1);

namespace App\Tests\Helpers;

/**
 * Generates test credentials (tokens, passwords) to avoid hardcoding
 * sensitive-looking strings in tests that trigger security scanners.
 */
final class TestCredentialsGenerator
{
    /**
     * Generates a valid invitation token string (32-64 chars).
     * Uses bin2hex(random_bytes) for cryptographically secure random tokens.
     */
    public static function invitationToken(int $length = 40): string
    {
        if ($length < 32 || $length > 64) {
            throw new \InvalidArgumentException('Token length must be between 32 and 64');
        }

        $bytes = (int) \ceil($length / 2);

        return \substr(\bin2hex(\random_bytes($bytes)), 0, $length);
    }

    /**
     * Generates a test password that meets typical requirements.
     * Uses random characters to avoid triggering security scanners.
     */
    public static function password(): string
    {
        return 'Test'.\bin2hex(\random_bytes(4)).'1A';
    }
}
