<?php

declare(strict_types=1);

namespace App\Tests\Functional\File\Infrastructure\Http;

use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\MimeType;
use App\Fixtures\Factory\File\FileFactory;
use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ListFilesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function returns_unauthorized_when_user_is_not_authenticated(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('GET', '/api/files');

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_only_active_files_uploaded_by_current_user_when_tenant_filter_is_missing(): void
    {
        // Arrange
        $client = self::createClient();
        $accountId = AccountId::generate();
        $jwt = $this->authenticateAndGetJwt($client, 'list-files-uploader', $accountId);

        $tenantId = TenantId::fromString('11111111-1111-1111-1111-111111111111');

        $ownedActiveFile = FileFactory::createOne([
            'path' => 'uploads/source/owned-document.pdf',
            'name' => FileName::fromString('owned-document.pdf'),
            'originalName' => FileName::fromString('owned-original.pdf'),
            'mimeType' => MimeType::fromString('application/pdf'),
            'description' => 'owned file',
            'uploaderId' => UserId::fromString($accountId->asString()),
            'tenantId' => $tenantId,
        ]);

        $ownedDeletedFile = FileFactory::createOne([
            'path' => 'uploads/source/deleted-document.pdf',
            'uploaderId' => UserId::fromString($accountId->asString()),
            'tenantId' => $tenantId,
        ]);
        $ownedDeletedFile->delete();

        FileFactory::createOne([
            'path' => 'uploads/source/foreign-document.pdf',
            'uploaderId' => UserId::generate(),
            'tenantId' => $tenantId,
        ]);

        self::getContainer()->get(ManagerRegistry::class)->getManager()->flush();

        // Act
        $client->jsonRequest('GET', '/api/files', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame(1, $response['count'] ?? null);
        Assert::assertCount(1, $response['files'] ?? []);

        $item = $response['files'][0] ?? null;
        Assert::assertIsArray($item);

        Assert::assertSame($ownedActiveFile->id()->asString(), $item['id'] ?? null);
        Assert::assertSame('owned-document.pdf', $item['name'] ?? null);
        Assert::assertSame('owned-original.pdf', $item['originalName'] ?? null);
        Assert::assertSame('application/pdf', $item['mimeType'] ?? null);
        Assert::assertSame('owned file', $item['description'] ?? null);
        Assert::assertSame($accountId->asString(), $item['uploaderId'] ?? null);
        Assert::assertSame($tenantId->asString(), $item['tenantId'] ?? null);

        Assert::assertIsString($item['createdAt'] ?? null);
        Assert::assertIsString($item['updatedAt'] ?? null);
        Assert::assertIsInt($item['size'] ?? null);
        Assert::assertIsString($item['sizeFormatted'] ?? null);

        Assert::assertIsString($item['url'] ?? null);
        Assert::assertStringContainsString('/source/owned-document.pdf', (string) ($item['url'] ?? ''));
        Assert::assertNull($item['thumbnailUrl'] ?? null);
        Assert::assertFalse((bool) ($item['isImage'] ?? true));
        Assert::assertTrue((bool) ($item['isDocument'] ?? false));
        Assert::assertFalse((bool) ($item['isArchive'] ?? true));
    }

    #[Test]
    public function tenant_filter_is_ignored_and_only_own_files_are_returned(): void
    {
        // Arrange: create files belonging to a different tenant/uploader
        $client = self::createClient();
        $jwt = $this->authenticateAndGetJwt($client, 'list-files-tenant');

        $otherTenantId = TenantId::fromString('22222222-2222-2222-2222-222222222222');

        FileFactory::createOne([
            'path' => 'uploads/source/other-tenant-1.jpg',
            'tenantId' => $otherTenantId,
            'uploaderId' => UserId::generate(),
        ]);
        FileFactory::createOne([
            'path' => 'uploads/source/other-tenant-2.pdf',
            'tenantId' => $otherTenantId,
            'uploaderId' => UserId::generate(),
        ]);

        self::getContainer()->get(ManagerRegistry::class)->getManager()->flush();

        // Act: pass tenantId query param — it must be ignored
        $client->jsonRequest('GET', '/api/files?tenantId='.$otherTenantId->asString(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        // Assert: only uploader's own files (none) are returned, not the other tenant's files
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame(0, $response['count'] ?? null);
        Assert::assertCount(0, $response['files'] ?? []);
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
