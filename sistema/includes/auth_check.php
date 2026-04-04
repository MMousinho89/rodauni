<?php
// includes/auth_check.php
require_once __DIR__ . '/../config/app.php';

// Garante sessão ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Compatibilidade: se algum arquivo antigo gravar outros nomes, tenta normalizar
if (empty($_SESSION['usuario_id'])) {
    if (!empty($_SESSION['user_id'])) {
        $_SESSION['usuario_id'] = $_SESSION['user_id'];
    } elseif (!empty($_SESSION['id_usuario'])) {
        $_SESSION['usuario_id'] = $_SESSION['id_usuario'];
    }
}

if (empty($_SESSION['usuario_nome'])) {
    if (!empty($_SESSION['user_name'])) {
        $_SESSION['usuario_nome'] = $_SESSION['user_name'];
    } elseif (!empty($_SESSION['nome_usuario'])) {
        $_SESSION['usuario_nome'] = $_SESSION['nome_usuario'];
    }
}

// Se não tiver sessão válida -> volta pro login
if (empty($_SESSION['usuario_id'])) {
    $url = BASE_URL . "/public/login.php";
    header("Location: " . $url);
    exit;
}