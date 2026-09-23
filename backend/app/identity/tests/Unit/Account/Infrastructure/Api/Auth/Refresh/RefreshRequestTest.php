<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Refresh;

use App\Identity\Account\Infrastructure\Api\Auth\Refresh\RefreshRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(RefreshRequest::class)]
final class RefreshRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Test]
    public function valid_payload_passes_validation(): void
    {
        // Given
        $request = new RefreshRequest(
            refreshToken: 'valid-refresh-token-123',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then
        Assert::assertCount(0, $violations);
    }

    #[Test]
    public function blank_token_fails_validation(): void
    {
        // Given
        $request = new RefreshRequest(
            refreshToken: '',
        );

        // When
        $violations = $this->validator->validate($request);

        // Then: expect a violation on refreshToken
        Assert::assertGreaterThanOrEqual(1, $violations->count());

        $byProperty = [];
        foreach ($violations as $violation) {
            $byProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('refreshToken', $byProperty);
    }

    #[Test]
    public function token_length_constraints(): void
    {
        // Given: exceed max length (max 180)
        $tooLongToken = \str_repeat('x', 181);

        $request = new RefreshRequest(
            refreshToken: $tooLongToken,
        );

        // When
        $violations = $this->validator->validate($request);

        // Then: expect a violation on refreshToken
        $byProperty = [];
        foreach ($violations as $violation) {
            $byProperty[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        Assert::assertArrayHasKey('refreshToken', $byProperty);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
