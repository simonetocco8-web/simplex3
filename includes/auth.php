<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['utente_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['utente_id'],
        'nome_utente' => $_SESSION['nome_utente'],
        'nome_completo' => $_SESSION['nome_completo'],
        'ruolo' => $_SESSION['ruolo'],
    ];
}

function loginUser(array $utente): void
{
    $_SESSION['utente_id'] = $utente['id'];
    $_SESSION['nome_utente'] = $utente['nome_utente'];
    $_SESSION['nome_completo'] = trim(($utente['nome'] ?? '') . ' ' . ($utente['cognome'] ?? ''));
    $_SESSION['ruolo'] = $utente['ruolo'];
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
