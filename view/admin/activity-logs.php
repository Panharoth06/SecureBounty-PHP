<?php
/**
 * SecureBounty — Admin: Activity Logs View
 *
 * Displays activity log entries with filter controls (user, action type, date range)
 * and pagination.
 *
 * Variables (set by AdminController::activityLogs):
 *   $logs    (array)       — log rows from ActivityLogRepository::getFiltered()
 *   $page    (int)         — current page number
 *   $success (string|null) — flash success message
 *   $error   (string|null) — flash error message
 *
 * Filter values from $_GET: user_id, action, date_from, date_to
 *
 * @see Requirements 12.3, 12.4
 */

$title ??= 'Activity Logs';
$activePage ??= 'admin-activity-logs';
include __DIR__ . '/../components/layout.php';

// Retrieve current filter values from GET
$filterUserId = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Common action types for the filter dropdown
$actionTypes = [
    'user.register',
    'user.login',
    'user.logout',
    'program.create',
    'program.update',
    'program.publish',
    'program.close',
    'report.submit',
    'report.status_change',
    'comment.create',
    'admin.user.deactivate',
    'admin.user.reactivate',
    'admin.user.change_role',
    'admin.program.suspend',
    'admin.program.reinstate',
];
?>

<div class="page-header">
    <h1 class="page-title">Activity Logs</h1>
    <p class="page-subtitle">Audit trail of all platform actions</p>
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

<!-- Filter Form -->
<form method="GET" action="index.php" class="filter-form">
    <input type="hidden" name="page" value="admin-activity-logs">

    <div class="filter-grid">
        <div class="form-group">
            <label for="filter-user-id" class="form-label">User ID</label>
            <input type="number" id="filter-user-id" name="user_id" class="input" placeholder="Enter user ID"
                value="<?= htmlspecialchars($filterUserId, ENT_QUOTES, 'UTF-8') ?>" min="1">
        </div>

        <div class="form-group">
            <label for="filter-action" class="form-label">Action Type</label>
            <select id="filter-action" name="action" class="input">
                <option value="">All actions</option>
                <?php foreach ($actionTypes as $actionType): ?>
                    <option value="<?= htmlspecialchars($actionType, ENT_QUOTES, 'UTF-8') ?>" <?= $filterAction === $actionType ? 'selected' : '' ?>>
                        <?= htmlspecialchars($actionType, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="filter-date-from" class="form-label">Date From</label>
            <input type="date" id="filter-date-from" name="date_from" class="input"
                value="<?= htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="filter-date-to" class="form-label">Date To</label>
            <input type="date" id="filter-date-to" name="date_to" class="input"
                value="<?= htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8') ?>">
        </div>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">
            <i data-lucide="search"></i> Filter
        </button>
        <a href="index.php?page=admin-activity-logs" class="btn btn-ghost btn-sm">
            <i data-lucide="x"></i> Clear
        </a>
    </div>
</form>

<?php if (empty($logs)): ?>
    <?php
    $emptyIcon = 'activity';
    $emptyTitle = 'No activity logs found';
    $emptyDescription = ($filterUserId || $filterAction || $filterDateFrom || $filterDateTo)
        ? 'No logs match the current filter criteria.'
        : 'No activity has been recorded yet.';
    include __DIR__ . '/../components/empty-state.php';
?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php if (!empty($log['user_first_name'])): ?>
                                <?= htmlspecialchars($log['user_first_name'] . ' ' . ($log['user_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <span class="text-muted text-xs">(#
                                    <?= (int) $log['user_id'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">User #
                                    <?= (int) $log['user_id'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge">
                                <?= htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['target_entity'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($log['target_id'])): ?>
                                <span class="text-muted">#
                                    <?= (int) $log['target_id'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Pagination - preserve current filters
    $totalPages = isset($totalLogs) ? (int) ceil($totalLogs / 20) : 1;
    $currentPage = $page ?? 1;
    $paginationParams = 'index.php?page=admin-activity-logs';
    if ($filterUserId !== '') {
        $paginationParams .= '&user_id=' . urlencode($filterUserId);
    }
    if ($filterAction !== '') {
        $paginationParams .= '&action=' . urlencode($filterAction);
    }
    if ($filterDateFrom !== '') {
        $paginationParams .= '&date_from=' . urlencode($filterDateFrom);
    }
    if ($filterDateTo !== '') {
        $paginationParams .= '&date_to=' . urlencode($filterDateTo);
    }
    $baseUrl = $paginationParams;
    include __DIR__ . '/../components/pagination.php';
?>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>