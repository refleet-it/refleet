<?php

declare(strict_types=1);

namespace App\Tests\Functional\Playbook\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Playbooks end to end through the public HTTP API: the built-in catalog is there from the
 * start, the organization adds its own, composes a prompt from both, and what it composed
 * lands verbatim on a shift — where the full prompt preview shows exactly what the agent gets.
 */
final class PlaybookFlowTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function composes_a_shift_prompt_from_built_in_and_own_playbooks(): void
    {
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Built-ins are listed before the organization has authored anything
        $client->jsonRequest('GET', '/api/playbooks?appliesTo=change');
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $playbooks = $this->decode($client->getResponse())['playbooks'];
        $byId = \array_column($playbooks, null, 'id');
        Assert::assertArrayHasKey('builtin:git-conventions', $byId);
        Assert::assertTrue($byId['builtin:git-conventions']['builtIn']);
        Assert::assertTrue($byId['builtin:git-conventions']['default']);
        Assert::assertArrayNotHasKey('builtin:evidence-based-scoring', $byId, 'qualification-only rules are filtered out');

        // The organization adds a task with a parameter
        $client->jsonRequest('POST', '/api/playbooks', [
            'name' => 'Add a CI badge',
            'kind' => 'task',
            'appliesTo' => 'change',
            'body' => 'Add a pipeline status badge for {{branch}} to the top of README.md.',
            'parameters' => [['name' => 'branch', 'label' => 'Branch', 'default' => 'main', 'required' => true]],
            'engine' => 'claude',
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $task = $this->decode($client->getResponse());
        Assert::assertFalse($task['builtIn']);
        Assert::assertSame('main', $task['parameters'][0]['default']);

        // Built-ins are read-only
        $client->jsonRequest('DELETE', '/api/playbooks/builtin:git-conventions');
        Assert::assertSame(409, $client->getResponse()->getStatusCode());

        // Compose: default rule + own task, parameter left to its default
        $client->jsonRequest('POST', '/api/playbooks/compose', [
            'appliesTo' => 'change',
            'ruleIds' => ['builtin:git-conventions'],
            'taskId' => $task['id'],
            'parameters' => [],
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $composed = $this->decode($client->getResponse());
        Assert::assertStringStartsWith("### Git conventions\n\n", $composed['rules']);
        Assert::assertSame('Add a pipeline status badge for main to the top of README.md.', $composed['prompt']);
        Assert::assertSame('claude', $composed['engine']);
        Assert::assertSame(['builtin:git-conventions', $task['id']], \array_column($composed['sources'], 'id'));

        // A missing required parameter is refused
        $client->jsonRequest('PUT', '/api/playbooks/'.$task['id'], [
            'name' => 'Add a CI badge',
            'kind' => 'task',
            'appliesTo' => 'change',
            'body' => 'Add a pipeline status badge for {{branch}} to the top of README.md.',
            'parameters' => [['name' => 'branch', 'label' => 'Branch', 'required' => true]],
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/playbooks/compose', ['appliesTo' => 'change', 'ruleIds' => [], 'taskId' => $task['id'], 'parameters' => []]);
        Assert::assertSame(422, $client->getResponse()->getStatusCode());

        // What was composed lands on a shift verbatim, and the preview wraps it the way the job will
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Docs', 'externalId' => '1', 'path' => 'acme/docs', 'defaultBranch' => 'main']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/shifts', ['title' => 'Badges everywhere', 'projectIds' => [$this->decode($client->getResponse())['id']]]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $shiftId = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', \sprintf('/api/shifts/%s/change', $shiftId), [
            'changeMode' => 'ai',
            'changePrompt' => $composed['prompt'],
            'changeRules' => $composed['rules'],
            'changeEngine' => $composed['engine'],
            'changeSources' => $composed['sources'],
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $shift = $this->decode($client->getResponse());
        Assert::assertSame($composed['rules'], $shift['changeRules']);
        Assert::assertSame(['builtin:git-conventions', $task['id']], \array_column($shift['changeSources'], 'id'));

        $client->jsonRequest('POST', '/api/shifts/prompt-preview', ['changePrompt' => $shift['changePrompt'], 'changeRules' => $shift['changeRules']]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $preview = $this->decode($client->getResponse())['prompt'];
        Assert::assertStringContainsString("## Rules\n\n### Git conventions\n\n", (string) $preview);
        Assert::assertStringContainsString("## Change\n\nAdd a pipeline status badge for main", (string) $preview);
        Assert::assertStringContainsString('"commit": {"subject"', (string) $preview);

        // Deleting the task leaves the shift's text untouched
        $client->jsonRequest('DELETE', '/api/playbooks/'.$task['id']);
        Assert::assertSame(204, $client->getResponse()->getStatusCode());
        $client->jsonRequest('GET', '/api/shifts/'.$shiftId);
        Assert::assertSame($composed['prompt'], $this->decode($client->getResponse())['changePrompt']);
        $client->jsonRequest('GET', '/api/playbooks/'.$task['id']);
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function qualification_prompts_get_their_own_rules_and_preview(): void
    {
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Docs', 'externalId' => '1', 'path' => 'acme/docs', 'defaultBranch' => 'main']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/playbooks/compose', [
            'appliesTo' => 'qualification',
            'ruleIds' => ['builtin:evidence-based-scoring'],
            'taskId' => 'builtin:find-dependency',
            'parameters' => ['package' => 'acme/legacy-lib'],
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $composed = $this->decode($client->getResponse());
        Assert::assertStringContainsString('`acme/legacy-lib`', (string) $composed['prompt']);

        $client->jsonRequest('POST', '/api/qualifications', [
            'title' => 'Find legacy-lib',
            'qualificationMode' => 'ai',
            'qualificationPrompt' => $composed['prompt'],
            'qualificationRules' => $composed['rules'],
            'qualificationSources' => $composed['sources'],
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('GET', '/api/qualifications/'.$this->decode($client->getResponse())['id']);
        $qualification = $this->decode($client->getResponse());
        Assert::assertSame($composed['rules'], $qualification['qualificationRules']);
        Assert::assertSame(['builtin:evidence-based-scoring', 'builtin:find-dependency'], \array_column($qualification['qualificationSources'], 'id'));

        $client->jsonRequest('POST', '/api/qualifications/prompt-preview', ['qualificationPrompt' => $composed['prompt'], 'qualificationRules' => $composed['rules']]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $preview = $this->decode($client->getResponse())['prompt'];
        Assert::assertStringContainsString("## Rules\n\n### Evidence-based scoring\n\n", (string) $preview);
        Assert::assertStringContainsString("## Criteria\n\nDoes this repository depend on `acme/legacy-lib`?", (string) $preview);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function authenticateAs(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, \App\Identity\Account\Domain\Account\Model\Account $account): void
    {
        $token = self::getContainer()->get(TokenGenerator::class)->generate($account);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }
}
