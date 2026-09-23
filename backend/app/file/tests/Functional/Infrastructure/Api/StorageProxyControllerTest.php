<?php

declare(strict_types=1);

namespace App\Tests\Functional\File\Infrastructure\Api;

use App\Fixtures\Factory\File\FileFactory;
use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Shared\Domain\User\UserId;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StorageProxyControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private string $storedFilePath = '';

    #[Test]
    public function owner_can_download_their_own_file(): void
    {
        // Arrange
        $client = self::createClient();
        $accountId = AccountId::generate();
        $jwt = $this->authenticateAndGetJwt($client, 'storage-owner', $accountId);
        $path = $this->createStoredFile(UserId::fromString($accountId->asString()), 'owner-file content');

        // Act
        $client->request('GET', '/api/storage/test-bucket/'.$path, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert: the owner's file was found in storage and streamed back
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function non_owner_cannot_download_someone_elses_file(): void
    {
        // Arrange
        $client = self::createClient();
        $jwt = $this->authenticateAndGetJwt($client, 'storage-outsider');
        $path = $this->createStoredFile(UserId::generate(), 'not-yours');

        // Act
        $client->request('GET', '/api/storage/test-bucket/'.$path, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        // Arrange
        $client = self::createClient();
        $path = $this->createStoredFile(UserId::generate(), 'irrelevant');

        // Act
        $client->request('GET', '/api/storage/test-bucket/'.$path);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    protected function tearDown(): void
    {
        if ('' !== $this->storedFilePath && \file_exists($this->storedFilePath)) {
            \unlink($this->storedFilePath);
        }

        parent::tearDown();
    }

    private function createStoredFile(UserId $uploaderId, string $content): string
    {
        $relativePath = 'test/'.\bin2hex(\random_bytes(8)).'.txt';

        FileFactory::createOne([
            'path' => $relativePath,
            'uploaderId' => $uploaderId,
        ]);

        self::getContainer()->get(ManagerRegistry::class)->getManager()->flush();

        $uploadsDir = self::getContainer()->getParameter('kernel.project_dir').'/public/uploads';
        \assert(\is_string($uploadsDir));

        $this->storedFilePath = $uploadsDir.'/'.$relativePath;
        @\mkdir(\dirname($this->storedFilePath), 0o755, true);
        \file_put_contents($this->storedFilePath, $content);

        return $relativePath;
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
