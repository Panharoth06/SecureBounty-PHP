<?php
/**
 * Profile Edit View
 *
 * Form for the authenticated user to update display name, bio, avatar,
 * and social links.
 *
 * Expected variables (set by UserController::editProfile):
 *   $profile    (array)  — current user data (id, first_name, last_name,
 *                          display_name, bio, avatar_path, *_url)
 *   $csrfToken  (string) — CSRF token
 *   $errors     (array)  — { field => message } validation errors
 *   $oldInput   (array)  — submitted values to repopulate
 *   $title      (string) — page title
 *   $activePage (string) — sidebar highlight slug
 */

$title = $title ?? 'SecureBounty | Edit Profile';
$activePage = $activePage ?? 'profile';
$profile = $profile ?? [];
$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$csrfToken = $csrfToken ?? '';

$profileId = (int) ($profile['id'] ?? 0);
$avatarPath = (string) ($profile['avatar_path'] ?? '');

/**
 * Helper: pick the value to display in an input — old submitted value first,
 * falling back to the current profile value.
 */
$valueFor = static function (string $field) use ($oldInput, $profile): string {
    if (array_key_exists($field, $oldInput)) {
        return (string) $oldInput[$field];
    }
    return (string) ($profile[$field] ?? '');
};

include __DIR__ . '/../components/layout.php';
?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=public-profile&amp;id=<?= $profileId ?>"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Profile
    </a>
</nav>

<h1 style="font-size:var(--text-heading); font-weight:600; margin:0 0 var(--space-lg);">Edit Profile</h1>

<?php if (isset($errors['general'])):
    $generalErrors = ['general' => $errors['general']];
    $errorsBackup = $errors;
    $errors = $generalErrors;
    include __DIR__ . '/../components/form-errors.php';
    $errors = $errorsBackup;
endif; ?>

<form method="POST" action="index.php?page=process-profile-edit" enctype="multipart/form-data" class="card-surface"
    style="padding:var(--space-lg); display:flex; flex-direction:column; gap:var(--space-lg);">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Avatar -->
    <div>
        <label for="avatar" class="form-label">Avatar</label>
        <?php if ($avatarPath !== ''): ?>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>" alt="Current avatar"
                    style="width:96px; height:96px; object-fit:cover; border-radius:var(--radius-full); border:1px solid var(--border);">
                <span style="color:var(--muted-foreground); font-size:var(--text-caption);">
                    Current avatar. Uploading a new image replaces this one.
                </span>
            </div>
        <?php endif; ?>
        <input type="file" id="avatar" name="avatar"
            class="form-input <?= isset($errors['avatar']) ? 'is-error' : '' ?>"
            accept="image/png,image/jpeg,image/gif">
        <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
            PNG/JPG/GIF, max 2 MB, resized to 150x150.
        </p>
        <?php if (isset($errors['avatar'])): ?>
            <p style="font-size:var(--text-caption); color:var(--destructive); margin-top:6px;">
                <?= htmlspecialchars((string) $errors['avatar'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Display name -->
    <div>
        <label for="display_name" class="form-label">Display Name</label>
        <input type="text" id="display_name" name="display_name"
            class="form-input <?= isset($errors['display_name']) ? 'is-error' : '' ?>" minlength="2" maxlength="50"
            value="<?= htmlspecialchars($valueFor('display_name'), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['display_name'])): ?>
            <p style="font-size:var(--text-caption); color:var(--destructive); margin-top:6px;">
                <?= htmlspecialchars((string) $errors['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Bio -->
    <?php $bioValue = $valueFor('bio'); ?>
    <div>
        <label for="bio" class="form-label">Bio</label>
        <textarea id="bio" name="bio" rows="4" maxlength="500"
            class="form-textarea <?= isset($errors['bio']) ? 'is-error' : '' ?>"
            placeholder="Tell other researchers about yourself..."><?= htmlspecialchars($bioValue, ENT_QUOTES, 'UTF-8') ?></textarea>
        <p id="bio-counter" style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
            <span id="bio-count">
                <?= mb_strlen($bioValue, 'UTF-8') ?>
            </span> / 500
        </p>
        <?php if (isset($errors['bio'])): ?>
            <p style="font-size:var(--text-caption); color:var(--destructive); margin-top:6px;">
                <?= htmlspecialchars((string) $errors['bio'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Social URLs -->
    <?php
    $urlFields = [
        'website_url' => 'Website',
        'github_url' => 'GitHub',
        'linkedin_url' => 'LinkedIn',
        'facebook_url' => 'Facebook',
        'youtube_url' => 'YouTube',
        'instagram_url' => 'Instagram',
    ];
    ?>
    <?php foreach ($urlFields as $field => $label): ?>
        <div>
            <label for="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" class="form-label">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <input type="url" id="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"
                name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"
                class="form-input <?= isset($errors[$field]) ? 'is-error' : '' ?>" placeholder="https://…"
                value="<?= htmlspecialchars($valueFor($field), ENT_QUOTES, 'UTF-8') ?>">
            <?php if (isset($errors[$field])): ?>
                <p style="font-size:var(--text-caption); color:var(--destructive); margin-top:6px;">
                    <?= htmlspecialchars((string) $errors[$field], ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Submit row -->
    <div style="display:flex; gap:var(--space-sm); justify-content:flex-end; flex-wrap:wrap;">
        <a href="index.php?page=public-profile&amp;id=<?= $profileId ?>" class="btn-secondary">Cancel</a>
        <button type="submit" class="btn-primary">
            <i data-lucide="save" style="width:14px; height:14px;"></i>
            Save Changes
        </button>
    </div>
</form>

<!-- Bio character counter -->
<script>
    (function () {
        const bio = document.getElementById('bio');
        const count = document.getElementById('bio-count');
        if (!bio || !count) return;
        const update = function () {
            count.textContent = String(bio.value.length);
        };
        bio.addEventListener('input', update);
        update();
    })();
</script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>