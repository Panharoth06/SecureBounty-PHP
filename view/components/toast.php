<?php
/**
 * SecureBounty — Toast Notification Component
 *
 * Reads flash messages from session and renders toast notifications
 * with auto-dismiss (5 seconds). Clears session values after rendering.
 *
 * Session keys:
 *   $_SESSION['flash_success'] — Success message string
 *   $_SESSION['flash_error']   — Error message string
 *   $_SESSION['flash_info']    — Informational message string
 */

$toasts = [];

if (!empty($_SESSION['flash_success'])) {
    $toasts[] = [
        'type' => 'success',
        'message' => $_SESSION['flash_success'],
    ];
    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_error'])) {
    $toasts[] = [
        'type' => 'error',
        'message' => $_SESSION['flash_error'],
    ];
    unset($_SESSION['flash_error']);
}

if (!empty($_SESSION['flash_info'])) {
    $toasts[] = [
        'type' => 'info',
        'message' => $_SESSION['flash_info'],
    ];
    unset($_SESSION['flash_info']);
}

if (empty($toasts)) {
    return;
}
?>
<div class="toast-container" id="toastContainer">
    <?php foreach ($toasts as $index => $toast): ?>
        <div class="toast toast-<?php echo htmlspecialchars($toast['type']); ?> active" id="toast-<?php echo $index; ?>"
            role="alert" aria-live="polite">
            <div class="toast-content">
                <div class="toast-icon">
                    <?php if ($toast['type'] === 'success'): ?>
                        <i data-lucide="check-circle"></i>
                    <?php elseif ($toast['type'] === 'info'): ?>
                        <i data-lucide="info"></i>
                    <?php else: ?>
                        <i data-lucide="alert-circle"></i>
                    <?php endif; ?>
                </div>
                <p class="toast-message">
                    <?php echo htmlspecialchars($toast['message']); ?>
                </p>
                <button class="toast-close" onclick="dismissToast('toast-<?php echo $index; ?>')"
                    aria-label="Dismiss notification">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="toast-progress"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function dismissToast(id) {
        const toast = document.getElementById(id);
        if (toast) {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 200);
        }
    }

    // Auto-dismiss after 5 seconds
    document.querySelectorAll('.toast.active').forEach(function (toast) {
        setTimeout(function () {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 200);
        }, 5000);
    });
</script>