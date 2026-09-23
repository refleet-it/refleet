<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\ResetPassword;

use App\Identity\Account\Infrastructure\Api\Auth\ResetPassword\ResetPasswordRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(ResetPasswordRequest::class)]
final class ResetPasswordRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Test]
    public function valid_payload_passes_validation(): void
    {
        // Given
        $request = new ResetPasswordRequest(
            token: 'valid-reset-token-123',
            newPassword: 'strongPass123',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertCount(0, $violations);
    }

    #[Test]
    public function blank_fields_fail_validation(): void
    {
        // Given
        $request = new ResetPasswordRequest(
            token: '',
            newPassword: '',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        // Expect two violations: token and newPassword NotBlank
        Assert::assertGreaterThanOrEqual(2, $violations->count());

        $messagesByProperty = [];
        foreach ($violations as $violation) {
            $messagesByProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('token', $messagesByProperty);
        Assert::assertContains('Token is required', $messagesByProperty['token']);

        Assert::assertArrayHasKey('newPassword', $messagesByProperty);
        Assert::assertContains('New password is required', $messagesByProperty['newPassword']);
    }

    #[Test]
    public function short_password_fails_validation(): void
    {
        // Given
        $request = new ResetPasswordRequest(
            token: 'some-token',
            newPassword: 'short7', // 6 chars
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertGreaterThanOrEqual(1, $violations->count());

        $passwordViolations = [];
        foreach ($violations as $violation) {
            if ('newPassword' === $violation->getPropertyPath()) {
                $passwordViolations[] = $violation->getMessage();
            }
        }

        Assert::assertNotEmpty($passwordViolations);
        Assert::assertContains('Password must be at least 8 characters long', $passwordViolations);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
