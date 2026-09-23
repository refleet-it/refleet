<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Infrastructure\Api\GitLabWebhook;

use App\Shared\Domain\Service\GitLabWebhookSecretProviderInterface;
use App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus\ReportShiftMergeRequestStatusCommand;
use App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl\FindShiftTargetByMergeRequestUrlQuery;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Infrastructure\Api\GitLabWebhook\GitLabMergeRequestWebhookController;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(GitLabMergeRequestWebhookController::class)]
final class GitLabMergeRequestWebhookControllerTest extends TestCase
{
    private const string ORGANIZATION_ID = '11111111-1111-1111-1111-111111111111';

    private const string WEBHOOK_SECRET = 'correct-secret';

    private MessageBusInterface&Stub $bus;

    private GitLabWebhookSecretProviderInterface&Stub $webhookSecrets;

    private GitLabMergeRequestWebhookController $controller;

    #[Test]
    public function returns_not_found_when_the_organization_has_no_gitlab_connection(): void
    {
        // Arrange
        $this->webhookSecrets->method('forOrganization')->willReturn(null);

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, ['object_kind' => 'merge_request']));

        // Assert
        Assert::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returns_unauthorized_when_the_token_does_not_match(): void
    {
        // Arrange
        $this->stubConnection();

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken('wrong-secret', ['object_kind' => 'merge_request']));

        // Assert
        Assert::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function ignores_payloads_that_are_not_merge_request_events(): void
    {
        // Arrange
        $this->stubConnection();

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, ['object_kind' => 'push']));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(['status' => 'ignored'], $this->decode($response));
    }

    #[Test]
    public function ignores_actions_it_does_not_map_to_a_status(): void
    {
        // Arrange
        $this->stubConnection();

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, [
            'object_kind' => 'merge_request',
            'object_attributes' => ['action' => 'approved', 'url' => 'https://gitlab.example/mr/1'],
        ]));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(['status' => 'ignored'], $this->decode($response));
    }

    #[Test]
    public function reports_unknown_target_when_no_target_matches_the_merge_request_url(): void
    {
        // Arrange
        $this->stubConnection();
        $this->bus->method('dispatch')->willReturnCallback(
            static function (object $message): Envelope {
                if ($message instanceof FindShiftTargetByMergeRequestUrlQuery) {
                    return new Envelope($message, [new HandledStamp(null, 'handler')]);
                }

                return new Envelope($message, [new HandledStamp(self::connectionOverview(), 'handler')]);
            }
        );

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, [
            'object_kind' => 'merge_request',
            'object_attributes' => ['action' => 'merge', 'url' => 'https://gitlab.example/mr/1'],
        ]));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(['status' => 'unknown_target'], $this->decode($response));
    }

    #[Test]
    public function dispatches_the_report_command_for_a_matched_merge_event(): void
    {
        // Arrange
        $this->stubConnection();
        $this->bus->method('dispatch')->willReturnCallback(
            static function (object $message): Envelope {
                if ($message instanceof FindShiftTargetByMergeRequestUrlQuery) {
                    return new Envelope($message, [new HandledStamp('target-1', 'handler')]);
                }

                if ($message instanceof ReportShiftMergeRequestStatusCommand) {
                    Assert::assertSame('target-1', $message->shiftTargetId);
                    Assert::assertSame('merged', $message->status);
                    Assert::assertSame('7', $message->externalIid);

                    return new Envelope($message);
                }

                return new Envelope($message, [new HandledStamp(self::connectionOverview(), 'handler')]);
            }
        );

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, [
            'object_kind' => 'merge_request',
            'object_attributes' => ['action' => 'merge', 'iid' => 7, 'url' => 'https://gitlab.example/mr/1'],
        ]));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(['status' => 'processed'], $this->decode($response));
    }

    #[Test]
    public function treats_a_late_or_duplicate_delivery_as_a_benign_no_op(): void
    {
        // Arrange
        $staleException = new InvalidShiftTargetStateTransitionException(
            ShiftTargetStatusEnum::COMPLETED,
            'record merge request merged',
        );

        $this->stubConnection();

        $this->bus->method('dispatch')->willReturnCallback(
            static function (object $message) use ($staleException): Envelope {
                if ($message instanceof FindShiftTargetByMergeRequestUrlQuery) {
                    return new Envelope($message, [new HandledStamp('target-1', 'handler')]);
                }

                if ($message instanceof ReportShiftMergeRequestStatusCommand) {
                    throw new HandlerFailedException(new Envelope($message), [$staleException]);
                }

                return new Envelope($message);
            }
        );

        // Act
        $response = ($this->controller)(self::ORGANIZATION_ID, $this->requestWithToken(self::WEBHOOK_SECRET, [
            'object_kind' => 'merge_request',
            'object_attributes' => ['action' => 'merge', 'url' => 'https://gitlab.example/mr/1'],
        ]));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(['status' => 'stale_event'], $this->decode($response));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createStub(MessageBusInterface::class);
        $this->webhookSecrets = $this->createStub(GitLabWebhookSecretProviderInterface::class);
        $this->controller = new GitLabMergeRequestWebhookController($this->bus, $this->webhookSecrets);
    }

    private function stubConnection(): void
    {
        $this->webhookSecrets->method('forOrganization')->willReturn(self::WEBHOOK_SECRET);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestWithToken(string $token, array $payload): Request
    {
        $request = Request::create('/api/webhooks/gitlab/'.self::ORGANIZATION_ID, 'POST', content: \json_encode($payload, \JSON_THROW_ON_ERROR));
        $request->headers->set('X-Gitlab-Token', $token);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
