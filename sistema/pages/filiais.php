<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';

$isPopup = isset($_GET['popup']) && $_GET['popup'] == '1';

/**
 * IMPORTANTE:
 * - Parceiros abre: filiais.php?popup=1&returnField=filial_id
 * - Então aqui tem que ler returnField (não "field")
 */
$returnField = $_GET['returnField'] ?? ($_GET['field'] ?? 'filial_id');
$returnField = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$returnField);
if ($returnField === '') $returnField = 'filial_id';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RODAUNI — Filiais</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/custom.css?v=<?= @filemtime(__DIR__ . '/../assets/css/custom.css') ?>">

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
    .ru-row-selected{
      outline: 2px solid rgba(13,110,253,.35);
      background: rgba(13,110,253,.06);
    }
    .is-invalid-msg{ font-size:.75rem; color:#dc3545; margin-top:.25rem; }
    .ru-help{ font-size:.75rem; color:#6b7280; margin-top:.25rem; }
  </style>
</head>

<body class="bg-light">
<div class="ru-app">
  <?php if(!$isPopup): ?>
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <?php endif; ?>

  <main class="ru-main" style="<?= $isPopup ? 'margin-left:0!important;' : '' ?>">
    <div class="ru-page-title-line">
      <div>
        <div class="text-muted small">Cadastros</div>
        <h4 class="mb-0"><i class="bi bi-diagram-3 me-1"></i>Filiais</h4>
        <?php if($isPopup): ?>
          <div class="text-muted small">Modo popup — ao salvar, retorna para a tela anterior.</div>
        <?php endif; ?>
      </div>

      <div class="ru-page-actions d-flex flex-wrap gap-2">
        <button id="btnIncluir" type="button" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Incluir</button>
        <button id="btnEditar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Editar</button>
        <button id="btnSalvar"  type="button" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar</button>
        <button id="btnCancelar"type="button" class="btn btn-warning btn-sm text-dark"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button id="btnExcluir" type="button" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Excluir</button>

        <?php if($isPopup): ?>
          <button type="button" class="btn btn-dark btn-sm" onclick="window.close()"><i class="bi bi-x-lg me-1"></i>Fechar</button>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-dark btn-sm"><i class="bi bi-arrow-left-circle me-1"></i>Voltar</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm border-0 ru-tabcard">
      <div class="card-header bg-white border-0 pb-0">
        <ul class="nav nav-tabs border-bottom-0" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDados" type="button">DADOS</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLista" type="button">LISTA</button></li>
        </ul>
      </div>

      <div class="card-body">
        <div id="msgBox" class="alert d-none" role="alert"></div>

        <form id="frmFilial" autocomplete="off">
          <input type="hidden" name="id" id="id" value="">

          <div class="tab-content">
            <!-- ================= DADOS ================= -->
            <div class="tab-pane fade show active" id="tabDados" role="tabpanel">
              <div class="row g-3">

                <div class="col-md-2">
                  <label class="ru-field-label">Código</label>
                  <input type="text" class="form-control form-control-sm" id="id_view" value="" readonly>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Nome da Filial *</label>
                  <input type="text" class="form-control form-control-sm" name="nome_filial" id="nome_filial" maxlength="100">
                  <div class="is-invalid-msg d-none" id="err_nome_filial"></div>
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Ativo</label>
                  <select class="form-select form-select-sm" name="ativo" id="ativo">
                    <option value="1">SIM</option>
                    <option value="0">NÃO</option>
                  </select>
                </div>

                <div class="col-md-2"></div>

                <hr class="my-2">

                <!-- ===== DADOS EMPRESA ===== -->
                <div class="col-md-6">
                  <label class="ru-field-label">Razão Social</label>
                  <input type="text" class="form-control form-control-sm" name="razao_social" id="razao_social" maxlength="150" placeholder="UNIÃO PIRES TRANSPORTES LTDA">
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Nome Fantasia</label>
                  <input type="text" class="form-control form-control-sm" name="nome_fantasia" id="nome_fantasia" maxlength="150" placeholder="UNIÃO PIRES">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">CNPJ</label>
                  <input type="text" class="form-control form-control-sm" name="cnpj" id="cnpj" maxlength="18" placeholder="00.000.000/0000-00">
                  <div class="is-invalid-msg d-none" id="err_cnpj"></div>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Inscrição Estadual</label>
                  <input type="text" class="form-control form-control-sm" name="inscricao_estadual" id="inscricao_estadual" maxlength="30" placeholder="ISENTO">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Inscrição Municipal</label>
                  <input type="text" class="form-control form-control-sm" name="inscricao_municipal" id="inscricao_municipal" maxlength="30">
                </div>

                <div class="col-md-3"></div>

                <hr class="my-2">

                <!-- ===== ENDEREÇO ===== -->
                <div class="col-md-2">
                  <label class="ru-field-label">CEP</label>
                  <input type="text" class="form-control form-control-sm" name="cep" id="cep" maxlength="9" placeholder="00000-000">
                  <div class="ru-help">Digite e saia do campo para consultar.</div>
                  <div class="is-invalid-msg d-none" id="err_cep"></div>
                </div>

                <div class="col-md-6">
                  <label class="ru-field-label">Endereço</label>
                  <input type="text" class="form-control form-control-sm" name="endereco" id="endereco" maxlength="150">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Número</label>
                  <input type="text" class="form-control form-control-sm" name="numero" id="numero" maxlength="10">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">Complemento</label>
                  <input type="text" class="form-control form-control-sm" name="complemento" id="complemento" maxlength="80">
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">Bairro</label>
                  <input type="text" class="form-control form-control-sm" name="bairro" id="bairro" maxlength="100">
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Município</label>
                  <input type="text" class="form-control form-control-sm" name="municipio" id="municipio" maxlength="100">
                </div>

                <div class="col-md-2">
                  <label class="ru-field-label">UF</label>
                  <input type="text" class="form-control form-control-sm" name="uf" id="uf" maxlength="2" placeholder="SP">
                  <div class="is-invalid-msg d-none" id="err_uf"></div>
                </div>

                <div class="col-md-3">
                  <label class="ru-field-label">Cód. Município (IBGE)</label>
                  <input type="text" class="form-control form-control-sm" name="cod_municipio" id="cod_municipio" maxlength="10" readonly>
                  <div class="ru-help">Preenchido automaticamente via CEP.</div>
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">Estado</label>
                  <input type="text" class="form-control form-control-sm" name="estado" id="estado" maxlength="100" readonly>
                  <div class="ru-help">Preenchido automaticamente pela UF.</div>
                </div>

                <div class="col-md-4">
                  <label class="ru-field-label">País</label>
                  <input type="text" class="form-control form-control-sm" name="pais" id="pais" maxlength="60" value="BRASIL" readonly>
                </div>

                <div class="col-md-4"></div>

                <hr class="my-2">

                <div class="col-md-3">
                  <label class="ru-field-label">Telefone</label>
                  <input type="text" class="form-control form-control-sm" name="telefone1" id="telefone1" maxlength="20" placeholder="(00) 00000-0000">
                </div>

                <div class="col-md-5">
                  <label class="ru-field-label">E-mail</label>
                  <input type="email" class="form-control form-control-sm" name="email" id="email" maxlength="120" placeholder="contato@empresa.com">
                  <div class="is-invalid-msg d-none" id="err_email"></div>
                </div>

                <div class="col-md-4"></div>

              </div>
            </div>

            <!-- ================= LISTA ================= -->
            <div class="tab-pane fade" id="tabLista" role="tabpanel">
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-6">
                  <label class="ru-field-label">Buscar</label>
                  <input type="text" class="form-control form-control-sm" id="filtroLista" placeholder="Nome, CNPJ, Município...">
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
                      <th>Nome</th>
                      <th style="width:170px;">CNPJ</th>
                      <th style="width:180px;">Cidade/UF</th>
                      <th style="width:90px;">Ativo</th>
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
const IS_POPUP = <?= $isPopup ? 'true' : 'false' ?>;
const RETURN_FIELD = <?= json_encode($returnField) ?>;

const $ = (sel) => document.querySelector(sel);
const state = { mode:'view', selectedId:null, loadedId:null };

function showTab(tabId){
  const btn = document.querySelector(`button[data-bs-target="#${tabId}"]`);
  if(!btn) return;
  new bootstrap.Tab(btn).show();
}

function showMsg(type, text){
  const box = $('#msgBox');
  box.className = 'alert';
  box.classList.add('alert-' + type);
  box.textContent = text;
  box.classList.remove('d-none');
  setTimeout(()=> box.classList.add('d-none'), 4500);
}

function clearInlineErrors(){
  ['nome_filial','cep','email','uf','cnpj'].forEach(id=>{
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

function setFormEnabled(enabled){
  const form = $('#frmFilial');
  form.querySelectorAll('input, select, textarea').forEach(el=>{
    if(el.name === 'id') return;
    if(el.id === 'id_view') return;

    // LISTA sempre habilitada
    if(el.id === 'filtroLista') return;
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

  setFormEnabled(isEdit);
}

function clearForm(){
  $('#frmFilial').reset();
  $('#id').value = '';
  $('#id_view').value = '';
  $('#pais').value = 'BRASIL';
  $('#auditCriado').textContent = 'Criado: —';
  $('#auditAtualizado').textContent = 'Atualizado: —';
  state.loadedId = null;
  clearInlineErrors();
}

function normalizeCep(v){
  const d = String(v||'').replace(/\D/g,'').slice(0,8);
  if(d.length === 8) return d.slice(0,5)+'-'+d.slice(5);
  return d;
}

function normalizeUF(v){
  return String(v||'').trim().toUpperCase().replace(/[^A-Z]/g,'').slice(0,2);
}

function digitsOnly(v){ return String(v||'').replace(/\D/g,''); }

function maskCnpj(v){
  const d = digitsOnly(v).slice(0,14);
  if(d.length <= 2) return d;
  if(d.length <= 5) return d.slice(0,2)+'.'+d.slice(2);
  if(d.length <= 8) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
  if(d.length <= 12) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
  return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
}

/* =========================
   MÁSCARAS (CEP, IE, TELEFONE)
   ========================= */
function maskCep(v){
  const d = digitsOnly(v).slice(0,8);
  if(d.length <= 5) return d;
  return d.slice(0,5) + '-' + d.slice(5);
}

function maskIE(v){
  let s = String(v||'').trim().toUpperCase();
  if(s === '') return '';
  if(s === 'ISENTO') return 'ISENTO';
  s = s.replace(/[^A-Z0-9.\-\/ ]+/g, '');
  s = s.replace(/\s+/g, ' ');
  return s.slice(0,30);
}

function maskTelefone(v){
  const d = digitsOnly(v).slice(0,11);
  if(d.length === 0) return '';
  if(d.length <= 2) return '(' + d;
  if(d.length <= 6) return '(' + d.slice(0,2) + ') ' + d.slice(2);
  if(d.length <= 10){
    return '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6);
  }
  return '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
}

function validateEmail(v){
  const s = String(v||'').trim();
  if(!s) return true;
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
  return re.test(s);
}

function isValidCnpj(cnpjRaw){
  const cnpj = digitsOnly(cnpjRaw);
  if(cnpj.length === 0) return true; // vazio permitido
  if(cnpj.length !== 14) return false;
  if(/^(\d)\1+$/.test(cnpj)) return false;

  const calcDv = (base) => {
    let sum = 0;
    let pos = base.length - 7;
    for(let i = base.length; i >= 1; i--){
      sum += parseInt(base.charAt(base.length - i), 10) * pos--;
      if(pos < 2) pos = 9;
    }
    const mod = sum % 11;
    return (mod < 2) ? 0 : 11 - mod;
  };

  const base12 = cnpj.slice(0,12);
  const dv1 = calcDv(base12);
  const dv2 = calcDv(base12 + dv1);

  return cnpj === (base12 + String(dv1) + String(dv2));
}

function validateForm(){
  clearInlineErrors();

  const nome = ($('#nome_filial').value || '').trim();
  if(!nome){
    setInlineError('nome_filial','Nome da filial é obrigatório.');
    return false;
  }

  const email = ($('#email').value || '').trim();
  if(email && !validateEmail(email)){
    setInlineError('email','E-mail inválido.');
    return false;
  }

  const cep = ($('#cep').value || '').trim();
  if(cep){
    const d = cep.replace(/\D/g,'');
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

  const cnpj = ($('#cnpj').value || '').trim();
  if(cnpj && !isValidCnpj(cnpj)){
    setInlineError('cnpj','CNPJ inválido (dígitos verificadores).');
    return false;
  }

  return true;
}

function formDataToObject(form){
  const fd = new FormData(form);
  const obj = {};
  fd.forEach((v,k)=> obj[k] = v);

  if(obj.id !== undefined){
    const s = String(obj.id ?? '').trim();
    obj.id = (s === '') ? null : s;
  }

  if(obj.cep !== undefined) obj.cep = normalizeCep(obj.cep);
  if(obj.uf !== undefined) obj.uf = normalizeUF(obj.uf);

  if(obj.cnpj !== undefined) obj.cnpj = maskCnpj(obj.cnpj);
  if(obj.inscricao_estadual !== undefined) obj.inscricao_estadual = maskIE(obj.inscricao_estadual);
  if(obj.telefone1 !== undefined) obj.telefone1 = maskTelefone(obj.telefone1);

  if(obj.ativo === '') obj.ativo = null;
  return obj;
}

function escapeHtml(str){
  return String(str)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function highlightSelectedRow(){
  document.querySelectorAll('#tbodyLista tr').forEach(tr => tr.classList.remove('ru-row-selected'));
  if(state.selectedId == null) return;
  const tr = document.querySelector(`#tbodyLista tr[data-id="${state.selectedId}"]`);
  if(tr) tr.classList.add('ru-row-selected');
}

async function loadLista(){
  const filtro = ($('#filtroLista').value || '').trim();

  const url = new URL('filiais_save.php', window.location.href);
  url.searchParams.set('action','list');
  if(filtro) url.searchParams.set('f', filtro);

  const res = await fetch(url.toString(), { credentials:'same-origin' });
  const data = await res.json();

  const tb = $('#tbodyLista');
  tb.innerHTML = '';

  if(!data.ok){
    tb.innerHTML = `<tr><td colspan="5" class="text-danger small">${data.error || 'Erro ao listar'}</td></tr>`;
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

    const nome = (r.nome_filial || r.label || '');
    const cnpj = (r.cnpj || '');
    const cidadeUF = `${(r.municipio || '')}${(r.uf ? '/' + r.uf : '')}`.trim();

    tr.innerHTML = `
      <td>${r.id}</td>
      <td><strong>${escapeHtml(nome)}</strong></td>
      <td>${escapeHtml(cnpj)}</td>
      <td>${escapeHtml(cidadeUF)}</td>
      <td>${String(r.ativo) === '1' ? 'SIM' : 'NÃO'}</td>
    `;

    let clickTimer = null;

    tr.addEventListener('click', () => {
      clearTimeout(clickTimer);
      clickTimer = setTimeout(async () => {
        state.selectedId = parseInt(tr.dataset.id, 10) || 0;
        highlightSelectedRow();
        setToolbar();
        await loadFilial(state.selectedId, { switchTab:false, silent:true });
      }, 220);
    });

    tr.addEventListener('dblclick', async () => {
      clearTimeout(clickTimer);
      state.selectedId = parseInt(tr.dataset.id, 10) || 0;
      highlightSelectedRow();
      setToolbar();
      await loadFilial(state.selectedId, { switchTab:true, silent:true });
      showTab('tabDados');
      state.mode = 'view';
      setToolbar();
    });

    tb.appendChild(tr);
  });

  highlightSelectedRow();
}

async function loadFilial(id, options={switchTab:true, silent:false}){
  const url = new URL('filiais_save.php', window.location.href);
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

  $('#nome_filial').value = v.nome_filial ?? '';
  $('#ativo').value = (v.ativo ?? '1');

  // Empresa
  $('#razao_social').value = v.razao_social ?? '';
  $('#nome_fantasia').value = v.nome_fantasia ?? '';
  $('#cnpj').value = v.cnpj ?? '';
  $('#inscricao_estadual').value = v.inscricao_estadual ?? '';
  $('#inscricao_municipal').value = v.inscricao_municipal ?? '';

  // Endereço
  $('#cep').value = v.cep ?? '';
  $('#endereco').value = v.endereco ?? '';
  $('#numero').value = v.numero ?? '';
  $('#complemento').value = v.complemento ?? '';
  $('#bairro').value = v.bairro ?? '';
  $('#municipio').value = v.municipio ?? '';
  $('#uf').value = v.uf ?? '';
  $('#cod_municipio').value = v.cod_municipio ?? '';

  $('#estado').value = v.estado ?? '';
  $('#pais').value = v.pais ?? 'BRASIL';

  // >>> AQUI aplica máscara no telefone ao carregar
  $('#telefone1').value = v.telefone1 ?? '';
  $('#telefone1').value = maskTelefone($('#telefone1').value);

  $('#email').value = v.email ?? '';

  $('#auditCriado').textContent = `Criado: ${(v.criado_em || '—')} por ${(v.criado_por || '—')}`;
  $('#auditAtualizado').textContent = `Atualizado: ${(v.atualizado_em || '—')} por ${(v.atualizado_por || '—')}`;

  // aplica máscaras visuais
  $('#cnpj').value = maskCnpj($('#cnpj').value);
  $('#cep').value = maskCep($('#cep').value);
  $('#inscricao_estadual').value = maskIE($('#inscricao_estadual').value);

  if(options.switchTab){
    showTab('tabDados');
  }
}

function returnToOpener(id, label){
  try{
    if (window.opener && typeof window.opener.ruReceiveLookup === 'function'){
      window.opener.ruReceiveLookup(RETURN_FIELD, String(id), label);
      window.close();
      return true;
    }
  }catch(e){}

  try{
    window.opener?.postMessage({ type:'ru_lookup', field: RETURN_FIELD, id: String(id), label }, '*');
    window.close();
    return true;
  }catch(e){}

  return false;
}

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

    $('#estado').value = '';
    $('#pais').value = 'BRASIL';

  }catch(e){}
}

async function consultarViaCep(){
  const cep = normalizeCep($('#cep').value);
  $('#cep').value = cep;

  const d = cep.replace(/\D/g,'');
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

    if(j.complemento && !($('#complemento').value||'').trim()){
      $('#complemento').value = j.complemento;
    }

    if(j.localidade) $('#municipio').value = j.localidade;
    if(j.uf) $('#uf').value = normalizeUF(j.uf);

    if(j.ibge) $('#cod_municipio').value = j.ibge;

    $('#pais').value = 'BRASIL';

    if(j.uf) await fillEstadoByUF(j.uf);

  }catch(e){}
}

async function saveFilial(){
  if(!validateForm()) return;

  const obj = formDataToObject($('#frmFilial'));

  const res = await fetch('filiais_save.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(obj)
  });

  const data = await res.json();
  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao salvar');
    return;
  }

  showMsg('success', 'Salvo com sucesso.');
  state.selectedId = data.id;

  await loadFilial(data.id, { switchTab:true, silent:true });
  await loadLista();

  state.mode = 'view';
  setToolbar();

  if(IS_POPUP){
    const label = data.label || ($('#nome_filial').value || '').trim() || ('#' + data.id);
    returnToOpener(data.id, label);
  }
}

async function deleteFilial(){
  const id = $('#id').value;
  if(!id) return;
  if(!confirm('Confirma excluir esta filial?')) return;

  const res = await fetch('filiais_delete.php', {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });

  const data = await res.json();
  if(!data.ok){
    showMsg('danger', data.error || 'Erro ao excluir');
    return;
  }

  showMsg('success', 'Excluída com sucesso.');
  clearForm();
  state.selectedId = null;
  state.mode = 'view';
  setToolbar();
  await loadLista();
}

/* ===== eventos ===== */
$('#btnIncluir').addEventListener('click', ()=>{
  clearForm();
  state.selectedId = null;
  state.mode = 'new';
  setToolbar();
  showTab('tabDados');
});

$('#btnEditar').addEventListener('click', async ()=>{
  const idAtual = $('#id').value;
  const idSelecionado = state.selectedId;
  const idParaEditar = idAtual || idSelecionado;
  if(!idParaEditar) return;

  if(!idAtual || String(idAtual) !== String(idParaEditar)){
    await loadFilial(idParaEditar, { switchTab:false, silent:true });
  }

  state.mode = 'edit';
  setToolbar();
  showTab('tabDados');
});

$('#btnCancelar').addEventListener('click', async ()=>{
  state.mode = 'view';
  setToolbar();

  if($('#id').value){
    await loadFilial($('#id').value, { switchTab:true, silent:true });
  } else {
    clearForm();
  }
});

$('#btnSalvar').addEventListener('click', saveFilial);
$('#btnExcluir').addEventListener('click', deleteFilial);

$('#btnAtualizarLista').addEventListener('click', loadLista);
$('#filtroLista').addEventListener('keydown', (e)=>{
  if(e.key === 'Enter'){ e.preventDefault(); loadLista(); }
});

/* =========================
   LISTENERS DE MÁSCARA
   ========================= */

// CNPJ
$('#cnpj').addEventListener('input', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#cnpj').value = maskCnpj($('#cnpj').value);
  }
});
$('#cnpj').addEventListener('blur', ()=>{
  if(!(state.mode === 'new' || state.mode === 'edit')) return;
  const c = ($('#cnpj').value || '').trim();
  if(c && !isValidCnpj(c)){
    setInlineError('cnpj','CNPJ inválido (dígitos verificadores).');
  }
});

// IE
$('#inscricao_estadual').addEventListener('input', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#inscricao_estadual').value = maskIE($('#inscricao_estadual').value);
  }
});
$('#inscricao_estadual').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#inscricao_estadual').value = maskIE($('#inscricao_estadual').value);
  }
});

// CEP
$('#cep').addEventListener('input', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#cep').value = maskCep($('#cep').value);
  }
});
$('#cep').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit') consultarViaCep();
});

// >>> TELEFONE (o que faltava)
$('#telefone1').addEventListener('input', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#telefone1').value = maskTelefone($('#telefone1').value);
  }
});
$('#telefone1').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit'){
    $('#telefone1').value = maskTelefone($('#telefone1').value);
  }
});

// UF
$('#uf').addEventListener('blur', ()=>{
  if(state.mode === 'new' || state.mode === 'edit') fillEstadoByUF($('#uf').value);
});

window.addEventListener('load', ()=>{
  state.mode = 'view';
  setToolbar();
  loadLista();
});
</script>
</body>
</html>
