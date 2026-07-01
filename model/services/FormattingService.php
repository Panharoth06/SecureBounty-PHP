<?php

/**
 * FormattingService
 *
 * Pure static helper functions for formatting display values
 * in the program card listing and profile views.
 *
 * @see Requirement 4.2 — Description truncated to 120 characters with ellipsis
 * @see Requirement 4.4 — Bounty range formatted as "$min – $max" with comma separators
 * @see Requirement 4.7 — Technology tags truncated to max 5 visible with "+N more"
 * @see Requirement 7.9 — Logo placeholder uses first letter of program title
 * @see Requirement 8.11 — Avatar placeholder uses first letters of first and last name
 */
class FormattingService
{
    /** @var int Maximum description length before truncation */
    private const DESCRIPTION_MAX_LENGTH = 120;

    /** @var int Default maximum visible tags */
    private const DEFAULT_MAX_VISIBLE_TAGS = 5;

    /**
     * Truncate a description to 120 characters with a unicode ellipsis appended.
     *
     * If the description length is 120 characters or fewer, it is returned unchanged.
     * If it exceeds 120 characters, the first 120 characters are returned with "…" appended.
     *
     * @param string $description The full description text.
     * @return string The truncated (or unchanged) description.
     *
     * @see Requirement 4.2
     */
    public static function truncateDescription(string $description): string
    {
        if (mb_strlen($description, 'UTF-8') > self::DESCRIPTION_MAX_LENGTH) {
            return mb_substr($description, 0, self::DESCRIPTION_MAX_LENGTH, 'UTF-8') . '…';
        }

        return $description;
    }

    /**
     * Format a bounty range as "$min – $max" with comma thousands separators.
     *
     * If both min and max are null, returns "No bounty defined".
     * Whole-number amounts omit decimal places; fractional amounts show 2 decimals.
     *
     * @param float|null $min The minimum bounty amount.
     * @param float|null $max The maximum bounty amount.
     * @return string The formatted bounty range string.
     *
     * @see Requirement 4.4
     * @see Requirement 4.5
     */
    public static function formatBountyRange(?float $min, ?float $max): string
    {
        if ($min === null && $max === null) {
            return 'No bounty defined';
        }

        $formattedMin = self::formatAmount($min ?? 0.0);
        $formattedMax = self::formatAmount($max ?? 0.0);

        return "\${$formattedMin} – \${$formattedMax}";
    }

    /**
     * Truncate a tag list to show a maximum number of visible tags.
     *
     * Returns an associative array with:
     * - 'tags': the visible tags (first $maxVisible items)
     * - 'overflow': the count of remaining tags (0 if no overflow)
     *
     * @param array $tags       Array of tag values.
     * @param int   $maxVisible Maximum number of tags to show (default 5).
     * @return array{tags: array, overflow: int}
     *
     * @see Requirement 4.7
     */
    public static function truncateTags(array $tags, int $maxVisible = self::DEFAULT_MAX_VISIBLE_TAGS): array
    {
        $totalCount = count($tags);

        if ($totalCount <= $maxVisible) {
            return [
                'tags' => $tags,
                'overflow' => 0,
            ];
        }

        return [
            'tags' => array_slice($tags, 0, $maxVisible),
            'overflow' => $totalCount - $maxVisible,
        ];
    }

    /**
     * Generate a logo placeholder character from a program title.
     *
     * Returns the first character of the title in uppercase.
     *
     * @param string $title The program title.
     * @return string The uppercase first character.
     *
     * @see Requirement 7.9
     */
    public static function logoPlaceholder(string $title): string
    {
        return mb_strtoupper(mb_substr($title, 0, 1, 'UTF-8'), 'UTF-8');
    }

    /**
     * Generate an avatar placeholder from a user's first and last name.
     *
     * Returns the uppercase first character of firstName concatenated with
     * the uppercase first character of lastName.
     *
     * @param string $firstName The user's first name.
     * @param string $lastName  The user's last name.
     * @return string The two-character uppercase initials.
     *
     * @see Requirement 8.11
     */
    public static function avatarPlaceholder(string $firstName, string $lastName): string
    {
        return mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_strtoupper(mb_substr($lastName, 0, 1, 'UTF-8'), 'UTF-8');
    }

    /**
     * Format a numeric amount with comma separators.
     *
     * Whole numbers are formatted without decimal places.
     * Fractional amounts are formatted with 2 decimal places.
     *
     * @param float $amount The amount to format.
     * @return string The formatted amount string.
     */
    private static function formatAmount(float $amount): string
    {
        if (floor($amount) == $amount) {
            return number_format($amount, 0, '.', ',');
        }

        return number_format($amount, 2, '.', ',');
    }
}
