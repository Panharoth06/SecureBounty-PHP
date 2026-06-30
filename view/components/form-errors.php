<?php
/**
 * SecureBounty — Reusable Form Errors Component
 *
 * Renders a styled error box with field-specific validation messages.
 * Include this component in any form view where validation errors should
 * be displayed as a consolidated list above the form.
 *
 * Expected variable:
 *   $errors (array) — Associative array of field => message pairs.
 *                     A 'general' key renders as a single message instead of a list.
 *
 * Usage in a view:
 *   <?php $errors = $errors ?? []; include __DIR__ . '/components/form-errors.php'; ?>
 *
 * @see Requirement 1.3, 2.2, 4.6, 5.5
 */

if (empty($errors)) {
    return;
}
?>
<div class="form-errors-box" role="alert" aria-live="polite"
    style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-rejected-text); font-size:var(--text-body);">
    <?php if (isset($errors['general'])): ?>
        <p style="margin:0;">
            <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php else: ?>
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $field => $message): ?>
                <li>
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>