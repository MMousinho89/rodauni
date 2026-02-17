<?php
// pages/marcas_delete.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function ru_get_pdo(): PDO {
  if (function_exists('getPDO')) return getPDO();
  if (function_exists('get_pdo')) return get_pdo();
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) return $GLOBALS['conn'];
  if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) return $GLOBALS['db'];

  $host = defined('DB_HOST') ? DB_HOST : (defined('MYSQL_HOST') ? MYSQL_HOST : '127.0.0.1');
  $name = defined('DB_NAME') ? DB_NAME : (defined('MYSQL_DB') ? MYSQL_DB : 'rodauni');
  $user = defined('DB_USER') ? DB_USER : (defined('MYSQL_USER') ? MYSQL_USER : 'root');
  $pass = defined('DB_PASS') ? DB_PASS : (defined('MYSQL_PASS') ? MYSQL_PASS : '');

  $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

try {
  $pdo = ru_get_pdo();

  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);

  if (!is_array($payload)) {
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $id = (int)($payload['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $st = $pdo->prepare("DELETE FROM cad_marcas_veiculo WHERE id = :id");
  $st->execute([':id' => $id]);

  echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
