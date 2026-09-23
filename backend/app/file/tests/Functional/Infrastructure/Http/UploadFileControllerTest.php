<?php

declare(strict_types=1);

namespace App\Tests\Functional\File\Infrastructure\Http;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UploadFileControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function returns_unauthorized_when_user_is_not_authenticated(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('POST', '/api/files/upload');

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_bad_request_when_file_is_missing(): void
    {
        // Arrange
        $client = self::createClient();
        $jwt = $this->authenticateAndGetJwt($client, 'upload-missing-file');

        // Act
        $client->request('POST', '/api/files/upload', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert
        Assert::assertSame(400, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('No file provided', $response['error'] ?? null);
    }

    #[Test]
    public function returns_bad_request_when_uploaded_file_is_invalid(): void
    {
        // Arrange
        $client = self::createClient();
        $jwt = $this->authenticateAndGetJwt($client, 'upload-invalid-file');

        $tempPath = \tempnam((string) \sys_get_temp_dir(), 'upload-invalid-');
        Assert::assertNotFalse($tempPath);
        \file_put_contents($tempPath, 'invalid');

        $uploadedFile = new UploadedFile(
            $tempPath,
            'broken.txt',
            'text/plain',
            \UPLOAD_ERR_INI_SIZE,
            true,
        );

        // Act
        $client->request('POST', '/api/files/upload', files: [
            'file' => $uploadedFile,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert
        Assert::assertSame(400, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Invalid file upload', $response['error'] ?? null);
    }

    #[Test]
    public function returns_bad_request_for_unsupported_mime_type(): void
    {
        // Arrange
        $client = self::createClient();
        $jwt = $this->authenticateAndGetJwt($client, 'upload-invalid-mime');

        $tempPath = \tempnam((string) \sys_get_temp_dir(), 'upload-invalid-mime-');
        Assert::assertNotFalse($tempPath);
        // Use deterministic binary data so mime detection is consistently application/octet-stream.
        \file_put_contents($tempPath, \str_repeat("\0", 64));

        $uploadedFile = new UploadedFile(
            $tempPath,
            'binary.bin',
            'application/octet-stream',
            null,
            true,
        );

        // Act
        $client->request('POST', '/api/files/upload', files: [
            'file' => $uploadedFile,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert
        Assert::assertSame(400, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertIsString($response['error'] ?? null);
        Assert::assertStringContainsString('is not allowed', (string) ($response['error'] ?? ''));
    }

    private function authenticateAndGetJwt(KernelBrowser $client, string $emailPrefix, ?AccountId $accountId = null): string
    {
        $email = Email::fromString($emailPrefix.'-'.\bin2hex(\random_bytes(8)).'@example.com');

        AccountFactory::createOne([
            'id' => $accountId ?? AccountId::generate(),
            'email' => $email,
            'role' => RoleEnum::USER,
        ]);

        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $email->asString(),
            'password' => 'password123',
        ]);

        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $token = $response['token']['jwtToken'] ?? '';

        Assert::assertIsString($token);
        Assert::assertNotSame('', $token);

        return $token;
    }
}
