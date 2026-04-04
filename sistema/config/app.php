<?php
// ============================================
// config/app.php
// Ajustado para funcionar 100% em DEV e PRODUÇÃO
// ============================================

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Caminho do script
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Remove /public ou /pages em QUALQUER posição final
$basePath = preg_replace('#/(public|pages)(/.*)?$#', '', $scriptDir);

// Define URL base
define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));