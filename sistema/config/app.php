<?php
// ============================================
// config/app.php
// Configuração automática de URL base
// Funciona em DEV e PRODUÇÃO sem alteração
// ============================================

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detecta automaticamente a pasta base
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = preg_replace('#/(public|pages)$#', '', $scriptDir);

define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));
