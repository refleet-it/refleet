<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

/**
 * The user code travels in a URL the person reads and clicks, the device secret only
 * ever leaves the CLI in a request body — so the former is short and URL-safe, the
 * latter as long as an API key and stored hashed, like one.
 */
final readonly class CliAuthorizationCodeGenerator
{
    private const int USER_CODE_BYTES = 12;

    private const int DEVICE_SECRET_BYTES = 32;

    public function generate(): GeneratedCliAuthorizationCodes
    {
        $deviceSecret = \bin2hex(\random_bytes(self::DEVICE_SECRET_BYTES));

        return new GeneratedCliAuthorizationCodes(
            userCode: \bin2hex(\random_bytes(self::USER_CODE_BYTES)),
            deviceSecret: $deviceSecret,
            deviceSecretHash: self::hash($deviceSecret),
        );
    }

    public static function hash(string $deviceSecret): string
    {
        return \hash('sha256', $deviceSecret);
    }
}
