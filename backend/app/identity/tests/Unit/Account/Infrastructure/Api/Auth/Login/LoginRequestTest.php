<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Login;

use App\Identity\Account\Infrastructure\Api\Auth\Login\LoginRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(LoginRequest::class)]
final class LoginRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Test]
    public function valid_payload_passes_validation(): void
    {
        // Given
        $request = new LoginRequest(
            email: 'user@example.com',
            password: 'password123',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertCount(0, $violations);
        Assert::assertFalse($request->keepExistingSessions); // default value
    }

    #[Test]
    public function blank_fields_fail_validation(): void
    {
        // Given
        $request = new LoginRequest(
            email: '',
            password: '',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then: expect at least one violation for email and password
        Assert::assertGreaterThanOrEqual(2, $violations->count());

        $byProperty = [];
        foreach ($violations as $violation) {
            $byProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('email', $byProperty);
        Assert::assertArrayHasKey('password', $byProperty);
    }

    #[Test]
    public function invalid_email_format_fails_validation(): void
    {
        // Given
        $request = new LoginRequest(
            email: 'not-an-email',
            password: 'password123',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        $emailViolations = [];
        foreach ($violations as $violation) {
            if ('email' === $violation->getPropertyPath()) {
                $emailViolations[] = $violation->getMessage();
            }
        }

        Assert::assertNotEmpty($emailViolations);
    }

    #[Test]
    public function length_constraints_on_fields(): void
    {
        // Given: exceed max lengths
        $tooLongEmail = \str_repeat('a', 181).'@example.com'; // email max 180
        $tooLongPassword = \str_repeat('x', 129); // password max 128

        $request = new LoginRequest(
            email: $tooLongEmail,
            password: $tooLongPassword,
        );

        // When
        $violations = $this->validator->validate($request);

        // Then: at least one violation per field
        $byProperty = [];
        foreach ($violations as $violation) {
            $byProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('email', $byProperty);
        Assert::assertArrayHasKey('password', $byProperty);
    }

    #[Test]
    public function short_password_fails_validation(): void
    {
        // Given: password below 8 chars
        $request = new LoginRequest(
            email: 'user@example.com',
            password: 'short7',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        $passwordViolations = [];
        foreach ($violations as $violation) {
            if ('password' === $violation->getPropertyPath()) {
                $passwordViolations[] = $violation->getMessage();
            }
        }

        Assert::assertNotEmpty($passwordViolations);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
