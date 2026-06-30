<?php
/**
 * SecureBounty — Admin: User Management View
 *
 * Displays a paginated table of all registered users with role, status,
 * and actions (deactivate, reactivate, change role).
 *
 * Variables (set by AdminController::userList):
 *   $users     (array)  — user rows from UserRepository::getAll()
 *   $csrfToken (string) — CSRF token for action forms
 *   $page      (int)    — current page number
 *   $success   (string|null) — flash success message
 *   $error     (string|null) — flash error message
 *
 * @see Requirements 10.1, 10.2, 10.3, 10.4, 10.5
 */

$title ??= 'User Management';
$activePage ??= 'admin-users';
include __DIR__ . '/../components/layout.php';

$adminUserId = (int) ($_SESSION['user_id'] ?? 0);

$roleNames = [
    1 => 'Admin',
    2 => 'Program Owner',
    3 => 'Researcher',
];
?>

<div class="page-header">
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Manage all platform user accounts</p>
</div>

<?php if (!empty($success)): ?>
    <div class="toast toast-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="toast toast-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <?php
    $emptyIcon = 'users';
    $emptyTitle = 'No users found';
    $emptyDescription = 'There are no registered users on the platform yet.';
    include __DIR__ . '/../components/empty-state.php';
?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userId = (int) $user['id'];
                    $userStatus = $user['status'] ?? 'active';
                    $userRoleId = (int) ($user['role_id'] ?? 0);
                    $isCurrentAdmin = ($userId === $adminUserId);
                    ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <span class="badge">
                                <?= htmlspecialchars($roleNames[$userRoleId] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($userStatus === 'active'): ?>
                                <span class="badge-accepted">Active</span>
                            <?php else: ?>
                                <span class="badge-rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="table-actions">
                            <?php if (!$isCurrentAdmin): ?>
                                <?php if ($userStatus === 'active'): ?>
                                    <!-- Deactivate -->
                                    <form method="POST" action="index.php?page=admin-deactivate-user" class="inline-form">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                                        <button type="submit" class="btn btn-sm btn-destructive" title="Deactivate user">
                                            <i data-lucide="user-x"></i> Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Reactivate -->
                                    <form method="POST" action="index.php?page=admin-reactivate-user" class="inline-form">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Reactivate user">
                                            <i data-lucide="user-check"></i> Reactivate
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Change Role -->
                                <form method="POST" action="index.php?page=admin-change-role" class="inline-form">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                                    <select name="role_id" class="input input-sm"
                                        aria-label="Change role for <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <option value="1" <?= $userRoleId === 1 ? 'selected' : '' ?>>Admin</option>
                                        <option value="2" <?= $userRoleId === 2 ? 'selected' : '' ?>>Program Owner</option>
                                        <option value="3" <?= $userRoleId === 3 ? 'selected' : '' ?>>Researcher</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-ghost" title="Change role">
                                        <i data-lucide="refresh-cw"></i> Change
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Current user</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Pagination
    $totalPages = isset($totalUsers) ? (int) ceil($totalUsers / 20) : 1;
    $currentPage = $page ?? 1;
    $baseUrl = 'index.php?page=admin-users';
    include __DIR__ . '/../components/pagination.php';
?>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>