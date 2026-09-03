<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(?string $role = null, string $redirect = '../index.php'): void
{
    // Prevent the browser from serving this page from its back/forward cache
    // after logout - without this, pressing Back can show a stale, already
    // logged-out page instead of re-checking the session with the server.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (empty($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit;
    }

    if ($role !== null && $_SESSION['role'] !== $role) {
        header("Location: $redirect");
        exit;
    }
}
