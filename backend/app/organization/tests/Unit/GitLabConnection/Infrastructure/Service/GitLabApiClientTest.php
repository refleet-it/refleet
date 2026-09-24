<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InsufficientGitLabPermissionsException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabLabel;
use App\Organization\GitLabConnection\Infrastructure\Service\GitLabApiClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GitLabApiClient::class)]
final class GitLabApiClientTest extends TestCase
{
    #[Test]
    public function resolves_a_group_by_its_path(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode(['id' => 42, 'name' => 'Backend', 'full_path' => 'acme-corp/backend']), ['http_code' => 200]),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $group = $client->resolveGroup('https://gitlab.com', 'token', 'acme-corp/backend');

        // Assert
        Assert::assertSame(['id' => '42', 'name' => 'Backend', 'fullPath' => 'acme-corp/backend'], $group);
    }

    #[Test]
    public function throws_when_the_access_token_is_rejected(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 401])]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $client->resolveGroup('https://gitlab.com', 'bad-token', 'acme-corp/backend');
    }

    #[Test]
    public function throws_when_the_group_does_not_exist(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $client->resolveGroup('https://gitlab.com', 'token', 'no-such-group');
    }

    #[Test]
    public function lists_projects_across_pages_until_the_next_page_header_is_empty(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse(
                \json_encode([['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments']]),
                ['http_code' => 200, 'response_headers' => ['x-next-page' => '2']],
            ),
            new MockResponse(
                \json_encode([['id' => 2, 'name' => 'Billing', 'path_with_namespace' => 'acme/billing']]),
                ['http_code' => 200, 'response_headers' => ['x-next-page' => '']],
            ),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $projects = \iterator_to_array($client->listGroupProjects('https://gitlab.com', 'token', '99'), false);

        // Assert
        Assert::assertCount(2, $projects);
        Assert::assertSame('acme/payments', $projects[0]['path_with_namespace']);
        Assert::assertSame('acme/billing', $projects[1]['path_with_namespace']);
    }

    #[Test]
    public function throws_when_listing_projects_fails(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        \iterator_to_array($client->listGroupProjects('https://gitlab.com', 'token', '99'), false);
    }

    #[Test]
    public function reads_merge_request_states_for_the_requested_iids(): void
    {
        // Arrange
        $requestedUrl = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse(\json_encode([
                    ['iid' => 7, 'state' => 'merged'],
                    ['iid' => 9, 'state' => 'opened'],
                ]), ['http_code' => 200]);
            },
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $states = $client->listMergeRequestStates('https://gitlab.com', 'token', '42', ['7', '9']);

        // Assert
        Assert::assertSame(['7' => 'merged', '9' => 'opened'], $states);
        Assert::assertStringStartsWith('https://gitlab.com/api/v4/projects/42/merge_requests?', (string) $requestedUrl);
        Assert::assertStringContainsString('iids[]=7', \rawurldecode((string) $requestedUrl));
        Assert::assertStringContainsString('iids[]=9', \rawurldecode((string) $requestedUrl));
    }

    #[Test]
    public function asks_for_nothing_when_a_project_has_no_merge_requests_to_check(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $states = $client->listMergeRequestStates('https://gitlab.com', 'token', '42', []);

        // Assert
        Assert::assertSame([], $states);
        Assert::assertSame(0, $httpClient->getRequestsCount());
    }

    #[Test]
    public function splits_a_long_list_of_iids_across_several_calls(): void
    {
        // Arrange
        $iids = \array_map(static fn (int $n): string => (string) $n, \range(1, 120));
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('[]', ['http_code' => 200]),
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->listMergeRequestStates('https://gitlab.com', 'token', '42', $iids);

        // Assert
        Assert::assertSame(3, $httpClient->getRequestsCount());
    }

    #[Test]
    public function throws_when_reading_merge_request_states_is_rejected(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 401])]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $client->listMergeRequestStates('https://gitlab.com', 'token', '42', ['7']);
    }

    #[Test]
    public function removes_only_the_group_webhooks_pointing_at_the_url(): void
    {
        // Arrange
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requests): MockResponse {
                $requests[] = [$method, $url];

                return 'GET' === $method
                    ? new MockResponse(\json_encode([
                        ['id' => 3, 'url' => 'https://ci.example/hook'],
                        ['id' => 7, 'url' => 'https://refleet.example/webhooks/gitlab/org-1'],
                    ]), ['http_code' => 200])
                    : new MockResponse('', ['http_code' => 204]);
            },
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->removeGroupWebhook('https://gitlab.com', 'token', '99', 'https://refleet.example/webhooks/gitlab/org-1');

        // Assert
        Assert::assertSame([
            ['GET', 'https://gitlab.com/api/v4/groups/99/hooks'],
            ['DELETE', 'https://gitlab.com/api/v4/groups/99/hooks/7'],
        ], $requests);
    }

    #[Test]
    public function leaves_group_webhooks_alone_when_gitlab_hides_the_endpoint(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->removeGroupWebhook('https://gitlab.com', 'token', '99', 'https://refleet.example/webhooks/gitlab/org-1');

        // Assert
        Assert::assertSame(1, $httpClient->getRequestsCount());
    }

    #[Test]
    public function removes_only_the_project_webhooks_pointing_at_the_url(): void
    {
        // Arrange
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requests): MockResponse {
                $requests[] = [$method, $url];

                return 'GET' === $method
                    ? new MockResponse(\json_encode([
                        ['id' => 3, 'url' => 'https://ci.example/hook'],
                        ['id' => 7, 'url' => 'https://refleet.example/webhooks/gitlab/org-1'],
                        ['id' => 8, 'url' => 'https://refleet.example/webhooks/gitlab/org-1'],
                    ]), ['http_code' => 200])
                    : new MockResponse('', ['http_code' => 204]);
            },
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->removeProjectWebhook('https://gitlab.com', 'token', '42', 'https://refleet.example/webhooks/gitlab/org-1');

        // Assert
        Assert::assertSame([
            ['GET', 'https://gitlab.com/api/v4/projects/42/hooks'],
            ['DELETE', 'https://gitlab.com/api/v4/projects/42/hooks/7'],
            ['DELETE', 'https://gitlab.com/api/v4/projects/42/hooks/8'],
        ], $requests);
    }

    #[Test]
    public function tolerates_a_project_webhook_that_vanished_before_it_could_be_removed(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode([['id' => 7, 'url' => 'https://refleet.example/webhooks/gitlab/org-1']]), ['http_code' => 200]),
            new MockResponse('', ['http_code' => 404]),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->removeProjectWebhook('https://gitlab.com', 'token', '42', 'https://refleet.example/webhooks/gitlab/org-1');

        // Assert
        Assert::assertSame(2, $httpClient->getRequestsCount());
    }

    #[Test]
    public function throws_when_removing_a_project_webhook_is_rejected(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode([['id' => 7, 'url' => 'https://refleet.example/webhooks/gitlab/org-1']]), ['http_code' => 200]),
            new MockResponse('', ['http_code' => 403]),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $client->removeProjectWebhook('https://gitlab.com', 'token', '42', 'https://refleet.example/webhooks/gitlab/org-1');
    }

    #[Test]
    public function creates_the_group_label_when_the_group_has_none_of_that_name(): void
    {
        // Arrange
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = [$method, $url, $options];

                return 'GET' === $method
                    ? new MockResponse(\json_encode([['id' => 3, 'name' => 'refleet-legacy', 'color' => '#FF0000', 'description' => '']]), ['http_code' => 200])
                    : new MockResponse(\json_encode(['id' => 5, 'name' => 'refleet']), ['http_code' => 201]);
            },
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->ensureGroupLabel('https://gitlab.com', 'token', '99', GitLabLabel::refleet());

        // Assert
        Assert::assertCount(2, $requests);
        Assert::assertSame('GET', $requests[0][0]);
        Assert::assertStringStartsWith('https://gitlab.com/api/v4/groups/99/labels?', $requests[0][1]);
        Assert::assertSame('POST', $requests[1][0]);
        Assert::assertSame('https://gitlab.com/api/v4/groups/99/labels', $requests[1][1]);
        Assert::assertSame(
            ['name' => 'refleet', 'color' => '#000000', 'description' => 'Merge requests opened by Refleet'],
            \json_decode((string) $requests[1][2]['body'], true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function restores_the_colour_and_description_of_a_group_label_someone_changed(): void
    {
        // Arrange
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = [$method, $url, $options];

                return 'GET' === $method
                    ? new MockResponse(\json_encode([['id' => 7, 'name' => 'refleet', 'color' => '#AB12CD', 'description' => 'Merge requests opened by Refleet']]), ['http_code' => 200])
                    : new MockResponse(\json_encode(['id' => 7, 'name' => 'refleet']), ['http_code' => 200]);
            },
        );
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->ensureGroupLabel('https://gitlab.com', 'token', '99', GitLabLabel::refleet());

        // Assert
        Assert::assertCount(2, $requests);
        Assert::assertSame('PUT', $requests[1][0]);
        Assert::assertSame('https://gitlab.com/api/v4/groups/99/labels/7', $requests[1][1]);
        Assert::assertSame(
            ['color' => '#000000', 'description' => 'Merge requests opened by Refleet'],
            \json_decode((string) $requests[1][2]['body'], true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function leaves_a_group_label_that_already_matches_alone(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode([['id' => 7, 'name' => 'refleet', 'color' => '#000000', 'description' => 'Merge requests opened by Refleet']]), ['http_code' => 200]),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Act
        $client->ensureGroupLabel('https://gitlab.com', 'token', '99', GitLabLabel::refleet());

        // Assert
        Assert::assertSame(1, $httpClient->getRequestsCount());
    }

    #[Test]
    public function reports_missing_permissions_when_gitlab_forbids_managing_group_labels(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([
            new MockResponse('[]', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 403]),
        ]);
        $client = new GitLabApiClient($httpClient);

        // Assert
        $this->expectException(InsufficientGitLabPermissionsException::class);
        $this->expectExceptionMessage('Reporter');

        // Act
        $client->ensureGroupLabel('https://gitlab.com', 'token', '99', GitLabLabel::refleet());
    }
}
