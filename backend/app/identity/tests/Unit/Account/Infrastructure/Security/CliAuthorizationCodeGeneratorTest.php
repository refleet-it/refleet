<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Infrastructure\Security\CliAuthorizationCodeGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CliAuthorizationCodeGenerator::class)]
final class CliAuthorizationCodeGeneratorTest extends TestCase
{
    #[Test]
    public function it_produces_a_url_safe_user_code_and_a_hashed_device_secret(): void
    {
        $codes = (new CliAuthorizationCodeGenerator())->generate();

        Assert::assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $codes->userCode);
        Assert::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $codes->deviceSecret);
        Assert::assertSame(\hash('sha256', $codes->deviceSecret), $codes->deviceSecretHash);
        Assert::assertSame($codes->deviceSecretHash, CliAuthorizationCodeGenerator::hash($codes->deviceSecret));
    }

    #[Test]
    public function two_generations_never_collide(): void
    {
        $generator = new CliAuthorizationCodeGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        Assert::assertNotSame($first->userCode, $second->userCode);
        Assert::assertNotSame($first->deviceSecret, $second->deviceSecret);
    }
}
