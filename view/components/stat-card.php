<?php
/**
 * SecureBounty — Stat Card Component
 *
 * Renders a stat card with a border-left accent, number display, and label.
 *
 * Parameters:
 *   $label — Caption text (e.g., "Total Programs")
 *   $value — Numeric or string value to display prominently
 *   $icon  — (optional) Lucide icon name (e.g., "shield", "file-text")
 */

$label = $label ?? '';
$value = $value ?? 0;
$icon = $icon ?? null;
?>
<div class="stat-card">
    <?php if ($icon): ?>
        <div class="stat-card-icon">
            <i data-lucide="<?php echo htmlspecialchars($icon); ?>"></i>
        </div>
    <?php endif; ?>
    <div class="stat-card-body">
        <span class="stat-number"><?php echo htmlspecialchars((string) $value); ?></span>
        <span class="stat-label"><?php echo htmlspecialchars($label); ?></span>
    </div>
</div>
