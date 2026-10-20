<?php ob_start(); ?>
<h1>Sites</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/sites">
    <input type="text" class="form-control" name="q" placeholder="Buscar nome/domínio" value="<?= htmlspecialchars($q) ?>" style="min-width: 250px;" />
    <button class="btn secondary">
      <i class="bi bi-search"></i> Buscar
    </button>
    <a class="btn secondary" href="<?= $prefix ?>/sites">
      <i class="bi bi-arrow-counterclockwise"></i> Limpar
    </a>
    <a class="btn" href="<?= $prefix ?>/sites/create">
      <i class="bi bi-plus-lg"></i> Novo Site
    </a>
  </form>
  <?php if (!empty($newSecrets) && is_array($newSecrets)): ?>
  <div class="space"></div>
  <button class="btn" data-bs-toggle="modal" data-bs-target="#modalSecrets">
    <i class="bi bi-key-fill"></i> Ver credenciais do novo site #<?= (int)$newSecrets['id'] ?>
  </button>
  <?php endif; ?>
</div>
<div class="panel">
  <table id="tblSites" class="table" data-url="<?= $prefix ?>/sites/data">
    <thead>
      <tr><th>ID</th><th>Nome</th><th>Domínio</th><th>Status</th><th>Secret (hint)</th><th>Callback</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($sites as $s): ?>
    <tr>
      <td><?= (int)$s['id'] ?></td>
      <td><?= htmlspecialchars($s['name']) ?></td>
      <td><?= htmlspecialchars((string)$s['domain']) ?></td>
      <td>
        <?php $st = (string)$s['status']; $lab = ['active'=>'Ativo','suspended'=>'Suspenso','disabled'=>'Desativado'][$st] ?? $st; ?>
        <span class="badge <?= $st==='active'?'ok':($st==='suspended'?'warn':'err') ?>"><?= htmlspecialchars($lab) ?></span>
        <?php $payer = ($s['interest_payer'] ?? 'user'); if ($payer==='merchant'): ?>
          <span class="badge info">juros: vendedor (<?= (int)($s['installments_free'] ?? 1) ?>x)</span>
        <?php else: ?>
          <span class="badge info">juros: usuário</span>
        <?php endif; ?>
        <?php $lim = (int)($s['rate_limit_per_minute'] ?? 0); $used = (int)($s['rate_used_minute'] ?? 0); if ($lim>0): ?>
          <span class="badge <?= ($used<$lim?'ok':'warn') ?>">limite: <?= $used ?>/<?= $lim ?></span>
        <?php endif; ?>
        <?php if (!empty($s['breaker_open_until'])): $until = strtotime((string)$s['breaker_open_until']); if ($until && $until > time()): ?>
          <span class="badge err">CB aberto até <?= htmlspecialchars(date('H:i:s', $until)) ?></span>
        <?php endif; endif; ?>
      </td>
      <td><code><?= htmlspecialchars((string)($s['client_secret_hint'] ?? '')) ?></code></td>
      <td class="muted" style="max-width:380px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['callback_url']) ?></td>
      <td class="right" style="white-space: nowrap;">
        <div class="action-grid">
        <a class="btn-icon primary" href="<?= $prefix ?>/sites/<?= (int)$s['id'] ?>" title="Editar">
          <i class="bi bi-pencil"></i>
        </a>
        <button class="btn-icon info btn-view-keys" type="button" title="Ver chaves"
          data-site-id="<?= (int)$s['id'] ?>"
          data-client-id="<?= htmlspecialchars($s['client_id']) ?>"
          data-client-secret="<?= htmlspecialchars((string)($s['client_secret_plain'] ?? '')) ?>"
          data-forward-secret="<?= htmlspecialchars((string)$s['webhook_forward_secret']) ?>"
          data-api-token="<?= htmlspecialchars($s['api_token']) ?>"
        >
          <i class="bi bi-key"></i>
        </button>
        <button class="btn-icon warning btn-reset-secrets" type="button" title="Resetar segredos" data-site-id="<?= (int)$s['id'] ?>">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button class="btn-icon primary btn-rotate-token" type="button" title="Gerar novo API_TOKEN" data-site-id="<?= (int)$s['id'] ?>">
          <i class="bi bi-arrow-repeat"></i>
        </button>
        <form method="post" action="<?= $prefix ?>/sites/toggle" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
          <button class="btn-icon <?= $s['status']==='active'?'warning':'success' ?>" type="submit" title="<?= $s['status']==='active'?'Desabilitar':'Habilitar' ?>" data-confirm="<?= $s['status']==='active'?'Desabilitar este site?':'Habilitar este site?' ?>">
            <i class="bi bi-<?= $s['status']==='active'?'pause-circle':'play-circle' ?>"></i>
          </button>
        </form>
        <form method="post" action="<?= $prefix ?>/sites/delete" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
          <button class="btn-icon danger" type="submit" title="Excluir" data-confirm="Excluir permanentemente este site e seus dados relacionados?">
            <i class="bi bi-trash"></i>
          </button>
        </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<?php if (!empty($newSecrets) && is_array($newSecrets)): ?>
<!-- Modal de credenciais (Bootstrap 5) -->
<div class="modal fade" id="modalSecrets" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
      <div class="modal-header" style="border-color:var(--border)">
        <h5 class="modal-title">Credenciais do site #<?= (int)$newSecrets['id'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="flex" style="justify-content: space-between; align-items: center;">
          <p class="muted" style="margin:0">Guarde com segurança. O CLIENT_SECRET é exibido apenas uma vez.</p>
          <button type="button" class="btn secondary" id="btnCopySecretsInitial">Copiar</button>
        </div>
        <pre id="modalSecretsInitialPre"><?php
echo htmlspecialchars(
  "CLIENT_ID={$newSecrets['client_id']}\n" .
  "CLIENT_SECRET={$newSecrets['client_secret']}\n" .
  "FORWARD_SECRET={$newSecrets['forward_secret']}\n" .
  "API_TOKEN={$newSecrets['api_token']}\n",
  ENT_QUOTES
);
?></pre>
      </div>
      <div class="modal-footer" style="border-color:var(--border)">
        <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
  </div>
<?php endif; ?>
<!-- Modal genérico para exibir segredos (AJAX) -->
<div class="modal fade" id="modalSecretsAjax" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
      <div class="modal-header" style="border-color:var(--border)">
        <h5 class="modal-title">Segredos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="flex" style="justify-content: space-between; align-items: center;">
          <p class="muted" style="margin:0">Guarde com segurança. Valores sensíveis podem ser exibidos apenas uma vez.</p>
          <button type="button" class="btn secondary" id="btnCopySecretsAjax">Copiar</button>
        </div>
        <pre id="modalSecretsAjaxPre" style="white-space:pre-wrap"></pre>
      </div>
      <div class="modal-footer" style="border-color:var(--border)">
        <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
  </div>
<!-- Modal de visualização de chaves por site -->
<div class="modal fade" id="modalViewKeys" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
      <div class="modal-header" style="border-color:var(--border)">
        <h5 class="modal-title">Chaves do site <span id="keysSiteId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <pre id="keysPre"></pre>
        <div class="space"></div>
        <div class="help">
          <h4>Como usar</h4>
          <ul>
            <li><b>CLIENT_ID</b> e <b>CLIENT_SECRET</b>: autenticação via cabeçalhos <code>X-Client-Id</code> e <code>X-Client-Secret</code>. Guarde o <b>CLIENT_SECRET</b> com segurança.</li>
            <li><b>API_TOKEN</b>: autenticação via <code>Authorization: Bearer &lt;API_TOKEN&gt;</code> (recomendado pela simplicidade). Você pode rotacioná-lo na tela de edição.</li>
            <li><b>FORWARD_SECRET</b>: usado para validar o HMAC das notificações encaminhadas. Verifique o header <code>X-Hub-Signature: sha256=...</code> calculando <code>hash_hmac('sha256', corpo_bruto, FORWARD_SECRET)</code>.</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer" style="border-color:var(--border)">
        <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
<script>
  (function(){
    var modalEl = document.getElementById('modalViewKeys');
    var pre = document.getElementById('keysPre');
    var siteSpan = document.getElementById('keysSiteId');
    var copyBtn = document.getElementById('btnCopyKeys');
    if (copyBtn && pre) {
      copyBtn.addEventListener('click', function(){
        var txt = pre.innerText || pre.textContent || '';
        var done = function(){ copyBtn.textContent = 'Copiado!'; copyBtn.classList.add('ok'); setTimeout(function(){ copyBtn.textContent = 'Copiar'; copyBtn.classList.remove('ok'); }, 1200); };
        if (navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(done).catch(function(){ fallbackCopy(txt, done); }); }
        else { fallbackCopy(txt, done); }
      });
    }
    function fallbackCopy(text, cb){
      var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch(e) {}
      document.body.removeChild(ta); if (cb) cb();
    }
    document.querySelectorAll('.btn-view-keys').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = btn.getAttribute('data-site-id');
        var clientId = btn.getAttribute('data-client-id')||'';
        var secret = btn.getAttribute('data-client-secret')||'';
        var forward = btn.getAttribute('data-forward-secret')||'';
        var token = btn.getAttribute('data-api-token')||'';
        var text = '';
        text += 'CLIENT_ID=' + clientId + "\n";
        text += 'CLIENT_SECRET=' + secret + "\n";
        text += 'FORWARD_SECRET=' + forward + "\n";
        text += 'API_TOKEN=' + token + "\n";
        pre.textContent = text;
        siteSpan.textContent = '#' + id;
        var bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
        // ensure copy button works for this content
        var copyBtn = document.getElementById('btnCopyKeys');
        if (copyBtn) {
          copyBtn.onclick = function(){
            var txt = pre.innerText || pre.textContent || '';
            var done = function(){ copyBtn.textContent = 'Copiado!'; copyBtn.classList.add('ok'); setTimeout(function(){ copyBtn.textContent = 'Copiar'; copyBtn.classList.remove('ok'); }, 1200); };
            if (navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(done).catch(function(){ fallbackCopy(txt, done); }); }
            else { fallbackCopy(txt, done); }
          };
        }
      });
    });
  })();
</script>
<script>
  (function($){
    var $form = $('form[action$="/sites"]');
    var $tbl = $('#tblSites');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.q=$form.find('[name=q]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'},{data:'name'},{data:'domain'},
        {data:'status', render:function(v,type,row){
          var map = { active:'Ativo', suspended:'Suspenso', disabled:'Desativado' };
          var cls = v==='active'?'ok':(v==='suspended'?'warn':'err');
          var html = '<span class="badge '+cls+'">'+(map[v]||v)+'</span>';
          var payer = (row.interest_payer||'user');
          if (payer==='merchant'){
            var n = row.installments_free||1;
            html += ' <span class="badge info">juros: vendedor ('+n+'x)</span>';
          } else {
            html += ' <span class="badge info">juros: usuário</span>';
          }
          return html;
        }},
        {data:'client_secret_hint', render:function(v){ return v?('<code>'+v+'</code>'):''; }},
        {data:'callback_url'},
        {data:null, orderable:false, render:function(row){
          var id = row.id;
          var html = '';
          html += '<a class="btn-icon" href="<?= $prefix ?>/sites/'+id+'" title="Editar">'
               + '<i class="bi bi-pencil"></i></a> ';
          html += '<button class="btn-icon btn-view-keys" type="button" title="Ver chaves" '
               + 'data-site-id="'+id+'" data-client-id="'+(row.client_id||'')+'" data-client-secret="'+(row.client_secret_plain||'')+'" data-forward-secret="'+(row.webhook_forward_secret||'')+'" data-api-token="'+(row.api_token||'')+'">'
               + '<i class="bi bi-key"></i></button> ';
          html += '<button class="btn-icon btn-reset-secrets" type="button" title="Resetar segredos" data-site-id="'+id+'">'
               + '<i class="bi bi-arrow-clockwise"></i></button> ';
          var icon = (row.status==='active') ? 'bi-pause-circle' : 'bi-play-circle';
          var title = (row.status==='active') ? 'Desabilitar' : 'Habilitar';
          var msg = (row.status==='active') ? 'Desabilitar este site?' : 'Habilitar este site?';
          html += '<form method="post" action="<?= $prefix ?>/sites/toggle" style="display:inline">'
               + '<input type="hidden" name="csrf" value="'+csrfToken+'" />'
               + '<input type="hidden" name="id" value="'+id+'" />'
               + '<button class="btn-icon" type="submit" title="'+title+'" data-confirm="'+msg+'"><i class="bi '+icon+'"></i></button>'
               + '</form> ';
          html += '<form method="post" action="<?= $prefix ?>/sites/delete" style="display:inline">'
               + '<input type="hidden" name="csrf" value="'+csrfToken+'" />'
               + '<input type="hidden" name="id" value="'+id+'" />'
               + '<button class="btn-icon danger" type="submit" title="Excluir" data-confirm="Excluir permanentemente este site e seus dados relacionados?">'
               + '<i class="bi bi-trash"></i></button>'
               + '</form>';
          return html; }
        }
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
    // Delegate click for dynamic rows
    $('#tblSites').on('click', '.btn-view-keys', function(){
      var btn = this;
      var id = btn.getAttribute('data-site-id');
      var clientId = btn.getAttribute('data-client-id')||'';
      var secret = btn.getAttribute('data-client-secret')||'';
      var forward = btn.getAttribute('data-forward-secret')||'';
      var token = btn.getAttribute('data-api-token')||'';
      var pre = document.getElementById('keysPre');
      var siteSpan = document.getElementById('keysSiteId');
      var modalEl = document.getElementById('modalViewKeys');
      var text = '';
      text += 'CLIENT_ID=' + clientId + "\n";
      text += 'CLIENT_SECRET=' + secret + "\n";
      text += 'FORWARD_SECRET=' + forward + "\n";
      text += 'API_TOKEN=' + token + "\n";
      pre.textContent = text;
      siteSpan.textContent = '#' + id;
      var bsModal = new bootstrap.Modal(modalEl);
      bsModal.show();
    });
    function showSecrets(obj){
      var pre = document.getElementById('modalSecretsAjaxPre');
      var lines = [];
      if (obj.client_id) lines.push('CLIENT_ID=' + obj.client_id);
      if (obj.client_secret) lines.push('CLIENT_SECRET=' + obj.client_secret);
      if (obj.forward_secret) lines.push('FORWARD_SECRET=' + obj.forward_secret);
      if (obj.api_token) lines.push('API_TOKEN=' + obj.api_token);
      pre.textContent = lines.join('\n');
      var el = document.getElementById('modalSecretsAjax');
      var bsModal = new bootstrap.Modal(el);
      bsModal.show();
      // bind copy for this modal
      var btn = document.getElementById('btnCopySecretsAjax');
      if (btn) {
        btn.onclick = function(){
          var txt = pre.innerText || pre.textContent || '';
          var done = function(){ btn.textContent = 'Copiado!'; btn.classList.add('ok'); setTimeout(function(){ btn.textContent = 'Copiar'; btn.classList.remove('ok'); }, 1200); };
          if (navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(done).catch(function(){ fallbackCopy(txt, done); }); }
          else { fallbackCopy(txt, done); }
        };
      }
    }
    function fallbackCopy(text, cb){
      var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch(e) {}
      document.body.removeChild(ta); if (cb) cb();
    }
    $('#tblSites').on('click', '.btn-reset-secrets', function(){
      var btn = this;
      var id = btn.getAttribute('data-site-id');
      var doReset = function(){
        btn.disabled = true;
        fetch('<?= $prefix ?>/sites/reset-secrets', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'},
          body:'csrf='+encodeURIComponent(csrfToken)+'&id='+encodeURIComponent(id)
        }).then(function(r){ return r.json(); }).then(function(obj){
          if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
          showSecrets(obj);
          try{ dt.ajax.reload(null,false); }catch(_){ }
        }).catch(function(err){
          if (window.Swal) Swal.fire({text:'Erro ao resetar segredos: '+err, icon:'error'}); else console.error(err);
        }).finally(function(){ btn.disabled = false; });
      };
      if (window.Swal){
        Swal.fire({text:'Gerar novos segredos para este site? Esta ação invalida os anteriores.', icon:'warning', showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Cancelar'}).then(function(res){ if (res.isConfirmed) doReset(); });
      } else {
        if (confirm('Gerar novos segredos para este site?')) doReset();
      }
    });
    $('#tblSites').on('click', '.btn-rotate-token', function(){
      var btn = this;
      var id = btn.getAttribute('data-site-id');
      var doRotate = function(){
        btn.disabled = true;
        fetch('<?= $prefix ?>/sites/rotate-token', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'},
          body:'csrf='+encodeURIComponent(csrfToken)+'&id='+encodeURIComponent(id)
        }).then(function(r){ return r.json(); }).then(function(obj){
          if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
          showSecrets(obj);
        }).catch(function(err){
          if (window.Swal) Swal.fire({text:'Erro ao gerar novo token: '+err, icon:'error'}); else console.error(err);
        }).finally(function(){ btn.disabled = false; });
      };
      if (window.Swal){
        Swal.fire({text:'Gerar um novo API_TOKEN? O anterior deixará de funcionar.', icon:'warning', showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Cancelar'}).then(function(res){ if (res.isConfirmed) doRotate(); });
      } else {
        if (confirm('Gerar um novo API_TOKEN? O anterior deixará de funcionar.')) doRotate();
      }
    });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
