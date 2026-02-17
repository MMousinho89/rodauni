<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';

$popup = (int)($_GET['popup'] ?? 0);
$returnField = preg_replace('/[^a-zA-Z0-9_]/','', (string)($_GET['returnField'] ?? 'classificacao_id'));
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RODAUNI — Classificações</title>

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
  </style>
</head>

<body class="bg-light">
<div class="ru-app">
  <?php if(!$popup){ include __DIR__ . '/../partials/sidebar.php'; } ?>

  <main class="<?= $popup ? 'container-fluid py-3' : 'ru-main' ?>">
    <div class="ru-page-title-line">
      <div>
        <div class="text-muted small">Cadastros</div>
        <h4 class="mb-0"><i class="bi bi-tags me-1"></i>Classificações</h4>
      </div>

      <div class="ru-page-actions d-flex flex-wrap gap-2">
        <button id="btnIncluir" type="button" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Incluir</button>
        <button id="btnEditar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Editar</button>
        <button id="btnSalvar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar</button>
        <button id="btnCancelar"type="button" class="btn btn-warning btn-sm text-dark"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button id="btnExcluir" type="button" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Excluir</button>

        <?php if($popup): ?>
          <button type="button" class="btn btn-dark btn-sm" onclick="window.close()"><i class="bi bi-x-lg me-1"></i>Fechar</button>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-dark btn-sm"><i class="bi bi-arrow-left-circle me-1"></i>Voltar</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm border-0 ru-tabcard">
      <div class="card-header bg-white border-0 pb-0">
        <ul class="nav nav-tabs border-bottom-0">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDados" type="button">DADOS</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLista" type="button">LISTA</button></li>
        </ul>
      </div>

      <div class="card-body">
        <div id="msgBox" class="alert d-none" role="alert"></div>

        <form id="frm" autocomplete="off">
          <input type="hidden" name="id" id="id" value="">
          <div class="tab-content">
            <div class="tab-pane fade show active" id="tabDados" role="tabpanel">
              <div class="row g-3">
                <div class="col-md-2">
                  <label class="ru-field-label">Código</label>
                  <input type="text" class="form-control form-control-sm" id="id_view" value="" readonly>
                </div>

                <div class="col-md-7">
                  <label class="ru-field-label">Descrição *</label>
                  <input type="text" class="form-control form-control-sm" id="descricao" name="descricao" maxlength="150">
                  <div class="is-invalid-msg d-none" id="err_descricao"></div>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Ativo</label>
                  <select class="form-select form-select-sm" id="ativo" name="ativo">
                    <option value="1">SIM</option>
                    <option value="0">NÃO</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="tabLista" role="tabpanel">
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-8">
                  <label class="ru-field-label">Buscar</label>
                  <input type="text" class="form-control form-control-sm" id="filtroLista" placeholder="ID ou descrição...">
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
                      <th style="width:90px;">ID</th>
                      <th>Descrição</th>
                      <th style="width:110px;">Ativo</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyLista">
                    <tr><td colspan="3" class="text-muted small">Carregando...</td></tr>
                  </tbody>
                </table>
              </div>

              <?php if($popup): ?>
                <div class="text-muted small">Clique para selecionar. <strong>Duplo clique</strong> seleciona e retorna.</div>
              <?php else: ?>
                <div class="text-muted small">Clique para selecionar. Duplo clique abre o cadastro.</div>
              <?php endif; ?>
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
const POPUP = <?= (int)$popup ?>;
const RETURN_FIELD = <?= json_encode($returnField) ?>;

const $ = (s)=>document.querySelector(s);

function showMsg(type, text){
  const box = $('#msgBox');
  box.className = 'alert';
  box.classList.add('alert-' + type);
  box.textContent = text;
  box.classList.remove('d-none');
  setTimeout(()=> box.classList.add('d-none'), 4500);
}
function showTab(tabId){
  const btn = document.querySelector(`button[data-bs-target="#${tabId}"]`);
  if(!btn) return;
  new bootstrap.Tab(btn).show();
}
function clearInlineErrors(){
  const el = $('#descricao');
  const msg = $('#err_descricao');
  el?.classList.remove('is-invalid');
  if(msg){ msg.textContent=''; msg.classList.add('d-none'); }
}
function setInlineError(message){
  const el = $('#descricao');
  const msg = $('#err_descricao');
  el?.classList.add('is-invalid');
  if(msg){ msg.textContent = message; msg.classList.remove('d-none'); }
}

const state = { mode:'view', selectedId:null, loadedId:null };

function setFormEnabled(enabled){
  const form = $('#frm');
  form.querySelectorAll('input, select, textarea').forEach(el=>{
    if(el.name === 'id') return;
    if(el.id === 'id_view') return;
    if(el.id === 'filtroLista' || el.id === 'filtroAtivo') return;
    if(el.closest('#tabLista')) return;
    el.disabled = !enabled;
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

  $('#btnAtualizarLista').disabled = false;
  $('#filtroLista').disabled = false;
  $('#filtroAtivo').disabled = false;

  setFormEnabled(isEdit);
}

function clearForm(){
  $('#frm').reset();
  $('#id').value = '';
  $('#id_view').value = '';
  $('#auditCriado').textContent = 'Criado: —';
  $('#auditAtualizado').textContent = 'Atualizado: —';
  state.loadedId = null;
  clearInlineErrors();
}

function validateForm(){
  clearInlineErrors();
  if(!($('#descricao').value || '').trim()){
    setInlineError('Descrição é obrigatória.');
    return false;
  }
  return true;
}

function formObj(){
  return {
    id: ($('#id').value || '').trim() ? $('#id').value : null,
    descricao: ($('#descricao').value || '').trim(),
    ativo: ($('#ativo').value === '0') ? 0 : 1
  };
}

function highlightSelectedRow(){
  document.querySelectorAll('#tbodyLista tr').forEach(tr=> tr.classList.remove('ru-row-selected'));
  if(state.selectedId == null) return;
  const tr = document.querySelector(`#tbodyLista tr[data-id="${state.selectedId}"]`);
  if(tr) tr.classList.add('ru-row-selected');
}

async function loadLista(){
  const f = ($('#filtroLista').value || '').trim();
  const ativo = ($('#filtroAtivo').value || '');

  const url = new URL('cad_classificacoes_save.php', window.location.href);
  url.searchParams.set('action','list');
  if(f) url.searchParams.set('f', f);
  if(ativo !== '') url.searchParams.set('ativo', ativo);

  const res = await fetch(url.toString(), { credentials:'same-origin' });
  const data = await res.json();

  const tb = $('#tbodyLista');
  tb.innerHTML = '';

  if(!data.ok){
    tb.innerHTML = `<tr><td colspan="3" class="text-danger small">${data.error || 'Erro ao listar'}</td></tr>`;
    return;
  }
  if(!data.rows || data.rows.length === 0){
    tb.innerHTML = `<tr><td colspan="3" class="text-muted small">Nenhum registro.</td></tr>`;
    return;
  }

  data.rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.dataset.id = r.id;
    tr.dataset.label = r.descricao || ('#'+r.id);
    tr.style.cursor = 'pointer';
    tr.innerHTML = `
      <td>${r.id}</td>
      <td><strong>${(r.descricao||'')}</strong></td>
      <td>${String(r.ativo)==='1' ? 'SIM' : 'NÃO'}</td>
    `;

    let clickTimer = null;

    tr.addEventListener('click', ()=>{
      clearTimeout(clickTimer);
      clickTimer = setTimeout(async ()=>{
        state.selectedId = parseInt(tr.dataset.id,10) || 0;
        highlightSelectedRow();
        setToolbar();
        await loadRegistro(state.selectedId, { switchTab:false, silent:true });
      }, 220);
    });

    tr.addEventListener('dblclick', async ()=>{
      clearTimeout(clickTimer);
      state.selectedId = parseInt(tr.dataset.id,10) || 0;
      highlightSelectedRow();
      setToolbar();

      if(POPUP){
        // retorna pro opener
        const id = tr.dataset.id;
        const label = tr.dataset.label || ('#'+id);
        if(window.opener && !window.opener.closed){
          window.opener.postMessage({ type:'ru_lookup', field: RETURN_FIELD, id: id, label: label }, '*');
        }
        window.close();
        return;
      }

      await loadRegistro(state.selectedId, { switchTab:true, silent:true });
      showTab('tabDados');
      state.mode = 'view';
      setToolbar();
    });

    tb.appendChild(tr);
  });

  highlightSelectedRow();
}

async function loadRegistro(id, options={switchTab:true, silent:false}){
  const url = new URL('cad_classificacoes_save.php', window.location.href);
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
  $('#descricao').value = v.descricao || '';
  $('#ativo').value = (String(v.ativo) === '0') ? '0' : '1';

  $('#auditCriado').textContent = `Criado: ${(v.criado_em || '—')} por ${(v.criado_por || '—')}`;
  $('#auditAtualizado').textContent = `Atualizado: ${(v.atualizado_em || '—')} por ${(v.atualizado_por || '—')}`;

  if(options.switchTab) showTab('tabDados');
}

async function salvar(){
  if(!validateForm()) return;

  const res = await fetch('cad_classificacoes_save.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(formObj())
  });
  const data = await res.json();
  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao salvar');
    return;
  }

  showMsg('success','Salvo com sucesso.');
  state.selectedId = data.id;
  await loadRegistro(data.id, { switchTab:true, silent:true });
  await loadLista();
  state.mode = 'view';
  setToolbar();
}

async function excluir(){
  const id = ($('#id').value || '').trim();
  if(!id) return;
  if(!confirm('Confirma excluir esta classificação?')) return;

  const res = await fetch('cad_classificacoes_delete.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ id })
  });
  const data = await res.json();
  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao excluir');
    return;
  }

  showMsg('success','Excluída com sucesso.');
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
  showTab('tabDados');
});

$('#btnEditar').addEventListener('click', async ()=>{
  const idAtual = $('#id').value;
  const idSel = state.selectedId;
  const idParaEditar = idAtual || idSel;
  if(!idParaEditar) return;

  if(!idAtual || String(idAtual) !== String(idParaEditar)){
    await loadRegistro(idParaEditar, { switchTab:false, silent:true });
  }
  state.mode = 'edit';
  setToolbar();
  showTab('tabDados');
});

$('#btnCancelar').addEventListener('click', async ()=>{
  state.mode = 'view';
  setToolbar();
  if($('#id').value) await loadRegistro($('#id').value, { switchTab:true, silent:true });
  else clearForm();
});

$('#btnSalvar').addEventListener('click', salvar);
$('#btnExcluir').addEventListener('click', excluir);

$('#btnAtualizarLista').addEventListener('click', loadLista);
$('#filtroLista').addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); loadLista(); } });
$('#filtroAtivo').addEventListener('change', loadLista);

window.addEventListener('load', async ()=>{
  state.mode='view';
  setToolbar();
  await loadLista();
});
</script>
</body>
</html>
