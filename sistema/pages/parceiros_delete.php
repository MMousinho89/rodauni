<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

function tableExists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
  ");
  $st->execute([':t'=>$table]);
  return ((int)$st->fetchColumn() > 0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'Método inválido.']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = (int)($data['id'] ?? 0);

if ($id <= 0) json_out(['ok'=>false,'error'=>'ID inválido.']);

try{
  $pdo->beginTransaction();

  // se existir tabela de contatos, apaga junto
  if (tableExists($pdo, 'cad_parceiro_contatos')) {
    $st = $pdo->prepare("DELETE FROM cad_parceiro_contatos WHERE parceiro_id = :id");
    $st->execute([':id'=>$id]);
  }

  $st = $pdo->prepare("DELETE FROM cad_parceiros WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$id]);

  $pdo->commit();
  json_out(['ok'=>true]);
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  json_out(['ok'=>false,'error'=>'Erro ao excluir: '.$e->getMessage()]);
}
