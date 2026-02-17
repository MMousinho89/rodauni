<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

function detectLabelColumn(PDO $pdo, string $table): string {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME IN ('descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    ORDER BY FIELD(COLUMN_NAME,'descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    LIMIT 1
  ");
  $st->execute([':t'=>$table]);
  $col = $st->fetchColumn();
  return $col ?: 'id';
}

$field = preg_replace('/[^a-zA-Z0-9_]/','', (string)($_GET['field'] ?? ''));
$value = trim((string)($_GET['value'] ?? ''));

if ($field === '' || $value === '') {
  json_out(['ok'=>false,'error'=>'Parâmetros inválidos.']);
}

try{
  if ($field === 'filial_id') {
    $table = 'cad_filiais';
    $labelCol = detectLabelColumn($pdo, $table);

    $st = $pdo->prepare("SELECT id, {$labelCol} AS label FROM {$table} WHERE id = :id LIMIT 1");
    $st->execute([':id'=>(int)$value]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if(!$row) json_out(['ok'=>false,'error'=>'Filial não encontrada.']);
    json_out(['ok'=>true,'id'=>$row['id'],'label'=>$row['label'] ?? ('#'.$row['id'])]);
  }

  if ($field === 'classificacao_id') {
    $table = 'cad_classificacoes';
    $labelCol = detectLabelColumn($pdo, $table);

    $st = $pdo->prepare("SELECT id, {$labelCol} AS label FROM {$table} WHERE id = :id LIMIT 1");
    $st->execute([':id'=>(int)$value]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if(!$row) json_out(['ok'=>false,'error'=>'Classificação não encontrada.']);
    json_out(['ok'=>true,'id'=>$row['id'],'label'=>$row['label'] ?? ('#'.$row['id'])]);
  }

  json_out(['ok'=>false,'error'=>'Campo de lookup não suportado.']);
}catch(Throwable $e){
  json_out(['ok'=>false,'error'=>'Erro no lookup: '.$e->getMessage()]);
}
