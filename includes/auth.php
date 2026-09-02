<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(?string $role = null, string $redirect = '../index.php'): void
{
    if (empty($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit;
    }

    if ($role !== null && $_SESSION['role'] !== $role) {
        header("Location: $redirect");
        exit;
    }
}
