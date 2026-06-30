<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../model/services/CvssCalculatorService.php';

/**
 * Unit tests for CvssCalculatorService.
 *
 * Verifies CVSS 3.1 base score calculation, vector parsing, building, and severity derivation.
 *
 * @covers CvssCalculatorService
 */
class CvssCalculatorServiceTest extends TestCase
{
    private CvssCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new CvssCalculatorService();
    }

    // ─── parseVector Tests ─────────────────────────────────────────────

    public function testParseVectorReturnsCorrectMetrics(): void
    {
        $metrics = $this->service->parseVector('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');

        $this->assertSame('N', $metrics['AV']);
        $this->assertSame('L', $metrics['AC']);
        $this->assertSame('N', $metrics['PR']);
        $this->assertSame('N', $metrics['UI']);
        $this->assertSame('U', $metrics['S']);
        $this->assertSame('H', $metrics['C']);
        $this->assertSame('H', $metrics['I']);
        $this->assertSame('H', $metrics['A']);
    }

    public function testParseVectorTrimsWhitespace(): void
    {
        $metrics = $this->service->parseVector('  CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H  ');

        $this->assertSame('N', $metrics['AV']);
        $this->assertCount(8, $metrics);
    }

    public function testParseVectorThrowsOnInvalidPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vector string must start with "CVSS:3.1/"');

        $this->service->parseVector('CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
    }

    public function testParseVectorThrowsOnMissingMetrics(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 8 base metrics');

        $this->service->parseVector('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H');
    }

    public function testParseVectorThrowsOnUnknownMetric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown metric');

        $this->service->parseVector('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/XX:H');
    }

    public function testParseVectorThrowsOnInvalidMetricValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value 'X' for metric 'AV'");

        $this->service->parseVector('CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
    }

    public function testParseVectorThrowsOnDuplicateMetric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate metric');

        $this->service->parseVector('CVSS:3.1/AV:N/AV:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
    }

    // ─── buildVector Tests ─────────────────────────────────────────────

    public function testBuildVectorCreatesCorrectString(): void
    {
        $metrics = [
            'AV' => 'N',
            'AC' => 'L',
            'PR' => 'N',
            'UI' => 'N',
            'S' => 'U',
            'C' => 'H',
            'I' => 'H',
            'A' => 'H',
        ];

        $vector = $this->service->buildVector($metrics);

        $this->assertSame('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', $vector);
    }

    public function testBuildVectorEnforcesCanonicalOrder(): void
    {
        // Provide metrics in non-standard order
        $metrics = [
            'C' => 'H',
            'A' => 'H',
            'I' => 'H',
            'S' => 'C',
            'UI' => 'R',
            'PR' => 'L',
            'AC' => 'H',
            'AV' => 'A',
        ];

        $vector = $this->service->buildVector($metrics);

        $this->assertSame('CVSS:3.1/AV:A/AC:H/PR:L/UI:R/S:C/C:H/I:H/A:H', $vector);
    }

    public function testBuildVectorThrowsOnMissingMetric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required metric');

        $this->service->buildVector([
            'AV' => 'N',
            'AC' => 'L',
            'PR' => 'N',
            'UI' => 'N',
            'S' => 'U',
            'C' => 'H',
            'I' => 'H',
            // Missing 'A'
        ]);
    }

    public function testBuildVectorThrowsOnInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value 'Z' for metric 'AV'");

        $this->service->buildVector([
            'AV' => 'Z',
            'AC' => 'L',
            'PR' => 'N',
            'UI' => 'N',
            'S' => 'U',
            'C' => 'H',
            'I' => 'H',
            'A' => 'H',
        ]);
    }

    // ─── buildVector + parseVector roundtrip ───────────────────────────

    public function testBuildAndParseRoundtrip(): void
    {
        $original = [
            'AV' => 'A',
            'AC' => 'H',
            'PR' => 'L',
            'UI' => 'R',
            'S' => 'C',
            'C' => 'L',
            'I' => 'H',
            'A' => 'N',
        ];

        $vector = $this->service->buildVector($original);
        $parsed = $this->service->parseVector($vector);

        $this->assertSame($original, $parsed);
    }

    // ─── deriveSeverity Tests ──────────────────────────────────────────

    public function testDeriveSeverityNone(): void
    {
        $this->assertSame('none', $this->service->deriveSeverity(0.0));
    }

    public function testDeriveSeverityLow(): void
    {
        $this->assertSame('low', $this->service->deriveSeverity(0.1));
        $this->assertSame('low', $this->service->deriveSeverity(2.0));
        $this->assertSame('low', $this->service->deriveSeverity(3.9));
    }

    public function testDeriveSeverityMedium(): void
    {
        $this->assertSame('medium', $this->service->deriveSeverity(4.0));
        $this->assertSame('medium', $this->service->deriveSeverity(5.5));
        $this->assertSame('medium', $this->service->deriveSeverity(6.9));
    }

    public function testDeriveSeverityHigh(): void
    {
        $this->assertSame('high', $this->service->deriveSeverity(7.0));
        $this->assertSame('high', $this->service->deriveSeverity(8.0));
        $this->assertSame('high', $this->service->deriveSeverity(8.9));
    }

    public function testDeriveSeverityCritical(): void
    {
        $this->assertSame('critical', $this->service->deriveSeverity(9.0));
        $this->assertSame('critical', $this->service->deriveSeverity(9.8));
        $this->assertSame('critical', $this->service->deriveSeverity(10.0));
    }

    public function testDeriveSeverityThrowsOnNegativeScore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CVSS score must be between 0.0 and 10.0');

        $this->service->deriveSeverity(-0.1);
    }

    public function testDeriveSeverityThrowsOnScoreAboveTen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CVSS score must be between 0.0 and 10.0');

        $this->service->deriveSeverity(10.1);
    }

    // ─── computeScore Tests ────────────────────────────────────────────

    public function testComputeScoreMaxSeverityVector(): void
    {
        // CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H = 9.8
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');

        $this->assertSame(9.8, $score);
    }

    public function testComputeScoreScopeChangedMaxVector(): void
    {
        // CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H = 10.0
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H');

        $this->assertSame(10.0, $score);
    }

    public function testComputeScoreZeroImpactReturnsZero(): void
    {
        // All impact metrics are None -> ISS = 0 -> Impact = 0 -> Score = 0.0
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N');

        $this->assertSame(0.0, $score);
    }

    public function testComputeScoreLowSeverityVector(): void
    {
        // CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:N/I:L/A:N = 1.6
        $score = $this->service->computeScore('CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:N/I:L/A:N');

        $this->assertSame(1.6, $score);
    }

    public function testComputeScoreMediumSeverityVector(): void
    {
        // CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:U/C:L/I:L/A:N = 4.6
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:U/C:L/I:L/A:N');

        $this->assertSame(4.6, $score);
    }

    public function testComputeScoreHighSeverityVector(): void
    {
        // CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:N = 8.1
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:N');

        $this->assertSame(8.1, $score);
    }

    public function testComputeScoreScopeChangedWithLowImpact(): void
    {
        // CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N = 5.4
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N');

        $this->assertSame(5.4, $score);
    }

    public function testComputeScorePhysicalAccessVector(): void
    {
        // CVSS:3.1/AV:P/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H = 6.8
        $score = $this->service->computeScore('CVSS:3.1/AV:P/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');

        $this->assertSame(6.8, $score);
    }

    public function testComputeScorePrivilegesRequiredChangesWithScope(): void
    {
        // Scope Unchanged: PR:L uses weight 0.62
        $scoreUnchanged = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H');

        // Scope Changed: PR:L uses weight 0.68
        $scoreChanged = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:C/C:H/I:H/A:H');

        // Scope Changed should yield a higher score
        $this->assertGreaterThan($scoreUnchanged, $scoreChanged);
    }

    public function testComputeScoreResultNeverExceedsTen(): void
    {
        // The maximum possible CVSS 3.1 score should be capped at 10.0
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H');

        $this->assertLessThanOrEqual(10.0, $score);
    }

    public function testComputeScoreThrowsOnInvalidVector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->computeScore('INVALID_VECTOR');
    }

    // ─── Integration: computeScore + deriveSeverity ────────────────────

    public function testComputeScoreAndDeriveSeverityIntegration(): void
    {
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H');
        $severity = $this->service->deriveSeverity($score);

        $this->assertSame(10.0, $score);
        $this->assertSame('critical', $severity);
    }

    public function testZeroImpactDerivesNoneSeverity(): void
    {
        $score = $this->service->computeScore('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N');
        $severity = $this->service->deriveSeverity($score);

        $this->assertSame(0.0, $score);
        $this->assertSame('none', $severity);
    }
}
