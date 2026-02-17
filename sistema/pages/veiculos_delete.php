<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];

  $id = (int)($data['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // FK cad_veiculo_rastreadores (ON DELETE CASCADE) vai limpar os rastreadores automaticamente
  $st = $pdo->prepare("DELETE FROM cad_veiculos WHERE id = :id");
  $st->execute([':id'=>$id]);

  if ($st->rowCount() <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Registro não encontrado para exclusão.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
