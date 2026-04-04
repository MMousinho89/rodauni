<?php
// ==========================================================
// Arquivo: config/database.php
// Compatível com LOCAL (XAMPP) e PRODUÇÃO (cPanel)
// ==========================================================

// ----------------------------------------------------------
// 1. Detectar ambiente
// ----------------------------------------------------------
$httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

$isLocal = in_array($httpHost, array('localhost', '127.0.0.1', '::1'), true)
    || strpos($httpHost, 'localhost') !== false
    || strpos($httpHost, '127.0.0.1') !== false;

// ----------------------------------------------------------
// 2. Configurações do banco
// ----------------------------------------------------------
if ($isLocal) {
    // LOCAL (XAMPP)
    $DB_HOST = 'localhost';
    $DB_NAME = 'rodauni';
    $DB_USER = 'root';
    $DB_PASS = '';
} else {
    // PRODUÇÃO (CPANEL)
    $DB_HOST = 'localhost';
    $DB_NAME = 'uniaop25_rodauni';
    $DB_USER = 'uniaop25_MMousinho';
    $DB_PASS = 'Mm0u5inh0@()';
}

// ----------------------------------------------------------
// 3. Conexão PDO
// ----------------------------------------------------------
try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $DB_USER,
        $DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        )
    );

} catch (PDOException $e) {
    if ($isLocal) {
        die("❌ Erro ao conectar ao banco: " . $e->getMessage());
    }

    die("Erro ao conectar ao banco de dados.");
}