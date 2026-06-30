<?php
/**
 * Comments view for a report.
 * Displays the list of comments in chronological order.
 *
 * Variables available:
 * @var array  $comments  Array of comment records
 * @var int    $reportId  Report ID
 * @var string $title     Page title
 * @var string $activePage Active page indicator
 */

// This view is typically rendered within the report detail page context.
// Standalone rendering provided as a fallback.

if (!isset($comments)) {
    $comments = [];
}
?>
<div class="comments-section">
    <h3>Comments</h3>
    <?php if (empty($comments)): ?>
        <p>No comments yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <div class="comment <?php echo $comment['parent_id'] ? 'comment-reply' : 'comment-top-level'; ?>">
                <div class="comment-meta">
                    <strong>
                        <?php echo htmlspecialchars($comment['author_first_name'] . ' ' . $comment['author_last_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                    <span class="comment-date">
                        <?php echo htmlspecialchars($comment['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="comment-body">
                    <?php echo htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>