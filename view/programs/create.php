<?php
/**
 * Program Creation Form View
 *
 * Displays a form for Program Owners to create a new bug bounty program.
 *
 * Variables available from ProgramController::create():
 *   $title     - Page title
 *   $activePage - Active navigation item ('programs')
 *   $csrfToken - CSRF token for the form
 *   $errors    - Validation errors array (nullable)
 *   $oldInput  - Previous form input for repopulation (nullable)
 *
 * @see Requirement 4.1, 4.5, 4.6
 */

$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=programs"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Programs
    </a>
</nav>

<!-- Page Header -->
<h1 style="font-size:var(--text-heading); font-weight:600; margin-bottom:var(--space-lg);">Create Program</h1>

<!-- Validation Errors -->
<?php include __DIR__ . '/../components/form-errors.php'; ?>

<!-- Create Form -->
<div class="card-surface" style="padding:var(--space-lg);">
    <form method="POST" action="index.php?page=process-program-create" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Title -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="title" class="form-label">Program Title</label>
            <input type="text" id="title" name="title"
                class="form-input <?= isset($errors['title']) ? 'is-error' : '' ?>"
                placeholder="e.g., Web Application Security Program"
                value="<?= htmlspecialchars($oldInput['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <!-- Description -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description"
                class="form-textarea <?= isset($errors['description']) ? 'is-error' : '' ?>"
                placeholder="Describe your program's goals, rules, and what researchers should know..." rows="6"
                required><?= htmlspecialchars($oldInput['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- Scope -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="scope" class="form-label">Scope</label>
            <textarea id="scope" name="scope" class="form-textarea <?= isset($errors['scope']) ? 'is-error' : '' ?>"
                placeholder="Define in-scope assets (domains, APIs, mobile apps, etc.)..." rows="4"
                required><?= htmlspecialchars($oldInput['scope'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                List the assets researchers are permitted to test. You can add reward policies after creating the
                program.
            </p>
        </div>

        <!-- Logo (optional) -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="logo" class="form-label">Program Logo <span
                    style="color:var(--muted-foreground); font-weight:400;">(optional)</span></label>
            <input type="file" id="logo" name="logo" class="form-input" accept="image/png,image/jpeg,image/gif">
            <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                PNG, JPG, or GIF. Max 2 MB. Will be resized to 200x200 pixels.
            </p>
        </div>

        <!-- Submit -->
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <a href="index.php?page=programs" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i data-lucide="plus" style="width:14px; height:14px;"></i> Create Program
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>