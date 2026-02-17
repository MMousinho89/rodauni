<?php
// =====================================================
// RODAUNI — Parceiros (Modelo União)
// - Debug controlado por querystring ?debug=1
// - Fallback amigável (evita tela branca)
// =====================================================

$debug = (isset($_GET['debug']) && $_GET['debug'] == '1');

if ($debug) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  ini_set('display_startup_errors', '0');
  error_reporting(E_ALL);
}

// Helper: falha amigável (evita branco)
function ru_fatal(string $title, string $details = ''): void {
  http_response_code(500);
  $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $safeDetails = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');
  echo "<!doctype html><html lang='pt-br'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>RODAUNI — Erro</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#e9ecef;margin:0;padding:24px;}
    .card{max-width:900px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:18px;}
    h1{font-size:18px;margin:0 0 10px;}
    pre{background:#111;color:#f5f5f5;padding:12px;border-radius:10px;overflow:auto;}
    .muted{color:#6b7280;font-size:13px;}
    a{color:#0d6efd;text-decoration:none;}
  </style></head><body>
    <div class='card'>
      <h1>⚠️ {$safeTitle}</h1>
      <div class='muted'>Se estiver em produção, abra com <b>?debug=1</b> para ver o erro detalhado (temporário).</div>
      ".($safeDetails ? "<pre>{$safeDetails}</pre>" : "")."
    </div>
  </body></html>";
  exit;
}

// Includes com checagem (se falhar, mostra erro ao invés de branco)
$authFile = __DIR__ . '/../includes/auth_check.php';
$dbFile   = __DIR__ . '/../config/database.php';
$sidebar  = __DIR__ . '/../partials/sidebar.php';

if (!file_exists($authFile)) ru_fatal("Arquivo ausente: auth_check.php", $authFile);
if (!file_exists($dbFile))   ru_fatal("Arquivo ausente: database.php", $dbFile);

require_once $authFile;
require_once $dbFile;

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RODAUNI — Parceiros</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/custom.css?v=<?= @filemtime(__DIR__ . '/../assets/css/custom.css') ?>">

  <style>
    .ru-page-title-line{display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem;}
    .ru-page-actions .btn{ font-size:.8rem; }
    .ru-tabcard .nav-tabs .nav-link{ font-size:.8rem; padding:.5rem .75rem; }
    .ru-field-label{ font-size:.75rem; font-weight:600; color:#6b7280; margin-bottom:.25rem; }
    .ru-audit{display:flex; flex-wrap:wrap; gap:.75rem; padding:.75rem 1rem; border-top:1px solid rgba(0,0,0,.08); background:#fff; position:sticky; bottom:0; z-index:5;}
    .ru-row-selected{outline:2px solid rgba(13,110,253,.35); background:rgba(13,110,253,.06);}
    .is-invalid-msg{ font-size:.75rem; color:#dc3545; margin-top:.25rem; }
    .ru-help-inline{ font-size:.8rem; color:#6b7280; margin-top:.25rem; }
    .ru-inline-input{display:flex; gap:2px; align-items:stretch;}
    .ru-inline-input .btn{ border-radius:.5rem; }

    .ru-editing .form-control:not([readonly]):not(:disabled),
    .ru-editing .form-select:not(:disabled),
    .ru-editing textarea.form-control:not(:disabled){
      background: #fff3cd;
      border-color: rgba(0,0,0,.18);
    }

    /* Debug badge opcional */
    .ru-debug-badge{
      font-size:12px; padding:.25rem .5rem; border:1px solid rgba(0,0,0,.12);
      border-radius:999px; background:#fff; color:#6b7280;
    }
  </style>
</head>

<body class="bg-light">
<div class="ru-app">
  <?php
    if (file_exists($sidebar)) {
      include $sidebar;
    } else {
      // sidebar faltando => não quebra a tela
      echo "<div style='padding:12px; margin:12px; background:#fff3cd; border:1px solid rgba(0,0,0,.12); border-radius:12px;'>
              <b>Atenção:</b> sidebar.php não encontrada em produção: ".htmlspecialchars($sidebar,ENT_QUOTES,'UTF-8')."
            </div>";
    }
  ?>

  <main class="ru-main">
    <div class="ru-page-title-line">
      <div>
        <div class="text-muted small">Cadastros</div>
        <h4 class="mb-0"><i class="bi bi-people me-1"></i>Parceiros</h4>
        <?php if ($debug): ?>
          <div class="mt-1"><span class="ru-debug-badge">DEBUG ATIVO (?debug=1)</span></div>
        <?php endif; ?>
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
        <ul class="nav nav-tabs border-bottom-0" id="tabsParceiros" role="tablist">
          <li class="nav-item"><button class="nav-link active" id="btnTabDados" data-bs-toggle="tab" data-bs-target="#tabDados" type="button">DADOS</button></li>
          <li class="nav-item"><button class="nav-link" id="btnTabLista" data-bs-toggle="tab" data-bs-target="#tabLista" type="button">LISTA</button></li>
        </ul>
      </div>

      <div class="card-body">
        <div id="msgBox" class="alert d-none" role="alert"></div>

        <form id="frmParceiro" autocomplete="off">
          <input type="hidden" name="id" id="id" value="">

          <div class="tab-content">
            <!-- ================= DADOS ================= -->
            <div class="tab-pane fade show active" id="tabDados" role="tabpanel">
              <div class="row g-3">
                <div class="col-md-2">
                  <label class="ru-field-label">Código</label>
                  <input type="text" class="form-control form-control-sm" id="id_view" value="" readonly>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Tipo Empresa</label>
                  <select class="form-select form-select-sm" id="tipo_empresa" name="tipo_empresa">
                    <option value="JURIDICA">JURÍDICA</option>
                    <option value="FISICA">FÍSICA</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Situação</label>
                  <select class="form-select form-select-sm" id="situacao" name="situacao">
                    <option value="ATIVO">ATIVO</option>
                    <option value="INATIVO">INATIVO</option>
                  </select>
                </div>

                <div class="col-md-4"></div>

                <div class="col-md-6">
                  <label class="ru-field-label">Filial (ID)</label>
                  <div class="ru-inline-input">
                    <input type="text" class="form-control form-control-sm" id="filial_id" name="filial_id" inputmode="numeric" placeholder="Digite o ID ou consulte...">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFilialConsultar">
                      <i class="bi bi-search me-1"></i>Consultar
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFilialPlus" title="Cadastro rápido">
                      <i class="bi bi-plus-lg"></i>
                    </button>
                  </div>
                  <div class="ru-help-inline" id="filial_label">—</div>
                  <div class="is-invalid-msg d-none" id="err_filial_id"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Classificação (obrigatório)</label>
                  <div class="ru-inline-input">
                    <input type="text" class="form-control form-control-sm" id="classificacao_id" name="classificacao_id" inputmode="numeric" placeholder="Digite o ID...">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClassConsultar">
                      <i class="bi bi-search me-1"></i>Consultar
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClassPlus" title="Cadastro rápido">
                      <i class="bi bi-plus-lg"></i>
                    </button>
                  </div>
                  <div class="ru-help-inline" id="classificacao_label">—</div>
                  <div class="is-invalid-msg d-none" id="err_classificacao_id"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Razão Social *</label>
                  <input type="text" class="form-control form-control-sm" id="razao_social" name="razao_social" maxlength="255">
                  <div class="is-invalid-msg d-none" id="err_razao_social"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Nome Fantasia</label>
                  <input type="text" class="form-control form-control-sm" id="nome_fantasia" name="nome_fantasia" maxlength="150">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Documento (CPF/CNPJ)</label>
                  <input type="text" class="form-control form-control-sm" id="documento" name="documento" maxlength="20" placeholder="CPF ou CNPJ">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">IE</label>
                  <input type="text" class="form-control form-control-sm" id="ie" name="ie" maxlength="30" placeholder="Somente números">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Sit. Fiscal</label>
                  <input type="text" class="form-control form-control-sm" id="sit_fiscal" name="sit_fiscal" maxlength="50">
                </div>

                <div class="col-md-3"></div>

                <hr class="my-2">

                <div class="col-md-3">
                  <label class="ru-field-label">CEP</label>
                  <input type="text" class="form-control form-control-sm" id="cep" name="cep" maxlength="9" placeholder="00000-000">
                  <div class="ru-help-inline">Digite e saia do campo para consultar.</div>
                  <div class="is-invalid-msg d-none" id="err_cep"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Endereço</label>
                  <input type="text" class="form-control form-control-sm" id="endereco" name="endereco" maxlength="255">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Número</label>
                  <input type="text" class="form-control form-control-sm" id="numero" name="numero" maxlength="20">
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">Bairro</label>
                  <input type="text" class="form-control form-control-sm" id="bairro" name="bairro" maxlength="100">
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">Município</label>
                  <input type="text" class="form-control form-control-sm" id="municipio" name="municipio" maxlength="150">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">UF</label>
                  <input type="text" class="form-control form-control-sm" id="uf" name="uf" maxlength="2" placeholder="SP">
                  <div class="is-invalid-msg d-none" id="err_uf"></div>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Cód. Município (IBGE)</label>
                  <input type="text" class="form-control form-control-sm" id="cod_municipio" name="cod_municipio" maxlength="10" readonly>
                  <div class="ru-help-inline">Preenchido automaticamente via CEP.</div>
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">Estado</label>
                  <input type="text" class="form-control form-control-sm" id="estado" name="estado" maxlength="100" readonly>
                  <div class="ru-help-inline">Preenchido automaticamente pela UF.</div>
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">País</label>
                  <input type="text" class="form-control form-control-sm" id="pais" name="pais" maxlength="100" value="BRASIL" readonly>
                </div>

                <div class="col-md-4"></div>

                <hr class="my-2">

                <div class="col-md-3">
                  <label class="ru-field-label">Telefone 1</label>
                  <input type="text" class="form-control form-control-sm" id="telefone1" name="telefone1" maxlength="20" placeholder="(00) 0000-0000">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Telefone 2</label>
                  <input type="text" class="form-control form-control-sm" id="telefone2" name="telefone2" maxlength="20" placeholder="(00) 0000-0000">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Celular</label>
                  <input type="text" class="form-control form-control-sm" id="celular" name="celular" maxlength="20" placeholder="(00) 00000-0000">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Ativo</label>
                  <select class="form-select form-select-sm" id="ativo" name="ativo">
                    <option value="1">SIM</option>
                    <option value="0">NÃO</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">E-mail</label>
                  <input type="email" class="form-control form-control-sm" id="email" name="email" maxlength="150">
                  <div class="is-invalid-msg d-none" id="err_email"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Observações</label>
                  <textarea class="form-control form-control-sm" id="observacoes" name="observacoes" rows="2"></textarea>
                </div>
              </div>
            </div>

            <!-- ================= LISTA ================= -->
            <div class="tab-pane fade" id="tabLista" role="tabpanel">
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-6">
                  <label class="ru-field-label">Buscar</label>
                  <input type="text" class="form-control form-control-sm" id="filtroLista" placeholder="Razão, Fantasia, CPF ou CNPJ...">
                </div>
                <div class="col-md-2">
                  <label class="ru-field-label">Ativo</label>
                  <select class="form-select form-select-sm" id="filtroAtivo">
                    <option value="">Todos</option>
                    <option value="1">Somente Ativos</option>
                    <option value="0">Somente Inativos</option>
                  </select>
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
                      <th>Razão Social</th>
                      <th>Fantasia</th>
                      <th style="width:180px;">Documento</th>
                      <th style="width:120px;">Ativo</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyLista">
                    <tr><td colspan="5" class="text-muted small">Carregando...</td></tr>
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
/* ===========================
   Helpers
=========================== */
const $ = (sel) => document.querySelector(sel);

function showMsg(type, text){
  const box = $('#msgBox');
  box.className = 'alert';
  box.classList.add('alert-' + type);
  box.textContent = text;
  box.classList.remove('d-none');
  setTimeout(()=> box.classList.add('d-none'), 4500);
}

function escapeHtml(str){
  return String(str ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function showTabDados(){
  const btn = document.getElementById('btnTabDados');
  if(!btn) return;
  bootstrap.Tab.getOrCreateInstance(btn).show();
  setTimeout(()=> bootstrap.Tab.getOrCreateInstance(btn).show(), 30);
}

/* ===========================
   STATE
=========================== */
const state = { mode:'view', selectedId:null, loadedId:null };

function setEditingClass(isEdit){ document.body.classList.toggle('ru-editing', !!isEdit); }

function setFormEnabled(enabled){
  const form = $('#frmParceiro');
  form.querySelectorAll('input, select, textarea').forEach(el=>{
    if(el.name === 'id') return;
    if(el.id === 'id_view') return;

    // LISTA sempre habilitada
    if(el.id === 'filtroLista' || el.id === 'filtroAtivo') return;
    if(el.closest('#tabLista')) return;

    el.disabled = !enabled;
  });

  // botões lookup só quando editando
  ['btnFilialConsultar','btnFilialPlus','btnClassConsultar','btnClassPlus'].forEach(id=>{
    const b = document.getElementById(id);
    if(b) b.disabled = !enabled;
  });
}

function setToolbar(){
  const hasId = !!($('#id').value || state.selectedId);
  const isEdit = (state.mode === 'edit' || state.mode === 'new');

  $('#btnIncluir').disabled  = isEdit;
  $('#btnEditar').disabled   = !hasId || isEdit;
  $('#btnSalvar').disabled   = !isEdit;
  $('#btnCancelar').disabled = !isEdit;
  $('#btnExcluir').disabled  = !($('#id').value) || isEdit;

  // LISTA sempre ativa
  $('#btnAtualizarLista').disabled = false;
  $('#filtroLista').disabled = false;
  $('#filtroAtivo').disabled = false;

  setFormEnabled(isEdit);
  setEditingClass(isEdit);
}

function clearInlineErrors(){
  ['razao_social','filial_id','classificacao_id','cep','uf','email'].forEach(id=>{
    const el = document.getElementById(id);
    const msg = document.getElementById('err_'+id);
    el?.classList.remove('is-invalid');
    if(msg){ msg.textContent=''; msg.classList.add('d-none'); }
  });
}
function setInlineError(fieldId, message){
  const el = document.getElementById(fieldId);
  const msg = document.getElementById('err_'+fieldId);
  el?.classList.add('is-invalid');
  if(msg){ msg.textContent = message; msg.classList.remove('d-none'); }
}

function clearForm(){
  $('#frmParceiro').reset();
  $('#id').value = '';
  $('#id_view').value = '';
  $('#pais').value = 'BRASIL';
  $('#estado').value = '';
  $('#cod_municipio').value = '';
  $('#auditCriado').textContent = 'Criado: —';
  $('#auditAtualizado').textContent = 'Atualizado: —';
  $('#filial_label').textContent = '—';
  $('#classificacao_label').textContent = '—';
  state.loadedId = null;
  clearInlineErrors();
}

/* ===========================
   Máscaras
=========================== */
const onlyDigits = (v) => String(v||'').replace(/\D/g,'');
function maskCep(v){
  const d = onlyDigits(v).slice(0,8);
  if(d.length <= 5) return d;
  return d.slice(0,5) + '-' + d.slice(5);
}
function maskCpf(v){
  const d = onlyDigits(v).slice(0,11);
  let s = d;
  if(d.length > 3) s = d.slice(0,3) + '.' + d.slice(3);
  if(d.length > 6) s = s.slice(0,7) + '.' + s.slice(7);
  if(d.length > 9) s = s.slice(0,11) + '-' + s.slice(11);
  return s;
}
function maskCnpj(v){
  const d = onlyDigits(v).slice(0,14);
  let s = d;
  if(d.length > 2)  s = d.slice(0,2) + '.' + d.slice(2);
  if(d.length > 5)  s = s.slice(0,6) + '.' + s.slice(6);
  if(d.length > 8)  s = s.slice(0,10) + '/' + s.slice(10);
  if(d.length > 12) s = s.slice(0,15) + '-' + s.slice(15);
  return s;
}
function maskDocByTipo(v){
  const tipo = String($('#tipo_empresa').value || 'JURIDICA').toUpperCase();
  return (tipo === 'FISICA') ? maskCpf(v) : maskCnpj(v);
}
function maskIE(v){
  const d = onlyDigits(v).slice(0,18);
  if(!d) return '';
  return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
function maskPhone(v){
  const d = onlyDigits(v).slice(0,11);
  if(!d) return '';
  if(d.length < 3) return d;
  const ddd = d.slice(0,2);
  const rest = d.slice(2);
  if(rest.length <= 8){
    const p1 = rest.slice(0,4);
    const p2 = rest.slice(4,8);
    return '('+ddd+') ' + p1 + (p2 ? '-' + p2 : '');
  } else {
    const p1 = rest.slice(0,5);
    const p2 = rest.slice(5,9);
    return '('+ddd+') ' + p1 + (p2 ? '-' + p2 : '');
  }
}
function applyMask(el, fn){
  if(!el) return;
  const pos = el.selectionStart;
  const before = el.value;
  const after = fn(before);
  el.value = after;
  try{
    const diff = after.length - before.length;
    const newPos = Math.max(0, (pos ?? after.length) + diff);
    el.setSelectionRange(newPos, newPos);
  }catch(e){}
}
function bindMask(el, fn){
  if(!el) return;
  el.addEventListener('input', ()=>{
    if(state.mode !== 'new' && state.mode !== 'edit') return;
    applyMask(el, fn);
  });
  el.addEventListener('blur', ()=>{
    if(state.mode !== 'new' && state.mode !== 'edit') return;
    applyMask(el, fn);
  });
}
function applyMasksOnLoad(){
  applyMask($('#documento'), maskDocByTipo);
  applyMask($('#ie'), maskIE);
  applyMask($('#cep'), maskCep);
  applyMask($('#telefone1'), maskPhone);
  applyMask($('#telefone2'), maskPhone);
  applyMask($('#celular'), maskPhone);
}

/* ===========================
   Validações
=========================== */
function normalizeUF(v){
  return String(v||'').trim().toUpperCase().replace(/[^A-Z]/g,'').slice(0,2);
}
function validateEmail(v){
  const s = String(v||'').trim();
  if(!s) return true;
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
  return re.test(s);
}
function validateForm(){
  clearInlineErrors();

  if(!($('#razao_social').value || '').trim()){
    setInlineError('razao_social','Razão Social é obrigatória.');
    return false;
  }
  const classId = ($('#classificacao_id').value || '').trim();
  if(!classId){
    setInlineError('classificacao_id','Classificação é obrigatória.');
    return false;
  }
  const email = ($('#email').value || '').trim();
  if(email && !validateEmail(email)){
    setInlineError('email','E-mail inválido.');
    return false;
  }
  const cep = ($('#cep').value || '').trim();
  if(cep){
    const d = onlyDigits(cep);
    if(d.length !== 8){
      setInlineError('cep','CEP inválido (use 8 dígitos).');
      return false;
    }
  }
  const uf = normalizeUF($('#uf').value);
  if(uf && uf.length !== 2){
    setInlineError('uf','UF inválida.');
    return false;
  }
  return true;
}

/* ===========================
   Form -> Object
=========================== */
function formDataToObject(form){
  const fd = new FormData(form);
  const obj = {};
  fd.forEach((v,k)=> obj[k] = v);

  if(obj.id !== undefined){
    const s = String(obj.id ?? '').trim();
    obj.id = (s === '') ? null : s;
  }

  if(obj.cep !== undefined) obj.cep = maskCep(obj.cep);
  if(obj.uf !== undefined) obj.uf = normalizeUF(obj.uf);
  if(obj.ie !== undefined) obj.ie = maskIE(obj.ie);
  ['telefone1','telefone2','celular'].forEach(k=>{
    if(obj[k] !== undefined) obj[k] = maskPhone(obj[k]);
  });

  if(obj.pais !== undefined){
    const p = String(obj.pais||'').trim();
    obj.pais = p ? p.toUpperCase() : 'BRASIL';
  }

  ['filial_id','classificacao_id'].forEach(k=>{
    if(obj[k] !== undefined){
      const s = String(obj[k] ?? '').trim();
      obj[k] = (s === '') ? null : s;
    }
  });

  const tipo = String(obj.tipo_empresa || 'JURIDICA').toUpperCase();
  const docMasked = maskDocByTipo(obj.documento || '');

  if(tipo === 'FISICA'){
    obj.cpf = maskCpf(docMasked);
    obj.cnpj = null;
  } else {
    obj.cnpj = maskCnpj(docMasked);
    obj.cpf = null;
  }

  delete obj.documento;
  return obj;
}

/* ===========================
   Lookup Labels
=========================== */
async function lookupParceiros(field, value){
  const url = new URL('parceiros_lookup.php', window.location.href);
  url.searchParams.set('field', field);
  url.searchParams.set('value', value);
  const res = await fetch(url.toString(), { credentials:'same-origin' });
  return await res.json();
}
async function refreshLookupLabels(){
  const f = ($('#filial_id').value || '').trim();
  if(f){
    const j = await lookupParceiros('filial_id', f);
    $('#filial_label').textContent = j.ok ? (j.label || ('#'+f)) : '—';
  } else $('#filial_label').textContent = '—';

  const c = ($('#classificacao_id').value || '').trim();
  if(c){
    const j = await lookupParceiros('classificacao_id', c);
    $('#classificacao_label').textContent = j.ok ? (j.label || ('#'+c)) : '—';
  } else $('#classificacao_label').textContent = '—';
}

window.addEventListener('message', (ev)=>{
  if(!ev?.data || ev.data.type !== 'ru_lookup') return;
  const field = ev.data.field;
  const id = ev.data.id;
  const label = ev.data.label;

  const el = document.getElementById(field);
  if(el){
    el.value = String(id || '');
    el.classList.remove('is-invalid');
  }

  if(field === 'filial_id') $('#filial_label').textContent = label || (id ? ('#'+id) : '—');
  if(field === 'classificacao_id') $('#classificacao_label').textContent = label || (id ? ('#'+id) : '—');

  refreshLookupLabels();
});

function openCenteredPopup(url, name, w=980, h=720){
  const left = Math.max(0, (window.screen.width - w) / 2);
  const top  = Math.max(0, (window.screen.height - h) / 2);
  const opts = `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`;
  return window.open(url, name, opts);
}

/* Filial */
$('#btnFilialConsultar').addEventListener('click', () => {
  if(state.mode !== 'new' && state.mode !== 'edit') return;
  openCenteredPopup(`filiais_lookup_popup.php?field=filial_id`, 'lookup_filial', 980, 720);
});
$('#btnFilialPlus').addEventListener('click', () => {
  if(state.mode !== 'new' && state.mode !== 'edit') return;
  openCenteredPopup(`filiais.php?popup=1&returnField=filial_id`, 'cad_filial_popup', 1100, 780);
});

/* Classificação */
$('#btnClassConsultar').addEventListener('click', () => {
  if(state.mode !== 'new' && state.mode !== 'edit') return;
  openCenteredPopup(`classificacoes_lookup_popup.php?field=classificacao_id`, 'lookup_classificacao', 980, 720);
});
$('#btnClassPlus').addEventListener('click', () => {
  if(state.mode !== 'new' && state.mode !== 'edit') return;
  openCenteredPopup(`cad_classificacoes.php?popup=1&returnField=classificacao_id`, 'cad_class_popup', 1100, 780);
});

$('#filial_id').addEventListener('blur', ()=> refreshLookupLabels());
$('#classificacao_id').addEventListener('blur', ()=> refreshLookupLabels());

/* ===========================
   CEP / UF
=========================== */
async function fillEstadoByUF(ufRaw){
  const uf = normalizeUF(ufRaw);
  $('#uf').value = uf;

  if(!uf || uf.length !== 2){
    $('#estado').value = '';
    $('#pais').value = 'BRASIL';
    return;
  }

  try{
    const url = new URL('estados_lookup.php', window.location.href);
    url.searchParams.set('uf', uf);
    const res = await fetch(url.toString(), { credentials:'same-origin' });
    const j = await res.json();

    if(j.ok && j.row){
      $('#estado').value = j.row.nome ?? '';
      $('#pais').value = j.row.pais ?? 'BRASIL';
      return;
    }
  }catch(e){}
  $('#estado').value = '';
  $('#pais').value = 'BRASIL';
}

async function consultarViaCep(){
  applyMask($('#cep'), maskCep);
  const d = onlyDigits($('#cep').value);
  if(d.length !== 8) return;

  try{
    const res = await fetch(`https://viacep.com.br/ws/${d}/json/`);
    const j = await res.json();

    if(j.erro){
      setInlineError('cep','CEP não encontrado.');
      return;
    }

    if(j.logradouro) $('#endereco').value = j.logradouro;
    if(j.bairro) $('#bairro').value = j.bairro;
    if(j.localidade) $('#municipio').value = j.localidade;

    if(j.uf){
      $('#uf').value = normalizeUF(j.uf);
      await fillEstadoByUF(j.uf);
    }

    if(j.ibge) $('#cod_municipio').value = j.ibge;
    $('#pais').value = 'BRASIL';
  }catch(e){}
}

$('#cep').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit') consultarViaCep();
});
$('#uf').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#uf').value = normalizeUF($('#uf').value);
    fillEstadoByUF($('#uf').value);
  }
});

/* ===========================
   LISTA / GET / SAVE / DELETE
=========================== */
function highlightSelectedRow(){
  document.querySelectorAll('#tbodyLista tr').forEach(tr=> tr.classList.remove('ru-row-selected'));
  if(state.selectedId == null) return;
  const tr = document.querySelector(`#tbodyLista tr[data-id="${state.selectedId}"]`);
  if(tr) tr.classList.add('ru-row-selected');
}

async function loadParceiro(id, options={switchTab:true, silent:false}){
  const url = new URL('parceiros_save.php', window.location.href);
  url.searchParams.set('action','get');
  url.searchParams.set('id', id);

  const res = await fetch(url.toString(), { credentials:'same-origin' });
  const data = await res.json();

  if(!data.ok){
    if(!options.silent) showMsg('danger', data.error || 'Erro ao carregar');
    return;
  }

  const v = data.row || {};
  $('#id').value = v.id || '';
  $('#id_view').value = v.id || '';
  state.loadedId = v.id || null;

  for(const k in v){
    const el = document.getElementById(k);
    if(el && el.name){
      el.value = (v[k] ?? '');
    }
  }

  applyMasksOnLoad();

  $('#auditCriado').textContent = `Criado: ${(v.criado_em || '—')} por ${(v.criado_por || '—')}`;
  $('#auditAtualizado').textContent = `Atualizado: ${(v.atualizado_em || '—')} por ${(v.atualizado_por || '—')}`;

  await refreshLookupLabels();

  if(options.switchTab){
    showTabDados();
  }
}

async function loadLista(){
  const filtro = ($('#filtroLista').value || '').trim();
  const ativo = ($('#filtroAtivo').value || '');

  const url = new URL('parceiros_save.php', window.location.href);
  url.searchParams.set('action','list');
  if(filtro) url.searchParams.set('f', filtro);
  if(ativo !== '') url.searchParams.set('ativo', ativo);

  const res = await fetch(url.toString(), { credentials:'same-origin' });
  const data = await res.json();

  const tb = $('#tbodyLista');
  tb.innerHTML = '';

  if(!data.ok){
    tb.innerHTML = `<tr><td colspan="5" class="text-danger small">${escapeHtml(data.error || 'Erro ao listar')}</td></tr>`;
    return;
  }

  if(!data.rows || data.rows.length === 0){
    tb.innerHTML = `<tr><td colspan="5" class="text-muted small">Nenhum registro.</td></tr>`;
    return;
  }

  data.rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.dataset.id = r.id;
    tr.style.cursor = 'pointer';

    const ativoTxt = String(r.ativo) === '1' ? 'SIM' : 'NÃO';

    tr.innerHTML = `
      <td>${r.id}</td>
      <td><strong>${escapeHtml(r.razao_social || '')}</strong></td>
      <td>${escapeHtml(r.nome_fantasia || '')}</td>
      <td>${escapeHtml(r.documento || '')}</td>
      <td>${ativoTxt}</td>
    `;

    let clickTimer = null;

    tr.addEventListener('click', () => {
      clearTimeout(clickTimer);
      clickTimer = setTimeout(async () => {
        state.selectedId = parseInt(tr.dataset.id, 10) || 0;
        highlightSelectedRow();
        setToolbar();
        await loadParceiro(state.selectedId, { switchTab:false, silent:true });
      }, 220);
    });

    tr.addEventListener('dblclick', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      clearTimeout(clickTimer);

      state.selectedId = parseInt(tr.dataset.id, 10) || 0;
      highlightSelectedRow();
      setToolbar();

      showTabDados();
      await loadParceiro(state.selectedId, { switchTab:true, silent:true });

      state.mode = 'view';
      setToolbar();
    });

    tb.appendChild(tr);
  });

  highlightSelectedRow();
}

async function saveParceiro(){
  if(!validateForm()) return;

  const obj = formDataToObject($('#frmParceiro'));

  const res = await fetch('parceiros_save.php', {
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

  await loadParceiro(data.id, { switchTab:true, silent:true });
  await loadLista();

  state.mode = 'view';
  setToolbar();
}

async function deleteParceiro(){
  const id = $('#id').value;
  if(!id) return;
  if(!confirm('Confirma excluir este parceiro?')) return;

  const res = await fetch('parceiros_delete.php', {
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

/* Toolbar */
$('#btnIncluir').addEventListener('click', ()=>{
  clearForm();
  state.selectedId = null;
  state.mode = 'new';
  setToolbar();
  showTabDados();
  applyMasksOnLoad();
});

$('#btnEditar').addEventListener('click', async ()=>{
  const idAtual = $('#id').value;
  const idSelecionado = state.selectedId;
  const idParaEditar = idAtual || idSelecionado;
  if(!idParaEditar) return;

  if(!idAtual || String(idAtual) !== String(idParaEditar)){
    await loadParceiro(idParaEditar, { switchTab:false, silent:true });
  }

  state.mode = 'edit';
  setToolbar();
  showTabDados();
  applyMasksOnLoad();
});

$('#btnCancelar').addEventListener('click', async ()=>{
  state.mode = 'view';
  setToolbar();

  if($('#id').value){
    await loadParceiro($('#id').value, { switchTab:true, silent:true });
  } else {
    clearForm();
  }
});

$('#btnSalvar').addEventListener('click', saveParceiro);
$('#btnExcluir').addEventListener('click', deleteParceiro);

$('#btnAtualizarLista').addEventListener('click', loadLista);
$('#filtroLista').addEventListener('keydown', (e)=>{
  if(e.key === 'Enter'){ e.preventDefault(); loadLista(); }
});
$('#filtroAtivo').addEventListener('change', loadLista);

/* máscaras */
bindMask($('#cep'), maskCep);
bindMask($('#ie'), maskIE);
bindMask($('#telefone1'), maskPhone);
bindMask($('#telefone2'), maskPhone);
bindMask($('#celular'), maskPhone);
bindMask($('#documento'), maskDocByTipo);

$('#tipo_empresa').addEventListener('change', ()=>{
  if(state.mode !== 'new' && state.mode !== 'edit') return;
  applyMask($('#documento'), maskDocByTipo);
});

/* init */
window.addEventListener('load', async ()=>{
  state.mode = 'view';
  setToolbar();
  await loadLista();
  await refreshLookupLabels();
});
</script>
</body>
</html>
