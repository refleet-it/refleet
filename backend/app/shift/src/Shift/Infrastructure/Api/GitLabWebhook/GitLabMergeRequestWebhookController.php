<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\GitLabWebhook;

use App\Shared\Domain\Service\GitLabWebhookSecretProviderInterface;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus\ReportShiftMergeRequestStatusCommand;
use App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl\FindShiftTargetByMergeRequestUrlQuery;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives GitLab's "Merge request events" webhook (configured manually per-organization
 * in GitLab, using the URL/secret shown on the GitLab connection settings page — see
 * GetGitLabConnectionController) and turns "merged"/"closed" deliveries into the same
 * ReportShiftMergeRequestStatusCommand the architect-facing REST endpoint accepts, so a
 * real GitLab merge is reflected on the shift detail page without anyone having to
 * report it by hand.
 *
 * Public route (PUBLIC_ACCESS in security.yaml): GitLab cannot authenticate as one of
 * our users, so trust is established by the X-Gitlab-Token header matching the target
 * organization's GitLabConnection::webhookSecret instead.
 *
 * Always answers 200 once the token checks out, even for events it ignores (wrong
 * object_kind, unmapped action, unknown merge request, or a late/duplicate delivery for
 * a target that already left MERGE_REQUEST_OPEN) — GitLab retries and eventually disables
 * a webhook that keeps failing, and none of those cases are actionable retries would fix.
 */
#[Route('/webhooks/gitlab/{organizationId}', name: 'gitlab_merge_request_webhook', requirements: ['organizationId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Receive a GitLab "Merge request events" webhook delivery for an organization.',
    summary: 'GitLab Merge Request Webhook',
    tags: ['GitLab Connection'],
    parameters: [
        new OA\Parameter(name: 'organizationId', description: 'Organization ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Delivery accepted (processed or intentionally ignored)'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Missing or incorrect X-Gitlab-Token header'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No GitLab connection for this organization'),
    ]
)]
final readonly class GitLabMergeRequestWebhookController
{
    /**
     * Maps GitLab's object_attributes.action to the status vocabulary
     * ReportShiftMergeRequestStatusCommand expects. Actions absent from this map
     * (update, approved, unapproved, ...) are deliberately ignored.
     *
     * @var array<string, string>
     */
    private const array ACTION_TO_STATUS = [
        'open' => 'opened',
        'reopen' => 'opened',
        'merge' => 'merged',
        'close' => 'closed',
    ];

    public function __construct(
        private MessageBusInterface $bus,
        private GitLabWebhookSecretProviderInterface $webhookSecrets,
    ) {
    }

    public function __invoke(string $organizationId, Request $request): JsonResponse
    {
        $webhookSecret = $this->webhookSecrets->forOrganization($organizationId);
        if (null === $webhookSecret) {
            return new JsonResponse(['status' => 'no_connection'], Response::HTTP_NOT_FOUND);
        }

        $providedToken = $request->headers->get('X-Gitlab-Token', '');
        if (!\hash_equals($webhookSecret, $providedToken)) {
            return new JsonResponse(['status' => 'invalid_token'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $this->resolveEvent($request);
        if (null === $event) {
            return new JsonResponse(['status' => 'ignored']);
        }

        $shiftTargetId = $this->resolveShiftTargetId($organizationId, $event['url']);
        if (null === $shiftTargetId) {
            return new JsonResponse(['status' => 'unknown_target']);
        }

        return $this->reportStatus($organizationId, $shiftTargetId, $event);
    }

    /**
     * @return array{status: string, url: string, iid: ?string}|null
     */
    private function resolveEvent(Request $request): ?array
    {
        $payload = $this->decodePayload($request);
        if (null === $payload || 'merge_request' !== ($payload['object_kind'] ?? null)) {
            return null;
        }

        $attributes = $payload['object_attributes'] ?? null;

        return $this->resolveEventFromAttributes(\is_array($attributes) ? $attributes : []);
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return array{status: string, url: string, iid: ?string}|null
     */
    private function resolveEventFromAttributes(array $attributes): ?array
    {
        $action = $this->stringOrNull($attributes['action'] ?? null);
        $status = null !== $action ? (self::ACTION_TO_STATUS[$action] ?? null) : null;
        $url = $this->stringOrNull($attributes['url'] ?? null);

        if (null === $status || null === $url) {
            return null;
        }

        return [
            'status' => $status,
            'url' => $url,
            'iid' => \is_scalar($attributes['iid'] ?? null) ? (string) $attributes['iid'] : null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function resolveShiftTargetId(string $organizationId, string $mergeRequestUrl): ?string
    {
        $handledStamp = $this->bus->dispatch(new FindShiftTargetByMergeRequestUrlQuery(
            organizationId: $organizationId,
            mergeRequestUrl: $mergeRequestUrl,
        ))->last(HandledStamp::class);

        /** @var string|null $result */
        $result = $handledStamp?->getResult();

        return $result;
    }

    /**
     * @param array{status: string, url: string, iid: ?string} $event
     */
    private function reportStatus(string $organizationId, string $shiftTargetId, array $event): JsonResponse
    {
        try {
            $this->bus->dispatch(new ReportShiftMergeRequestStatusCommand(
                shiftTargetId: $shiftTargetId,
                organizationId: $organizationId,
                status: $event['status'],
                url: $event['url'],
                externalIid: $event['iid'],
            ));
        } catch (HandlerFailedException $exception) {
            // The bus wraps handler exceptions; a synchronous direct call (e.g. in tests)
            // would throw the domain exception itself, so both forms are checked.
            if ($exception->getPrevious() instanceof InvalidShiftTargetStateTransitionException) {
                return new JsonResponse(['status' => 'stale_event']);
            }

            throw $exception;
        } catch (InvalidShiftTargetStateTransitionException) {
            return new JsonResponse(['status' => 'stale_event']);
        }

        return new JsonResponse(['status' => 'processed']);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decodePayload(Request $request): ?array
    {
        try {
            $decoded = \json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }
}
