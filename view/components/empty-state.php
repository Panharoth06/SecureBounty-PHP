<?php
/**
 * SecureBounty — Empty State Component
 *
 * Renders a centered icon + text pattern for when no data is available.
 *
 * Parameters:
 *   $icon        — Lucide icon name (e.g., "inbox", "file-text")
 *   $title       — Main heading text (e.g., "No reports yet")
 *   $description — Supporting caption text
 *   $actionUrl   — (optional) URL for the action button
 *   $actionLabel — (optional) Label for the action button
 */

$icon = $icon ?? 'inbox';
$title = $title ?? 'Nothing here';
$description = $description ?? '';
$actionUrl = $actionUrl ?? null;
$actionLabel = $actionLabel ?? null;
?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i data-lucide="<?php echo htmlspecialchars($icon); ?>"></i>
    </div>
    <h3 class="empty-state-title">
        <?php echo htmlspecialchars($title); ?>
    </h3>
    <?php if ($description): ?>
        <p class="empty-state-description">
            <?php echo htmlspecialchars($description); ?>
        </p>
    <?php endif; ?>
    <?php if ($actionUrl && $actionLabel): ?>
        <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn-primary btn-sm empty-state-action">
            <?php echo htmlspecialchars($actionLabel); ?>
        </a>
    <?php endif; ?>
</div>