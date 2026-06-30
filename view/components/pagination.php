<?php
/**
 * SecureBounty — Pagination Component
 *
 * Renders page navigation with prev/next buttons and page numbers.
 *
 * Parameters:
 *   $currentPage — Current active page number (1-based)
 *   $totalPages  — Total number of pages
 *   $baseUrl     — Base URL for page links (page number appended as &page=N)
 */

$currentPage = (int) ($currentPage ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$baseUrl = $baseUrl ?? '';

// Don't render if only one page
if ($totalPages <= 1) {
    return;
}

// Determine the separator character for URL params
$urlSeparator = (strpos($baseUrl, '?') !== false) ? '&' : '?';

/**
 * Build page URL
 */
function buildPageUrl(string $baseUrl, int $page, string $separator): string
{
    return htmlspecialchars($baseUrl . $separator . 'page=' . $page);
}

// Calculate visible page range (show max 7 pages with ellipsis)
$maxVisible = 7;
$startPage = 1;
$endPage = $totalPages;

if ($totalPages > $maxVisible) {
    $half = (int) floor($maxVisible / 2);
    $startPage = max(1, $currentPage - $half);
    $endPage = min($totalPages, $startPage + $maxVisible - 1);

    if ($endPage - $startPage < $maxVisible - 1) {
        $startPage = max(1, $endPage - $maxVisible + 1);
    }
}
?>
<nav class="pagination" aria-label="Page navigation">
    <!-- Previous button -->
    <?php if ($currentPage > 1): ?>
        <a href="<?php echo buildPageUrl($baseUrl, $currentPage - 1, $urlSeparator); ?>"
            class="pagination-btn pagination-prev" aria-label="Previous page">
            <i data-lucide="chevron-left"></i>
            <span>Prev</span>
        </a>
    <?php else: ?>
        <span class="pagination-btn pagination-prev disabled" aria-disabled="true">
            <i data-lucide="chevron-left"></i>
            <span>Prev</span>
        </span>
    <?php endif; ?>

    <!-- Page numbers -->
    <div class="pagination-pages">
        <?php if ($startPage > 1): ?>
            <a href="<?php echo buildPageUrl($baseUrl, 1, $urlSeparator); ?>" class="pagination-page">1</a>
            <?php if ($startPage > 2): ?>
                <span class="pagination-ellipsis">&hellip;</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i === $currentPage): ?>
                <span class="pagination-page active" aria-current="page">
                    <?php echo $i; ?>
                </span>
            <?php else: ?>
                <a href="<?php echo buildPageUrl($baseUrl, $i, $urlSeparator); ?>" class="pagination-page">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
                <span class="pagination-ellipsis">&hellip;</span>
            <?php endif; ?>
            <a href="<?php echo buildPageUrl($baseUrl, $totalPages, $urlSeparator); ?>" class="pagination-page">
                <?php echo $totalPages; ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Next button -->
    <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo buildPageUrl($baseUrl, $currentPage + 1, $urlSeparator); ?>"
            class="pagination-btn pagination-next" aria-label="Next page">
            <span>Next</span>
            <i data-lucide="chevron-right"></i>
        </a>
    <?php else: ?>
        <span class="pagination-btn pagination-next disabled" aria-disabled="true">
            <span>Next</span>
            <i data-lucide="chevron-right"></i>
        </span>
    <?php endif; ?>
</nav>