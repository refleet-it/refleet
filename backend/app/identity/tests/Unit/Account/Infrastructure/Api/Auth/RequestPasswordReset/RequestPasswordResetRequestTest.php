<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset;

use App\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset\RequestPasswordResetRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(RequestPasswordResetRequest::class)]
final class RequestPasswordResetRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Test]
    public function valid_payload_passes_validation(): void
    {
        // Given
        $request = new RequestPasswordResetRequest(
            email: 'user@example.com',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertCount(0, $violations);
    }

    #[Test]
    public function blank_email_fails_validation(): void
    {
        // Given
        $request = new RequestPasswordResetRequest(
            email: '',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertGreaterThanOrEqual(1, $violations->count());

        $messagesByProperty = [];
        foreach ($violations as $violation) {
            $messagesByProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('email', $messagesByProperty);
        Assert::assertContains('Email is required', $messagesByProperty['email']);
    }

    #[Test]
    public function invalid_email_format_fails_validation(): void
    {
        // Given
        $request = new RequestPasswordResetRequest(
            email: 'not-an-email',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertGreaterThanOrEqual(1, $violations->count());

        $messagesByProperty = [];
        foreach ($violations as $violation) {
            $messagesByProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('email', $messagesByProperty);
        Assert::assertContains('Invalid email format', $messagesByProperty['email']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
