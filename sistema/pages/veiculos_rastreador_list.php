<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function detectLabelColumn(PDO $pdo, string $table): string {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME IN ('descricao','nome','titulo')
    ORDER BY FIELD(COLUMN_NAME,'descricao','nome','titulo')
    LIMIT 1
  ");
  $stmt->execute([':t' => $table]);
  $col = $stmt->fetchColumn();
  return $col ?: 'id';
}

try {
  $action = $_GET['action'] ?? '';

  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(['ok'=>false,'error'=>'ID inválido.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $st = $pdo->prepare("SELECT * FROM cad_veiculo_rastreadores WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      echo json_encode(['ok'=>false,'error'=>'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    echo json_encode(['ok'=>true,'row'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $veiculoId = (int)($_GET['veiculo_id'] ?? 0);
  if ($veiculoId <= 0) {
    echo json_encode(['ok'=>true,'rows'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $tipLabel = detectLabelColumn($pdo, 'cad_tipos_rastreador');

  $sql = "
    SELECT r.id, r.veiculo_id, r.tipo_rastreador_id, r.identificador, r.ativo,
           t.`{$tipLabel}` AS tipo_label
    FROM cad_veiculo_rastreadores r
    LEFT JOIN cad_tipos_rastreador t ON t.id = r.tipo_rastreador_id
    WHERE r.veiculo_id = :vid
    ORDER BY r.id DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':vid'=>$veiculoId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
