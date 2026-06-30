<?php
/**
 * SecureBounty — Admin: Program Oversight View
 *
 * Displays all programs with filtering by status, and suspend/reinstate actions.
 *
 * Variables (set by AdminController::programList):
 *   $programs     (array)       — program rows from ProgramRepository::getAll()
 *   $csrfToken    (string)      — CSRF token for action forms
 *   $statusFilter (string|null) — current status filter value
 *   $page         (int)         — current page number
 *   $success      (string|null) — flash success message
 *   $error        (string|null) — flash error message
 *
 * @see Requirements 11.1, 11.2, 11.3, 11.4
 */

$title ??= 'Program Oversight';
$activePage ??= 'admin-programs';
include __DIR__ . '/../components/layout.php';

$statusFilter ??= null;
$allowedStatuses = ['draft', 'active', 'closed', 'suspended'];
?>

<div class="page-header">
    <h1 class="page-title">Program Oversight</h1>
    <p class="page-subtitle">Manage all bug bounty programs on the platform</p>
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

<!-- Status Filter Links -->
<div class="filter-bar">
    <span class="filter-label">Filter by status:</span>
    <a href="index.php?page=admin-programs"
        class="btn btn-sm <?= $statusFilter === null ? 'btn-primary' : 'btn-ghost' ?>">All</a>
    <?php foreach ($allowedStatuses as $status): ?>
        <a href="index.php?page=admin-programs&status=<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
            class="btn btn-sm <?= $statusFilter === $status ? 'btn-primary' : 'btn-ghost' ?>">
            <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($programs)): ?>
    <?php
    $emptyIcon = 'shield';
    $emptyTitle = 'No programs found';
    $emptyDescription = $statusFilter
        ? 'No programs match the selected status filter.'
        : 'There are no programs on the platform yet.';
    include __DIR__ . '/../components/empty-state.php';
?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $program): ?>
                    <?php
                    $programId = (int) $program['id'];
                    $programStatus = $program['status'] ?? 'draft';
                    $ownerName = ($program['owner_first_name'] ?? '') . ' ' . ($program['owner_last_name'] ?? '');
                    if (trim($ownerName) === '') {
                        $ownerName = $program['owner_email'] ?? 'Unknown';
                    }
                    ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($program['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?php
                            $type = 'status';
                            $value = $programStatus;
                            $statusBadgeMap = [
                                'draft' => 'badge-pending',
                                'active' => 'badge-accepted',
                                'closed' => 'badge-rejected',
                                'suspended' => 'badge-critical',
                            ];
                            $badgeClass = $statusBadgeMap[$programStatus] ?? 'badge-pending';
                            ?>
                            <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucfirst($programStatus), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($program['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="table-actions">
                            <?php if ($programStatus === 'active' || $programStatus === 'draft'): ?>
                                <!-- Suspend -->
                                <form method="POST" action="index.php?page=admin-suspend-program" class="inline-form">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="program_id" value="<?= $programId ?>">
                                    <button type="submit" class="btn btn-sm btn-destructive" title="Suspend program">
                                        <i data-lucide="pause-circle"></i> Suspend
                                    </button>
                                </form>
                            <?php elseif ($programStatus === 'suspended'): ?>
                                <!-- Reinstate -->
                                <form method="POST" action="index.php?page=admin-reinstate-program" class="inline-form">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="program_id" value="<?= $programId ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Reinstate program">
                                        <i data-lucide="play-circle"></i> Reinstate
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Pagination
    $totalPages = isset($totalPrograms) ? (int) ceil($totalPrograms / 20) : 1;
    $currentPage = $page ?? 1;
    $baseUrl = 'index.php?page=admin-programs' . ($statusFilter ? '&status=' . urlencode($statusFilter) : '');
    include __DIR__ . '/../components/pagination.php';
?>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>