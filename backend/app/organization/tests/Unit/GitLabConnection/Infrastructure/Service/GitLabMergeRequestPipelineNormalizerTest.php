<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Infrastructure\Service\GitLabMergeRequestPipelineNormalizer;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitLabMergeRequestPipelineNormalizer::class)]
final class GitLabMergeRequestPipelineNormalizerTest extends TestCase
{
    #[Test]
    public function reads_the_head_and_its_pipeline(): void
    {
        $normalized = new GitLabMergeRequestPipelineNormalizer()->normalize([
            'state' => 'opened',
            'sha' => 'abc',
            'head_pipeline' => ['id' => 77, 'sha' => 'abc', 'ref' => 'refleet/change-1', 'status' => 'failed'],
        ]);

        Assert::assertSame(['state' => 'opened', 'headSha' => 'abc', 'pipelineId' => '77', 'pipelineSha' => 'abc', 'pipelineRef' => 'refleet/change-1', 'pipelineStatus' => 'failed'], $normalized);
    }

    #[Test]
    public function a_merge_request_without_a_pipeline_reports_nulls(): void
    {
        $normalized = new GitLabMergeRequestPipelineNormalizer()->normalize(['state' => 'opened', 'sha' => 'abc', 'head_pipeline' => null]);

        Assert::assertSame(['state' => 'opened', 'headSha' => 'abc', 'pipelineId' => null, 'pipelineSha' => null, 'pipelineRef' => null, 'pipelineStatus' => null], $normalized);
    }

    #[Test]
    public function refuses_a_merge_request_with_no_head_to_compare(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        new GitLabMergeRequestPipelineNormalizer()->normalize(['state' => 'opened', 'head_pipeline' => null]);
    }
}
