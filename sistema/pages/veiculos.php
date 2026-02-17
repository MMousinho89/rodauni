<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';

function detectLabelColumn(PDO $pdo, string $table): string {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME IN ('descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    ORDER BY FIELD(COLUMN_NAME,'descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    LIMIT 1
  ");
  $stmt->execute([':t' => $table]);
  $col = $stmt->fetchColumn();
  return $col ?: 'id';
}

function getColumns(PDO $pdo, string $table): array {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
  ");
  $st->execute([':t' => $table]);
  return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function fetchOptions(PDO $pdo, string $table, ?string $where = null, array $params = []): array {
  $labelCol = detectLabelColumn($pdo, $table);
  $labelColSql = "`" . str_replace("`", "", $labelCol) . "`";

  $sql = "SELECT id, {$labelColSql} AS label FROM `{$table}`";
  if ($where) $sql .= " " . $where;
  $sql .= " ORDER BY label";

  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    return [];
  }
}

function fetchParceirosWithClassificacao(PDO $pdo): array {
  $cols = getColumns($pdo, 'cad_parceiros');
  if (!$cols) return [];

  $labelCol = detectLabelColumn($pdo, 'cad_parceiros');
  $labelColSql = "`" . str_replace("`","", $labelCol) . "`";

  $hasClass = in_array('classificacao', $cols, true);
  $hasSitu  = in_array('situacao', $cols, true);
  $hasAtivo = in_array('ativo', $cols, true);

  $where = [];
  if ($hasSitu)  $where[] = "situacao = 'ATIVO'";
  if ($hasAtivo) $where[] = "ativo = 1";

  $sql = "SELECT id, {$labelColSql} AS label";
  if ($hasClass) $sql .= ", classificacao";
  $sql .= " FROM cad_parceiros";
  if ($where) $sql .= " WHERE " . implode(" AND ", $where);
  $sql .= " ORDER BY label";

  try {
    $q = $pdo->query($sql);
    return $q ? ($q->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
  } catch (Throwable $e) {
    return [];
  }
}

$optsFiliais         = fetchOptions($pdo, 'cad_filiais');
$optsTiposVeiculo    = fetchOptions($pdo, 'cad_tipos_veiculo', "WHERE ativo = 1");
$optsMarcas          = fetchOptions($pdo, 'cad_marcas_veiculo');
$optsSituacoes       = fetchOptions($pdo, 'cad_situacoes');
$optsParceiros       = fetchParceirosWithClassificacao($pdo);
$optsTiposRastreador = fetchOptions($pdo, 'cad_tipos_rastreador', "WHERE ativo = 1");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RODAUNI — Veículos</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/custom.css">

  <style>
    .ru-page-title-line{
      display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
      gap:.75rem; margin-bottom:1rem;
    }
    .ru-page-actions .btn{ font-size:.8rem; }
    .ru-tabcard .nav-tabs .nav-link{ font-size:.8rem; padding:.5rem .75rem; }
    .ru-field-label{ font-size:.75rem; font-weight:600; color:#6b7280; margin-bottom:.25rem; }
    .ru-audit{
      display:flex; flex-wrap:wrap; gap:.75rem;
      padding:.75rem 1rem; border-top:1px solid rgba(0,0,0,.08);
      background:#fff;
      position: sticky; bottom: 0; z-index: 5;
    }
    .ru-audit .badge{ font-weight:600; }
    .is-readonly{ background:#f8f9fa !important; }
    .ru-row-selected{
      outline: 2px solid rgba(13,110,253,.35);
      background: rgba(13,110,253,.06);
    }
    .is-invalid-msg{ font-size:.75rem; color:#dc3545; margin-top:.25rem; }
    .ru-subhint{ font-size:.8rem; color:#6b7280; }
    .ru-inline-actions{
      display:flex; gap:.5rem; justify-content:flex-end; align-items:center;
    }
  </style>
</head>

<body class="bg-light">
<div class="ru-app">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="ru-main">
    <div class="ru-page-title-line">
      <div>
        <div class="text-muted small">Cadastros</div>
        <h4 class="mb-0">Veículos</h4>
        <div class="text-muted small">Modelo União — DADOS / RASTREADOR / LISTA</div>
      </div>

      <div class="ru-page-actions d-flex flex-wrap gap-2">
        <button id="btnIncluir" type="button" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Incluir</button>
        <button id="btnEditar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Editar</button>
        <button id="btnSalvar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar</button>
        <button id="btnCancelar"type="button" class="btn btn-warning btn-sm text-dark"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button id="btnExcluir" type="button" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Excluir</button>
        <a href="dashboard.php" class="btn btn-dark btn-sm"><i class="bi bi-arrow-left-circle me-1"></i>Voltar</a>
      </div>
    </div>

    <div class="card shadow-sm border-0 ru-tabcard">
      <div class="card-header bg-white border-0 pb-0">
        <ul class="nav nav-tabs border-bottom-0" id="tabsVeic" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDados" type="button">DADOS</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRast" type="button">RASTREADOR</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLista" type="button">LISTA</button></li>
        </ul>
      </div>

      <div class="card-body">
        <div id="msgBox" class="alert d-none" role="alert"></div>

        <form id="frmVeic" autocomplete="off">
          <input type="hidden" name="id" id="id" value="">

          <div class="tab-content">
            <!-- ===================== DADOS ===================== -->
            <div class="tab-pane fade show active" id="tabDados" role="tabpanel">
              <div class="row g-3">

                <div class="col-md-2">
                  <label class="ru-field-label">Código</label>
                  <input type="text" class="form-control form-control-sm is-readonly" id="id_view" value="" readonly>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Placa *</label>
                  <input type="text" class="form-control form-control-sm" name="placa" id="placa" maxlength="10" required>
                  <div class="is-invalid-msg d-none" id="err_placa"></div>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Placa 2</label>
                  <input type="text" class="form-control form-control-sm" name="placa_2" id="placa_2" maxlength="10">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Placa 3</label>
                  <input type="text" class="form-control form-control-sm" name="placa_3" id="placa_3" maxlength="10">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Filial</label>
                  <select class="form-select form-select-sm" name="filial_id" id="filial_id">
                    <option value="">—</option>
                    <?php foreach ($optsFiliais as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['label'] ?? ('#'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- ✅ Regrid: Parceiro maior / Classificação ao lado (sem buracos) -->
                <div class="col-md-4">
                  <label class="ru-field-label">Parceiro Comercial</label>
                  <select class="form-select form-select-sm" name="parceiro_id" id="parceiro_id">
                    <option value="">—</option>
                    <?php foreach ($optsParceiros as $o):
                      $class = strtoupper(trim((string)($o['classificacao'] ?? '')));
                      $label = (string)($o['label'] ?? ('#'.$o['id']));
                    ?>
                      <option value="<?= (int)$o['id'] ?>" data-classificacao="<?= htmlspecialchars($class) ?>">
                        <?= htmlspecialchars($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="is-invalid-msg d-none" id="err_parceiro_id"></div>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Classificação Parceiro</label>
                  <select class="form-select form-select-sm" id="parceiro_classificacao">
                    <option value="">—</option>
                    <option value="PROPRIO">PROPRIO</option>
                    <option value="AGREGADO">AGREGADO</option>
                    <option value="TERCEIRO">TERCEIRO</option>
                    <option value="ALUGADO">ALUGADO</option>
                  </select>
                </div>

                <!-- ✅ Linha continua sem "Tipo (Vínculo)" -->
                <div class="col-md-3">
                  <label class="ru-field-label">Tipo de Veículo</label>
                  <select class="form-select form-select-sm" name="tipo_veiculo_id" id="tipo_veiculo_id">
                    <option value="">—</option>
                    <?php foreach ($optsTiposVeiculo as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['label'] ?? ('#'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Marca</label>
                  <select class="form-select form-select-sm" name="marca_id" id="marca_id">
                    <option value="">—</option>
                    <?php foreach ($optsMarcas as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['label'] ?? ('#'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Modelo</label>
                  <input type="text" class="form-control form-control-sm" name="modelo" id="modelo" maxlength="80" placeholder="Digite o modelo">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Cor</label>
                  <input type="text" class="form-control form-control-sm" name="cor" id="cor" maxlength="50" placeholder="Ex: Branco">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Combustível</label>
                  <input type="text" class="form-control form-control-sm" name="combustivel" id="combustivel" maxlength="50" placeholder="Ex: Diesel">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Situação</label>
                  <select class="form-select form-select-sm" name="situacao_id" id="situacao_id">
                    <option value="">—</option>
                    <?php foreach ($optsSituacoes as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['label'] ?? ('#'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Status Operacional</label>
                  <select class="form-select form-select-sm" name="status_operacional" id="status_operacional">
                    <option value="">—</option>
                    <option>Disponível</option>
                    <option>Em Rota</option>
                    <option>Oficina</option>
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Ano Fabricação</label>
                  <input type="number" class="form-control form-control-sm" name="ano_fabricacao" id="ano_fabricacao" min="1900" max="2100">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Ano Modelo</label>
                  <input type="number" class="form-control form-control-sm" name="ano_modelo" id="ano_modelo" min="1900" max="2100">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Chassi</label>
                  <input type="text" class="form-control form-control-sm" name="chassi" id="chassi" maxlength="30">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Renavam</label>
                  <input type="text" class="form-control form-control-sm" name="renavam" id="renavam" maxlength="20">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Eixos</label>
                  <input type="number" class="form-control form-control-sm" name="eixos" id="eixos" min="0" max="20">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Data Aquisição</label>
                  <input type="date" class="form-control form-control-sm" name="data_aquisicao" id="data_aquisicao">
                </div>

              </div>
            </div>

            <!-- ===================== RASTREADOR ===================== -->
            <div class="tab-pane fade" id="tabRast" role="tabpanel">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="ru-subhint">Vinculados ao veículo selecionado. (Permite múltiplos)</div>
                <div class="ru-inline-actions">
                  <button type="button" id="btnRastNovo" class="btn btn-outline-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Novo</button>
                  <button type="button" id="btnRastSalvar" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar</button>
                  <button type="button" id="btnRastCancelar" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
                </div>
              </div>

              <div id="msgRast" class="alert d-none" role="alert"></div>

              <input type="hidden" id="rast_id" value="">

              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="ru-field-label">Tipo Rastreador</label>
                  <select class="form-select form-select-sm" id="tipo_rastreador_id">
                    <option value="">—</option>
                    <?php foreach ($optsTiposRastreador as $o): ?>
                      <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['label'] ?? ('#'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="is-invalid-msg d-none" id="err_tipo_rastreador_id"></div>
                </div>

                <div class="col-md-5">
                  <label class="ru-field-label">Identificador (ID)</label>
                  <input type="text" class="form-control form-control-sm" id="identificador" maxlength="80" placeholder="Ex: 123445">
                  <div class="is-invalid-msg d-none" id="err_identificador"></div>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Ativo</label>
                  <select class="form-select form-select-sm" id="rast_ativo">
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                  </select>
                </div>

                <div class="col-md-1 d-grid">
                  <button type="button" id="btnRastExcluir" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                </div>
              </div>

              <hr class="my-3"/>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead>
                    <tr>
                      <th style="width:80px;">ID</th>
                      <th>Tipo</th>
                      <th>Identificador</th>
                      <th style="width:90px;">Ativo</th>
                      <th style="width:140px;">Ações</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyRast">
                    <tr><td colspan="5" class="text-muted small">Selecione um veículo para ver os rastreadores.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ===================== LISTA ===================== -->
            <div class="tab-pane fade" id="tabLista" role="tabpanel">
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-6">
                  <label class="ru-field-label">Buscar</label>
                  <input type="text" class="form-control form-control-sm" id="filtroLista" placeholder="Placa, Chassi, Renavam...">
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btnAtualizarLista">
                    <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                  </button>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead>
                    <tr>
                      <th style="width:80px;">ID</th>
                      <th>Placa</th>
                      <th>Filial</th>
                      <th>Tipo Veículo</th>
                      <th>Marca</th>
                      <th>Situação</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyLista">
                    <tr><td colspan="7" class="text-muted small">Carregando...</td></tr>
                  </tbody>
                </table>
              </div>

              <div class="text-muted small">Clique para selecionar. Duplo clique abre o cadastro.</div>
            </div>
          </div>

          <div class="ru-audit">
            <span class="badge text-bg-light border">Logado: <?= htmlspecialchars($usuarioNome) ?></span>
            <span class="badge text-bg-light border" id="auditCriado">Criado: —</span>
            <span class="badge text-bg-light border" id="auditAtualizado">Atualizado: —</span>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const $ = (s) => document.querySelector(s);

const state = { mode:'view', selectedId:null, loadedId:null };

function showMsg(type, text){
  const box = $('#msgBox');
  box.className = 'alert alert-' + type;
  box.textContent = text;
  box.classList.remove('d-none');
  setTimeout(()=> box.classList.add('d-none'), 4500);
}
function showMsgRast(type, text){
  const box = $('#msgRast');
  box.className = 'alert alert-' + type;
  box.textContent = text;
  box.classList.remove('d-none');
  setTimeout(()=> box.classList.add('d-none'), 4500);
}

function clearInlineErrors(){
  ['placa','parceiro_id'].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.classList.remove('is-invalid');
    const msg = document.getElementById('err_'+id);
    if(msg){ msg.textContent=''; msg.classList.add('d-none'); }
  });
}
function setInlineError(fieldId, message){
  const el = document.getElementById(fieldId);
  const msg = document.getElementById('err_'+fieldId);
  if(el) el.classList.add('is-invalid');
  if(msg){ msg.textContent = message; msg.classList.remove('d-none'); }
}

function clearRastErrors(){
  ['tipo_rastreador_id','identificador'].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.classList.remove('is-invalid');
    const msg = document.getElementById('err_'+id);
    if(msg){ msg.textContent=''; msg.classList.add('d-none'); }
  });
}
function setRastError(id, msg){
  const el = document.getElementById(id);
  const box = document.getElementById('err_'+id);
  if(el) el.classList.add('is-invalid');
  if(box){ box.textContent = msg; box.classList.remove('d-none'); }
}

// ✅ auto: parceiro -> classif (PROPRIO/AGREGADO/TERCEIRO). Mantém ALUGADO manual.
function applyParceiroAuto(){
  const parceiroSel = $('#parceiro_id');
  const classSel = $('#parceiro_classificacao');
  if(!parceiroSel || !classSel) return;

  const opt = parceiroSel.options[parceiroSel.selectedIndex];
  const classificacaoParceiro = (opt?.dataset?.classificacao || '').toUpperCase().trim();

  let desired = '';
  if(!parceiroSel.value){
    desired = 'PROPRIO';
  } else if(classificacaoParceiro === 'AGREGADO'){
    desired = 'AGREGADO';
  } else {
    desired = 'TERCEIRO';
  }

  if(classSel.value !== 'ALUGADO'){
    classSel.value = desired;
  }
}

function setFormEnabled(enabled){
  const form = $('#frmVeic');
  form.querySelectorAll('input, select, textarea').forEach(el=>{
    if(el.id === 'id_view') return;
    if(el.name === 'id') return;
    if(el.id === 'filtroLista') return;
    if(el.closest('#tabLista')) return;
    if(el.closest('#tabRast')) return;

    el.disabled = !enabled;
  });

  applyParceiroAuto();
}

function clearForm(){
  $('#id').value = '';
  $('#id_view').value = '';
  $('#frmVeic').reset();
  $('#auditCriado').textContent = 'Criado: —';
  $('#auditAtualizado').textContent = 'Atualizado: —';
  state.loadedId = null;
  clearInlineErrors();
  applyParceiroAuto();
  clearRastForm();
  $('#tbodyRast').innerHTML = `<tr><td colspan="5" class="text-muted small">Selecione um veículo para ver os rastreadores.</td></tr>`;
}

function setToolbar(){
  const hasId = !!($('#id').value || state.selectedId);
  const isEdit = (state.mode === 'edit' || state.mode === 'new');

  $('#btnIncluir').disabled  = isEdit;
  $('#btnEditar').disabled   = !hasId || isEdit;
  $('#btnSalvar').disabled   = !isEdit;
  $('#btnCancelar').disabled = !isEdit;
  $('#btnExcluir').disabled  = !($('#id').value) || isEdit;

  $('#btnAtualizarLista').disabled = false;
  $('#filtroLista').disabled = false;

  setFormEnabled(isEdit);
  setRastControlsEnabled(!isEdit);
}

function highlightSelectedRow(){
  document.querySelectorAll('#tbodyLista tr').forEach(tr => tr.classList.remove('ru-row-selected'));
  if(state.selectedId == null) return;
  const tr = document.querySelector(`#tbodyLista tr[data-id="${state.selectedId}"]`);
  if(tr) tr.classList.add('ru-row-selected');
}

async function loadLista(){
  const filtro = ($('#filtroLista').value || '').trim();
  const res = await fetch(`veiculos_save.php?action=list&f=${encodeURIComponent(filtro)}`, { credentials: 'same-origin' });
  const data = await res.json();

  const tb = $('#tbodyLista');
  tb.innerHTML = '';

  if(!data.ok){
    tb.innerHTML = `<tr><td colspan="7" class="text-danger small">${data.error || 'Erro ao listar'}</td></tr>`;
    return;
  }

  if(!data.rows || data.rows.length === 0){
    tb.innerHTML = `<tr><td colspan="7" class="text-muted small">Nenhum registro.</td></tr>`;
    return;
  }

  data.rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.dataset.id = r.id;
    tr.style.cursor = 'pointer';
    tr.innerHTML = `
      <td>${r.id}</td>
      <td><strong>${r.placa || ''}</strong></td>
      <td>${r.filial_label || ''}</td>
      <td>${r.tipo_veiculo_label || ''}</td>
      <td>${r.marca_label || ''}</td>
      <td>${r.situacao_label || ''}</td>
      <td>${r.status_operacional || ''}</td>
    `;

    tr.addEventListener('click', async ()=> {
      state.selectedId = r.id;
      highlightSelectedRow();
      await loadVeiculo(r.id, { switchTab:false });
      setToolbar();
    });

    tr.addEventListener('dblclick', async ()=> {
      state.selectedId = r.id;
      highlightSelectedRow();
      await loadVeiculo(r.id, { switchTab:true });
      state.mode = 'view';
      setToolbar();
    });

    tb.appendChild(tr);
  });

  highlightSelectedRow();
}

async function loadVeiculo(id, options = { switchTab:true }){
  const res = await fetch(`veiculos_save.php?action=get&id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
  const data = await res.json();

  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao carregar');
    return;
  }

  const v = data.row;

  $('#id').value = v.id || '';
  $('#id_view').value = v.id || '';
  state.loadedId = v.id || null;

  for (const k in v){
    const el = document.getElementById(k);
    if(el && (el.name || el.id === 'modelo' || el.id === 'cor' || el.id === 'combustivel')){
      if(el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA'){
        el.value = (v[k] ?? '');
      }
    }
  }

  $('#auditCriado').textContent = `Criado: ${(v.criado_em || '—')} por ${(v.criado_por || '—')}`;
  $('#auditAtualizado').textContent = `Atualizado: ${(v.atualizado_em || '—')} por ${(v.atualizado_por || '—')}`;

  state.mode = 'view';
  clearInlineErrors();

  applyParceiroAuto();

  await loadRastLista();

  if(options.switchTab){
    const tab = new bootstrap.Tab(document.querySelector('button[data-bs-target="#tabDados"]'));
    tab.show();
  }
}

function formDataToObject(form){
  const fd = new FormData(form);
  const obj = {};
  fd.forEach((v,k)=> obj[k] = v);

  if(obj.placa)   obj.placa   = obj.placa.toUpperCase().replace(/\s+/g,'').trim();
  if(obj.placa_2) obj.placa_2 = obj.placa_2.toUpperCase().replace(/\s+/g,'').trim();
  if(obj.placa_3) obj.placa_3 = obj.placa_3.toUpperCase().replace(/\s+/g,'').trim();

  ['filial_id','parceiro_id','tipo_veiculo_id','marca_id','situacao_id','ano_fabricacao','ano_modelo','eixos']
    .forEach(k => { if(obj[k] === '') obj[k] = null; });

  if(obj.data_aquisicao === '') obj.data_aquisicao = null;

  return obj;
}

function validateForm(){
  clearInlineErrors();

  const placa = ($('#placa').value || '').trim();
  if(!placa){
    setInlineError('placa','Placa é obrigatória.');
    return false;
  }

  return true;
}

async function saveVeiculo(){
  if(!validateForm()) return;

  const obj = formDataToObject($('#frmVeic'));

  const res = await fetch('veiculos_save.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(obj)
  });
  const data = await res.json();

  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao salvar');
    return;
  }

  showMsg('success', 'Salvo com sucesso.');
  state.selectedId = data.id;
  await loadVeiculo(data.id, { switchTab:true });
  await loadLista();
  setToolbar();
}

async function deleteVeiculo(){
  const id = $('#id').value;
  if(!id) return;

  if(!confirm('Confirma excluir este veículo?')) return;

  const res = await fetch('veiculos_delete.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  const data = await res.json();

  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao excluir');
    return;
  }

  showMsg('success', 'Excluído com sucesso.');
  clearForm();
  state.selectedId = null;
  state.mode = 'view';
  setToolbar();
  await loadLista();
}

$('#parceiro_id')?.addEventListener('change', ()=> {
  applyParceiroAuto();
});

$('#parceiro_classificacao')?.addEventListener('change', ()=>{
  if($('#parceiro_classificacao').value === 'PROPRIO'){
    $('#parceiro_id').value = '';
  }
  // mantém ALUGADO manual; senão recalcula
  applyParceiroAuto();
});

$('#btnIncluir').addEventListener('click', ()=>{
  clearForm();
  state.selectedId = null;
  state.mode = 'new';
  setToolbar();
  new bootstrap.Tab(document.querySelector('button[data-bs-target="#tabDados"]')).show();
});

$('#btnEditar').addEventListener('click', async ()=>{
  const idAtual = $('#id').value;
  const idSelecionado = state.selectedId;
  const idParaEditar = idAtual || idSelecionado;
  if(!idParaEditar) return;

  if(!idAtual || String(idAtual) !== String(idParaEditar)){
    await loadVeiculo(idParaEditar, { switchTab:false });
  }

  state.mode = 'edit';
  setToolbar();
  new bootstrap.Tab(document.querySelector('button[data-bs-target="#tabDados"]')).show();
});

$('#btnCancelar').addEventListener('click', async ()=>{
  state.mode = 'view';
  setToolbar();

  if($('#id').value){
    await loadVeiculo($('#id').value, { switchTab:true });
  } else {
    clearForm();
  }
});

$('#btnSalvar').addEventListener('click', saveVeiculo);
$('#btnExcluir').addEventListener('click', deleteVeiculo);

$('#btnAtualizarLista').addEventListener('click', loadLista);
$('#filtroLista').addEventListener('keydown', (e)=>{
  if(e.key === 'Enter'){ e.preventDefault(); loadLista(); }
});

/* ===================== RASTREADOR (igual) ===================== */
function setRastControlsEnabled(enabled){
  const hasVeic = !!($('#id').value);
  const can = enabled && hasVeic;

  ['btnRastNovo','btnRastSalvar','btnRastCancelar','btnRastExcluir','tipo_rastreador_id','identificador','rast_ativo']
    .forEach(id => { const el = document.getElementById(id); if(el) el.disabled = !can; });
}

function clearRastForm(){
  $('#rast_id').value = '';
  $('#tipo_rastreador_id').value = '';
  $('#identificador').value = '';
  $('#rast_ativo').value = '1';
  clearRastErrors();
}

async function loadRastLista(){
  const veiculoId = $('#id').value;
  const tb = $('#tbodyRast');

  if(!veiculoId){
    tb.innerHTML = `<tr><td colspan="5" class="text-muted small">Salve o veículo primeiro para vincular rastreadores.</td></tr>`;
    return;
  }

  const res = await fetch(`veiculos_rastreador_list.php?veiculo_id=${encodeURIComponent(veiculoId)}`, { credentials: 'same-origin' });
  const data = await res.json();

  tb.innerHTML = '';
  if(!data.ok){
    tb.innerHTML = `<tr><td colspan="5" class="text-danger small">${data.error || 'Erro ao listar rastreadores'}</td></tr>`;
    return;
  }

  if(!data.rows || data.rows.length === 0){
    tb.innerHTML = `<tr><td colspan="5" class="text-muted small">Nenhum rastreador vinculado.</td></tr>`;
    return;
  }

  data.rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${r.tipo_label || ''}</td>
      <td>${r.identificador || ''}</td>
      <td>${(String(r.ativo) === '1') ? 'Sim' : 'Não'}</td>
      <td>
        <button type="button" class="btn btn-outline-primary btn-sm me-1" data-act="edit" data-id="${r.id}">
          <i class="bi bi-pencil-square"></i> Editar
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" data-act="del" data-id="${r.id}">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;
    tb.appendChild(tr);
  });

  tb.querySelectorAll('button[data-act="edit"]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const res = await fetch(`veiculos_rastreador_list.php?action=get&id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
      const data = await res.json();
      if(!data.ok){ showMsgRast('danger', data.error || 'Erro ao carregar rastreador'); return; }
      const r = data.row;
      $('#rast_id').value = r.id || '';
      $('#tipo_rastreador_id').value = r.tipo_rastreador_id || '';
      $('#identificador').value = r.identificador || '';
      $('#rast_ativo').value = String(r.ativo ?? '1');
      clearRastErrors();
    });
  });

  tb.querySelectorAll('button[data-act="del"]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      if(!confirm('Excluir este rastreador?')) return;
      const res = await fetch('veiculos_rastreador_delete.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id})
      });
      const data = await res.json();
      if(!data.ok){ showMsgRast('danger', data.error || 'Erro ao excluir'); return; }
      showMsgRast('success','Rastreador excluído.');
      clearRastForm();
      await loadRastLista();
    });
  });
}

function validateRast(){
  clearRastErrors();

  const veiculoId = $('#id').value;
  if(!veiculoId){
    showMsgRast('warning','Salve o veículo primeiro (para gerar o ID) e depois vincule o rastreador.');
    return false;
  }

  const tipo = ($('#tipo_rastreador_id').value || '').trim();
  if(!tipo){
    setRastError('tipo_rastreador_id','Informe o Tipo Rastreador.');
    return false;
  }

  const ident = ($('#identificador').value || '').trim();
  if(!ident){
    setRastError('identificador','Informe o Identificador.');
    return false;
  }

  return true;
}

async function saveRast(){
  if(!validateRast()) return;

  const payload = {
    id: ($('#rast_id').value || '') ? Number($('#rast_id').value) : 0,
    veiculo_id: Number($('#id').value),
    tipo_rastreador_id: Number($('#tipo_rastreador_id').value),
    identificador: ($('#identificador').value || '').trim(),
    ativo: ($('#rast_ativo').value === '0') ? 0 : 1
  };

  const res = await fetch('veiculos_rastreador_save.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();

  if(!data.ok){
    showMsgRast('danger', data.error || 'Erro ao salvar rastreador');
    return;
  }

  showMsgRast('success','Rastreador salvo.');
  clearRastForm();
  await loadRastLista();
}

$('#btnRastNovo').addEventListener('click', clearRastForm);
$('#btnRastCancelar').addEventListener('click', clearRastForm);
$('#btnRastSalvar').addEventListener('click', saveRast);
$('#btnRastExcluir').addEventListener('click', async ()=>{
  const id = Number($('#rast_id').value || 0);
  if(!id){ showMsgRast('warning','Nenhum rastreador selecionado.'); return; }
  if(!confirm('Excluir este rastreador?')) return;
  const res = await fetch('veiculos_rastreador_delete.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  const data = await res.json();
  if(!data.ok){ showMsgRast('danger', data.error || 'Erro ao excluir'); return; }
  showMsgRast('success','Rastreador excluído.');
  clearRastForm();
  await loadRastLista();
});

window.addEventListener('load', ()=>{
  state.mode = 'view';
  setToolbar();
  loadLista();
  applyParceiroAuto();
});
</script>
</body>
</html>
