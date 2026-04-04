<?php
// ============================================
// config/app.php
// Ajustado para funcionar em DEV e PRODUÇÃO
// ============================================

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// Caminho do script atual
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Remove /public ou /pages do final
$basePath = preg_replace('#/(public|pages)(/.*)?$#', '', $scriptDir);

// Se estiver vazio, garante string vazia limpa
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));