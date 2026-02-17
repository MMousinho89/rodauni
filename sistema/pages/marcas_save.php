<?php
// pages/marcas_save.php
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

function hasColumn(PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $key = $table . ':' . $column;
  if (isset($cache[$key])) return $cache[$key];

  $st = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
    LIMIT 1
  ");
  $st->execute([':t' => $table, ':c' => $column]);
  $row = $st->fetch();
  $cache[$key] = ((int)($row['c'] ?? 0) > 0);
  return $cache[$key];
}

try {
  $pdo = ru_get_pdo();
  $action = $_GET['action'] ?? '';

  $table = 'cad_marcas_veiculo';
  $hasAtualizadoEm = hasColumn($pdo, $table, 'atualizado_em');

  // LISTA
  if ($action === 'list') {
    $f = trim($_GET['f'] ?? '');

    $cols = "id, nome, ativo, criado_em" . ($hasAtualizadoEm ? ", atualizado_em" : "");
    $sql = "SELECT {$cols} FROM {$table} WHERE 1=1";
    $params = [];

    if ($f !== '') {
      $sql .= " AND nome LIKE :f";
      $params[':f'] = "%{$f}%";
    }

    $sql .= " ORDER BY nome ASC LIMIT 500";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    echo json_encode(['status' => 'ok', 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // GET
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(['status' => 'error', 'message' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $cols = "id, nome, ativo, criado_em" . ($hasAtualizadoEm ? ", atualizado_em" : "");
    $st = $pdo->prepare("SELECT {$cols} FROM {$table} WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $data = $st->fetch();

    if (!$data) {
      echo json_encode(['status' => 'error', 'message' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // SAVE (POST JSON)
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);

  if (!is_array($payload)) {
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $id = isset($payload['id']) ? (int)$payload['id'] : 0;
  $nome = trim((string)($payload['nome'] ?? ''));
  $ativo = isset($payload['ativo']) ? (int)$payload['ativo'] : 1;
  $ativo = ($ativo === 0) ? 0 : 1;

  if ($nome === '') {
    echo json_encode(['status' => 'error', 'message' => 'Informe a marca.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($id > 0) {
    if ($hasAtualizadoEm) {
      $st = $pdo->prepare("UPDATE {$table} SET nome = :nome, ativo = :ativo, atualizado_em = NOW() WHERE id = :id");
    } else {
      $st = $pdo->prepare("UPDATE {$table} SET nome = :nome, ativo = :ativo WHERE id = :id");
    }

    $st->execute([
      ':nome' => $nome,
      ':ativo' => $ativo,
      ':id' => $id
    ]);

    echo json_encode(['status' => 'ok', 'id' => $id], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($hasAtualizadoEm) {
    $st = $pdo->prepare("INSERT INTO {$table} (nome, ativo, atualizado_em) VALUES (:nome, :ativo, NOW())");
  } else {
    $st = $pdo->prepare("INSERT INTO {$table} (nome, ativo) VALUES (:nome, :ativo)");
  }

  $st->execute([
    ':nome' => $nome,
    ':ativo' => $ativo
  ]);

  echo json_encode(['status' => 'ok', 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
