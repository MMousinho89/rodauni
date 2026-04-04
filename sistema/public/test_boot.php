<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e) {
        echo '<pre style="background:#111;color:#0f0;padding:16px;font:14px monospace">';
        echo "ERRO FATAL\n";
        print_r($e);
        echo '</pre>';
    }
});

echo '<pre style="background:#111;color:#0f0;padding:16px;font:14px monospace">';
echo "INICIO\n";

try {
    echo "1. Carregando app.php...\n";
    require_once __DIR__ . '/../config/app.php';
    echo "OK app.php\n";

    echo "2. Carregando database.php...\n";
    require_once __DIR__ . '/../config/database.php';
    echo "OK database.php\n";

    echo "3. Verificando BASE_URL...\n";
    echo 'BASE_URL: ' . (defined('BASE_URL') ? BASE_URL : 'NAO DEFINIDA') . "\n";

    echo "4. Verificando PDO...\n";
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO não foi criado pelo database.php');
    }
    echo "OK PDO criado\n";

    echo "5. Testando banco atual...\n";
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "DATABASE(): " . ($db ?: 'VAZIO') . "\n";

    echo "6. Testando tabela cad_usuarios...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'cad_usuarios'");
    $tbl = $stmt->fetchColumn();
    echo "cad_usuarios: " . ($tbl ?: 'NAO ENCONTRADA') . "\n";

    echo "7. Finalizado sem erro.\n";
} catch (Throwable $e) {
    echo "ERRO CAPTURADO:\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString() . "\n";
}

echo '</pre>';