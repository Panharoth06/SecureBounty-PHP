<?php
/**
 * SecureBounty — Authenticated Layout Component
 *
 * Assembles: <html> + <head> + topbar + sidebar + main content wrapper.
 * Pages include this at the top and close with layout_end.php.
 *
 * Expects:
 *   $title (string) — page title
 *   $activePage (string) — current page slug for sidebar highlight
 *   $unreadCount (int, optional) — unread notification count
 *
 * Usage in a view:
 *   <?php $title = 'Dashboard'; $activePage = 'dashboard'; include __DIR__ . '/components/layout.php'; ?>
 *   <!-- page content here -->
 *   <?php include __DIR__ . '/components/layout_end.php'; ?>
 *
 * @see Requirement 13.1, 13.2, 13.3
 */

$title ??= 'SecureBounty';
$activePage ??= '';

// Wire unread notification count from the database when user is logged in
if (!isset($unreadCount) && !empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../model/repository/BaseRepository.php';
    require_once __DIR__ . '/../../model/repository/NotificationRepository.php';
    $__notifConn = require __DIR__ . '/../../config/database.php';
    $__notifRepo = new NotificationRepository($__notifConn);
    $unreadCount = $__notifRepo->getUnreadCount((int) $_SESSION['user_id']);
    $__recentNotifications = $__notifRepo->getByUserId((int) $_SESSION['user_id'], 7, 0);
} else {
    $unreadCount ??= 0;
    $__recentNotifications ??= [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — SecureBounty
    </title>

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Design System CSS -->
    <link href="view/assets/style.css" rel="stylesheet">

    <!-- Theme initialization (prevents flash) -->
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const preferred = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', preferred);
        })();
    </script>
</head>

<body class="app-layout-body">

    <?php include __DIR__ . '/topbar.php'; ?>

    <div class="app-layout-wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="app-main">
            <div class="app-content">