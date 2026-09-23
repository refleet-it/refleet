<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Domain\Qualification\ValueObject;

use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationScoreException;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualificationScore::class)]
final class QualificationScoreTest extends TestCase
{
    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function scores(): iterable
    {
        yield '1 does not qualify' => [1, false];
        yield '2 does not qualify' => [2, false];
        yield '3 does not qualify' => [3, false];
        yield '4 qualifies' => [4, true];
        yield '5 qualifies' => [5, true];
    }

    #[Test]
    #[DataProvider('scores')]
    public function four_and_five_qualify_and_everything_below_does_not(int $value, bool $qualifies): void
    {
        $score = QualificationScore::fromInt($value);

        Assert::assertSame($value, $score->asInt());
        Assert::assertSame($qualifies, $score->qualifies());
    }

    #[Test]
    public function rejects_a_score_below_the_scale(): void
    {
        $this->expectException(InvalidQualificationScoreException::class);

        QualificationScore::fromInt(0);
    }

    #[Test]
    public function rejects_a_score_above_the_scale(): void
    {
        $this->expectException(InvalidQualificationScoreException::class);

        QualificationScore::fromInt(6);
    }
}
