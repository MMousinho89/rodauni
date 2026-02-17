<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function nowSql(): string { return date('Y-m-d H:i:s'); }

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];

  $id = (int)($data['id'] ?? 0);
  $veiculoId = (int)($data['veiculo_id'] ?? 0);
  $tipoId = (int)($data['tipo_rastreador_id'] ?? 0);
  $ident = trim((string)($data['identificador'] ?? ''));
  $ativo = ((string)($data['ativo'] ?? '1') === '0') ? 0 : 1;

  if ($veiculoId <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Salve o veículo primeiro (ID não encontrado).'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($tipoId <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Tipo Rastreador é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($ident === '') {
    echo json_encode(['ok'=>false,'error'=>'Identificador é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // garante que o veículo existe (evita FK estourar)
  $st = $pdo->prepare("SELECT id FROM cad_veiculos WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$veiculoId]);
  if (!$st->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'Veículo não encontrado para vincular rastreador.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

  if ($id > 0) {
    $sql = "
      UPDATE cad_veiculo_rastreadores
      SET veiculo_id = :veiculo_id,
          tipo_rastreador_id = :tipo_rastreador_id,
          identificador = :identificador,
          ativo = :ativo,
          atualizado_em = :atualizado_em,
          atualizado_por = :atualizado_por
      WHERE id = :id
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':veiculo_id'=>$veiculoId,
      ':tipo_rastreador_id'=>$tipoId,
      ':identificador'=>$ident,
      ':ativo'=>$ativo,
      ':atualizado_em'=>nowSql(),
      ':atualizado_por'=>$usuario,
      ':id'=>$id
    ]);

    echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $sql = "
    INSERT INTO cad_veiculo_rastreadores
      (veiculo_id, tipo_rastreador_id, identificador, ativo, criado_em, criado_por, atualizado_em, atualizado_por)
    VALUES
      (:veiculo_id, :tipo_rastreador_id, :identificador, :ativo, :criado_em, :criado_por, :atualizado_em, :atualizado_por)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':veiculo_id'=>$veiculoId,
    ':tipo_rastreador_id'=>$tipoId,
    ':identificador'=>$ident,
    ':ativo'=>$ativo,
    ':criado_em'=>nowSql(),
    ':criado_por'=>$usuario,
    ':atualizado_em'=>nowSql(),
    ':atualizado_por'=>$usuario,
  ]);

  echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
