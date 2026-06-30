<?php

/**
 * CvssCalculatorService
 *
 * Pure computation service that implements CVSS 3.1 Base Score calculation.
 * Parses vector strings, computes base scores, and derives severity ratings.
 *
 * Supports both researcher and program_owner submissions of CVSS metrics.
 *
 * @see Requirement 7.2 — Severity level via CVSS scoring
 * @see Requirement 8.4 — Associate reward policy based on severity
 * @see https://www.first.org/cvss/v3.1/specification-document
 */
class CvssCalculatorService
{
    // ─── CVSS 3.1 Metric Weight Constants ──────────────────────────────

    /** @var array Attack Vector weights */
    private const AV_WEIGHTS = [
        'N' => 0.85,  // Network
        'A' => 0.62,  // Adjacent
        'L' => 0.55,  // Local
        'P' => 0.20,  // Physical
    ];

    /** @var array Attack Complexity weights */
    private const AC_WEIGHTS = [
        'L' => 0.77,  // Low
        'H' => 0.44,  // High
    ];

    /** @var array Privileges Required weights (Scope Unchanged) */
    private const PR_WEIGHTS_UNCHANGED = [
        'N' => 0.85,  // None
        'L' => 0.62,  // Low
        'H' => 0.27,  // High
    ];

    /** @var array Privileges Required weights (Scope Changed) */
    private const PR_WEIGHTS_CHANGED = [
        'N' => 0.85,  // None
        'L' => 0.68,  // Low
        'H' => 0.50,  // High
    ];

    /** @var array User Interaction weights */
    private const UI_WEIGHTS = [
        'N' => 0.85,  // None
        'R' => 0.62,  // Required
    ];

    /** @var array Confidentiality Impact weights */
    private const C_WEIGHTS = [
        'N' => 0.00,  // None
        'L' => 0.22,  // Low
        'H' => 0.56,  // High
    ];

    /** @var array Integrity Impact weights */
    private const I_WEIGHTS = [
        'N' => 0.00,  // None
        'L' => 0.22,  // Low
        'H' => 0.56,  // High
    ];

    /** @var array Availability Impact weights */
    private const A_WEIGHTS = [
        'N' => 0.00,  // None
        'L' => 0.22,  // Low
        'H' => 0.56,  // High
    ];

    /** @var array Valid metric keys in required order */
    private const METRIC_ORDER = ['AV', 'AC', 'PR', 'UI', 'S', 'C', 'I', 'A'];

    /** @var array Valid values for each metric */
    private const VALID_VALUES = [
        'AV' => ['N', 'A', 'L', 'P'],
        'AC' => ['L', 'H'],
        'PR' => ['N', 'L', 'H'],
        'UI' => ['N', 'R'],
        'S' => ['U', 'C'],
        'C' => ['N', 'L', 'H'],
        'I' => ['N', 'L', 'H'],
        'A' => ['N', 'L', 'H'],
    ];

    /**
     * Compute the CVSS 3.1 base score from a vector string.
     *
     * @param string $vector CVSS 3.1 vector string (e.g., "CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H")
     * @return float Base score between 0.0 and 10.0
     * @throws InvalidArgumentException If the vector string is invalid.
     */
    public function computeScore(string $vector): float
    {
        $metrics = $this->parseVector($vector);

        return $this->calculateBaseScore($metrics);
    }

    /**
     * Parse a CVSS 3.1 vector string into an associative array of metric/value pairs.
     *
     * @param string $vectorString CVSS 3.1 vector string.
     * @return array Associative array with metric abbreviations as keys (e.g., ['AV' => 'N', 'AC' => 'L', ...])
     * @throws InvalidArgumentException If the vector string format is invalid.
     */
    public function parseVector(string $vectorString): array
    {
        $vectorString = trim($vectorString);

        // Validate prefix
        if (strpos($vectorString, 'CVSS:3.1/') !== 0) {
            throw new InvalidArgumentException('Vector string must start with "CVSS:3.1/"');
        }

        // Remove prefix and split into metric pairs
        $metricString = substr($vectorString, 9); // Length of "CVSS:3.1/"
        $parts = explode('/', $metricString);

        if (count($parts) !== 8) {
            throw new InvalidArgumentException('Vector string must contain exactly 8 base metrics');
        }

        $metrics = [];

        foreach ($parts as $part) {
            $pair = explode(':', $part);

            if (count($pair) !== 2) {
                throw new InvalidArgumentException("Invalid metric format: {$part}");
            }

            [$key, $value] = $pair;

            if (!isset(self::VALID_VALUES[$key])) {
                throw new InvalidArgumentException("Unknown metric: {$key}");
            }

            if (!in_array($value, self::VALID_VALUES[$key], true)) {
                throw new InvalidArgumentException("Invalid value '{$value}' for metric '{$key}'");
            }

            if (isset($metrics[$key])) {
                throw new InvalidArgumentException("Duplicate metric: {$key}");
            }

            $metrics[$key] = $value;
        }

        // Verify all required metrics are present
        foreach (self::METRIC_ORDER as $required) {
            if (!isset($metrics[$required])) {
                throw new InvalidArgumentException("Missing required metric: {$required}");
            }
        }

        return $metrics;
    }

    /**
     * Build a CVSS 3.1 vector string from an associative array of metrics.
     *
     * @param array $metrics Associative array with metric abbreviations as keys (e.g., ['AV' => 'N', 'AC' => 'L', ...])
     * @return string CVSS 3.1 vector string.
     * @throws InvalidArgumentException If required metrics are missing or values are invalid.
     */
    public function buildVector(array $metrics): string
    {
        // Validate all required metrics are present and valid
        foreach (self::METRIC_ORDER as $key) {
            if (!isset($metrics[$key])) {
                throw new InvalidArgumentException("Missing required metric: {$key}");
            }

            if (!in_array($metrics[$key], self::VALID_VALUES[$key], true)) {
                throw new InvalidArgumentException("Invalid value '{$metrics[$key]}' for metric '{$key}'");
            }
        }

        // Build vector string in canonical order
        $parts = [];
        foreach (self::METRIC_ORDER as $key) {
            $parts[] = "{$key}:{$metrics[$key]}";
        }

        return 'CVSS:3.1/' . implode('/', $parts);
    }

    /**
     * Derive the qualitative severity rating from a CVSS base score.
     *
     * @param float $score CVSS base score (0.0–10.0).
     * @return string Severity rating: 'none', 'low', 'medium', 'high', or 'critical'.
     * @throws InvalidArgumentException If the score is outside the valid range.
     */
    public function deriveSeverity(float $score): string
    {
        if ($score < 0.0 || $score > 10.0) {
            throw new InvalidArgumentException('CVSS score must be between 0.0 and 10.0');
        }

        if ($score === 0.0) {
            return 'none';
        }

        if ($score <= 3.9) {
            return 'low';
        }

        if ($score <= 6.9) {
            return 'medium';
        }

        if ($score <= 8.9) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * Calculate the CVSS 3.1 base score from parsed metrics.
     *
     * Implements the CVSS 3.1 specification formula:
     * - ISS = 1 - [(1 - C) × (1 - I) × (1 - A)]
     * - If Scope Unchanged: Impact = 6.42 × ISS
     * - If Scope Changed:   Impact = 7.52 × [ISS - 0.029] - 3.25 × [ISS - 0.02]^15
     * - Exploitability = 8.22 × AV × AC × PR × UI
     * - If Impact <= 0: BaseScore = 0.0
     * - If Scope Unchanged: BaseScore = Roundup(min(Impact + Exploitability, 10))
     * - If Scope Changed:   BaseScore = Roundup(min(1.08 × (Impact + Exploitability), 10))
     *
     * @param array $metrics Parsed metrics array.
     * @return float The calculated base score.
     */
    private function calculateBaseScore(array $metrics): float
    {
        $scopeChanged = ($metrics['S'] === 'C');

        // Get weights
        $avWeight = self::AV_WEIGHTS[$metrics['AV']];
        $acWeight = self::AC_WEIGHTS[$metrics['AC']];
        $uiWeight = self::UI_WEIGHTS[$metrics['UI']];
        $cWeight = self::C_WEIGHTS[$metrics['C']];
        $iWeight = self::I_WEIGHTS[$metrics['I']];
        $aWeight = self::A_WEIGHTS[$metrics['A']];

        // Privileges Required depends on Scope
        $prWeight = $scopeChanged
            ? self::PR_WEIGHTS_CHANGED[$metrics['PR']]
            : self::PR_WEIGHTS_UNCHANGED[$metrics['PR']];

        // Impact Sub-Score (ISS)
        $iss = 1 - ((1 - $cWeight) * (1 - $iWeight) * (1 - $aWeight));

        // Impact
        if ($scopeChanged) {
            $impact = 7.52 * ($iss - 0.029) - 3.25 * pow($iss - 0.02, 15);
        } else {
            $impact = 6.42 * $iss;
        }

        // If impact is zero or negative, base score is 0.0
        if ($impact <= 0) {
            return 0.0;
        }

        // Exploitability
        $exploitability = 8.22 * $avWeight * $acWeight * $prWeight * $uiWeight;

        // Base Score
        if ($scopeChanged) {
            $baseScore = $this->roundUp(min(1.08 * ($impact + $exploitability), 10.0));
        } else {
            $baseScore = $this->roundUp(min($impact + $exploitability, 10.0));
        }

        return $baseScore;
    }

    /**
     * CVSS 3.1 "Roundup" function.
     * Rounds up to one decimal place per the CVSS specification.
     *
     * The spec defines Roundup as:
     * If the value has more than one decimal place, round up to one decimal.
     * Specifically: roundup(x) = ceil(x * 10) / 10.0
     *
     * @param float $value Value to round.
     * @return float Rounded value.
     */
    private function roundUp(float $value): float
    {
        return ceil($value * 10) / 10.0;
    }
}
