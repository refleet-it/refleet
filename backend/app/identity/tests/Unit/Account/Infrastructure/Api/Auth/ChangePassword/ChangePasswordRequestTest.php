<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\ChangePassword;

use App\Identity\Account\Infrastructure\Api\Auth\ChangePassword\ChangePasswordRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(ChangePasswordRequest::class)]
final class ChangePasswordRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Test]
    public function valid_payload_passes_validation(): void
    {
        // Given
        $request = new ChangePasswordRequest(
            currentPassword: 'OldPassword1!',
            newPassword: 'NewPassword1!',
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
        $request = new ChangePasswordRequest(
            currentPassword: '',
            newPassword: '',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        $byProperty = [];
        foreach ($violations as $violation) {
            $byProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('currentPassword', $byProperty);
        Assert::assertArrayHasKey('newPassword', $byProperty);
    }

    #[Test]
    public function short_new_password_fails_validation(): void
    {
        // Given
        $request = new ChangePasswordRequest(
            currentPassword: 'OldPassword1!',
            newPassword: 'short7',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        $newPasswordViolations = [];
        foreach ($violations as $violation) {
            if ('newPassword' === $violation->getPropertyPath()) {
                $newPasswordViolations[] = $violation->getMessage();
            }
        }

        Assert::assertNotEmpty($newPasswordViolations);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
