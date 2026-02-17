<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    header("Location: " . BASE_URL . "/public/login.php?erro=" . urlencode("Usuário ou senha inválidos"));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$sql = "SELECT id, nome, email, senha_hash, perfil, ativo
        FROM cad_usuarios
        WHERE email = :email
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':email', $email);
$stmt->execute();
$usuario = $stmt->fetch();

if (!$usuario || (int)$usuario['ativo'] !== 1 || !password_verify($senha, $usuario['senha_hash'])) {
    header("Location: " . BASE_URL . "/public/login.php?erro=" . urlencode("Usuário ou senha inválidos"));
    exit;
}

$_SESSION['logado'] = true;
$_SESSION['usuario_id'] = (int)$usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_perfil'] = $usuario['perfil'];

header("Location: " . BASE_URL . "/pages/dashboard.php");
exit;
