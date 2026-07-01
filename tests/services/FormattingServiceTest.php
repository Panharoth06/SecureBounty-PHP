<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../model/services/FormattingService.php';

/**
 * Unit tests for FormattingService.
 *
 * Verifies description truncation, bounty range formatting, tag truncation,
 * logo placeholder, and avatar placeholder generation.
 *
 * @covers FormattingService
 */
class FormattingServiceTest extends TestCase
{
    // ─── truncateDescription Tests ─────────────────────────────────────

    public function testTruncateDescriptionReturnsUnchangedWhenUnderLimit(): void
    {
        $short = 'This is a short description.';
        $this->assertSame($short, FormattingService::truncateDescription($short));
    }

    public function testTruncateDescriptionReturnsUnchangedAtExactly120Chars(): void
    {
        $exact = str_repeat('a', 120);
        $this->assertSame($exact, FormattingService::truncateDescription($exact));
    }

    public function testTruncateDescriptionTruncatesAndAppendsEllipsis(): void
    {
        $long = str_repeat('b', 150);
        $result = FormattingService::truncateDescription($long);

        $this->assertSame(str_repeat('b', 120) . '…', $result);
        $this->assertSame(121, mb_strlen($result, 'UTF-8'));
    }

    public function testTruncateDescriptionHandlesEmptyString(): void
    {
        $this->assertSame('', FormattingService::truncateDescription(''));
    }

    public function testTruncateDescriptionHandlesMultibyteCharacters(): void
    {
        // 121 multibyte characters should be truncated
        $multibyte = str_repeat('é', 121);
        $result = FormattingService::truncateDescription($multibyte);

        $this->assertSame(str_repeat('é', 120) . '…', $result);
    }

    // ─── formatBountyRange Tests ───────────────────────────────────────

    public function testFormatBountyRangeWithBothNull(): void
    {
        $this->assertSame('No bounty defined', FormattingService::formatBountyRange(null, null));
    }

    public function testFormatBountyRangeWithWholeNumbers(): void
    {
        $this->assertSame('$500 – $5,000', FormattingService::formatBountyRange(500.0, 5000.0));
    }

    public function testFormatBountyRangeWithLargeAmounts(): void
    {
        $this->assertSame('$1,000 – $100,000', FormattingService::formatBountyRange(1000.0, 100000.0));
    }

    public function testFormatBountyRangeWithFractionalAmounts(): void
    {
        $this->assertSame('$99.99 – $1,500.50', FormattingService::formatBountyRange(99.99, 1500.50));
    }

    public function testFormatBountyRangeWithMinNullDefaultsToZero(): void
    {
        $this->assertSame('$0 – $10,000', FormattingService::formatBountyRange(null, 10000.0));
    }

    public function testFormatBountyRangeWithMaxNullDefaultsToZero(): void
    {
        $this->assertSame('$500 – $0', FormattingService::formatBountyRange(500.0, null));
    }

    public function testFormatBountyRangeWithZeroValues(): void
    {
        $this->assertSame('$0 – $0', FormattingService::formatBountyRange(0.0, 0.0));
    }

    // ─── truncateTags Tests ────────────────────────────────────────────

    public function testTruncateTagsReturnsAllWhenUnderLimit(): void
    {
        $tags = ['PHP', 'React', 'MySQL'];
        $result = FormattingService::truncateTags($tags);

        $this->assertSame(['tags' => ['PHP', 'React', 'MySQL'], 'overflow' => 0], $result);
    }

    public function testTruncateTagsReturnsAllAtExactlyFive(): void
    {
        $tags = ['PHP', 'React', 'MySQL', 'AWS', 'Docker'];
        $result = FormattingService::truncateTags($tags);

        $this->assertSame(['tags' => $tags, 'overflow' => 0], $result);
    }

    public function testTruncateTagsTruncatesOverFive(): void
    {
        $tags = ['PHP', 'React', 'MySQL', 'AWS', 'Docker', 'Redis', 'Nginx'];
        $result = FormattingService::truncateTags($tags);

        $this->assertSame(
            ['tags' => ['PHP', 'React', 'MySQL', 'AWS', 'Docker'], 'overflow' => 2],
            $result
        );
    }

    public function testTruncateTagsWithCustomMaxVisible(): void
    {
        $tags = ['PHP', 'React', 'MySQL', 'AWS', 'Docker'];
        $result = FormattingService::truncateTags($tags, 3);

        $this->assertSame(
            ['tags' => ['PHP', 'React', 'MySQL'], 'overflow' => 2],
            $result
        );
    }

    public function testTruncateTagsWithEmptyArray(): void
    {
        $result = FormattingService::truncateTags([]);

        $this->assertSame(['tags' => [], 'overflow' => 0], $result);
    }

    // ─── logoPlaceholder Tests ─────────────────────────────────────────

    public function testLogoPlaceholderReturnsUppercaseFirstChar(): void
    {
        $this->assertSame('S', FormattingService::logoPlaceholder('SecureBounty'));
    }

    public function testLogoPlaceholderHandlesLowercaseInput(): void
    {
        $this->assertSame('M', FormattingService::logoPlaceholder('my program'));
    }

    public function testLogoPlaceholderHandlesMultibyteCharacter(): void
    {
        $this->assertSame('É', FormattingService::logoPlaceholder('éducation'));
    }

    public function testLogoPlaceholderHandlesNumberStart(): void
    {
        $this->assertSame('1', FormattingService::logoPlaceholder('123 Security'));
    }

    // ─── avatarPlaceholder Tests ───────────────────────────────────────

    public function testAvatarPlaceholderReturnsInitials(): void
    {
        $this->assertSame('JD', FormattingService::avatarPlaceholder('John', 'Doe'));
    }

    public function testAvatarPlaceholderHandlesLowercaseNames(): void
    {
        $this->assertSame('AB', FormattingService::avatarPlaceholder('alice', 'bob'));
    }

    public function testAvatarPlaceholderHandlesMultibyteNames(): void
    {
        $this->assertSame('ÉÖ', FormattingService::avatarPlaceholder('éric', 'österman'));
    }

    public function testAvatarPlaceholderHandlesSingleCharNames(): void
    {
        $this->assertSame('AB', FormattingService::avatarPlaceholder('A', 'B'));
    }
}
