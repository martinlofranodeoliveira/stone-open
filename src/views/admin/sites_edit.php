<?php ob_start(); ?>
<h1>Editar Site</h1>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/sites/update">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>" />
    <div class="grid cols-2 gap-xl">
      <label>Nome <a href="#" class="help-hint" data-help="site_name" title="Ajuda sobre o nome do site"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" style="width: 100%" type="text" name="name" value="<?= htmlspecialchars($site['name']) ?>" required /></label>
      <label>Domínio <a href="#" class="help-hint" data-help="domain" title="Ajuda sobre domínio"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" type="text" style="width: 100%" name="domain" value="<?= htmlspecialchars((string)$site['domain']) ?>" /></label>
      <label>Callback URL <a href="#" class="help-hint" data-help="callback_url" title="Ajuda sobre Callback URL"><i class="bi bi-question-circle"></i></a><br><input class="w-xxl" style="width: 100%" type="text" name="callback_url" value="<?= htmlspecialchars($site['callback_url']) ?>" required /></label>
      <label>Stone Account ID <a href="#" class="help-hint" data-help="stone_account_id" title="Id da conta no Pagar.me (account.id)"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" style="width: 100%" type="text" name="stone_account_id" value="<?= htmlspecialchars((string)($site['stone_account_id'] ?? '')) ?>" placeholder="acc_xxxxxxxxxxxxxxxxx" /></label>
      <label>Conta Pagar.me <a href="#" class="help-hint" data-help="pagarme_account" title="Conta (credenciais) a ser usada por este site"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-xl" name="pagarme_account_id" required>
          <option value="">Selecione...</option>
          <?php $selAcc = (int)($site['pagarme_account_id'] ?? 0); foreach (($accounts ?? []) as $acc): $sel = $selAcc===(int)$acc['id']?'selected':''; ?>
            <option value="<?= (int)$acc['id'] ?>" <?= $sel ?>><?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['active_env']) ?><?= $acc['stone_account_uid']? ', '.htmlspecialchars($acc['stone_account_uid']):'' ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Cadastre as contas em Admin → Contas Pagar.me.</div>
      </label>
      <label>Status <a href="#" class="help-hint" data-help="site_status" title="Ajuda sobre status"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="status">
          <?php foreach (['active','suspended','disabled'] as $st): ?>
            <option value="<?= $st ?>" <?= $site['status']===$st?'selected':'' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quem paga o juro? <a href="#" class="help-hint" data-help="interest_payer" title="Ajuda sobre quem paga o juro"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="interest_payer">
          <option value="user" <?= ($site['interest_payer'] ?? 'user')==='user'?'selected':'' ?>>Usuário (comprador)</option>
          <option value="merchant" <?= ($site['interest_payer'] ?? 'user')==='merchant'?'selected':'' ?>>Vendedor (site)</option>
        </select>
        <div class="muted">Apenas para simulação/cálculo de parcelas retornadas pelo Hub.</div>
      </label>
      <label>Parcelas sem juro (quando vendedor paga) <a href="#" class="help-hint" data-help="installments_free" title="Ajuda sobre parcelas sem jurso"><i class="bi bi-question-circle"></i></a><br>
        <input class="form-control w-sm" type="number" min="1" name="installments_free" value="<?= (int)($site['installments_free'] ?? 1) ?>" />
      </label>
      <label>Exigir billing_address no cartão? <a href="#" class="help-hint" data-help="billing_address" title="Exige endereço de cobrança quando antifraude está habilitado"><i class="bi bi-question-circle"></i></a><br>
        <?php $reqBilling = $site['require_billing_address'] ?? null; ?>
        <select class="form-select w-md" name="require_billing_address">
          <option value="" <?= ($reqBilling === null || $reqBilling === '') ? 'selected' : '' ?>>Padrão do sistema</option>
          <option value="1" <?= ((string)$reqBilling === '1') ? 'selected' : '' ?>>Sim</option>
          <option value="0" <?= ((string)$reqBilling === '0') ? 'selected' : '' ?>>Não</option>
        </select>
        <div class="muted">Em sandbox/homologação essa exigência é desativada automaticamente.</div>
      </label>
      <label>Rate limit/min <a href="#" class="help-hint" data-help="rate_limit" title="Ajuda sobre rate limit"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-sm" type="number" name="rate_limit_per_minute" min="1" value="<?= (int)$site['rate_limit_per_minute'] ?>" /></label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Salvar</button>
      <a class="btn secondary" href="<?= $prefix ?>/sites">Voltar</a>
    </div>
  </form>
</div>

<script>
  (function(){
    function bind(frm){
      var sel = frm.querySelector('select[name="interest_payer"]');
      var inp = frm.querySelector('input[name="installments_free"]');
      if (!sel || !inp) return;
      function update(){
        var isMerchant = sel.value === 'merchant';
        inp.disabled = !isMerchant;
        inp.required = isMerchant;
        if (isMerchant && (!inp.value || parseInt(inp.value,10) < 1)) inp.value = 1;
      }
      sel.addEventListener('change', update);
      frm.addEventListener('submit', function(e){
          if (sel.value === 'merchant'){
          var v = parseInt(inp.value||'0',10);
          if (!v || v < 1){
            e.preventDefault();
            if (window.Swal) Swal.fire({text:'Informe um número válido de parcelas sem juro quando o vendedor paga.', icon:'warning'});
            inp.focus();
          }
        }
      });
      update();
    }
    document.addEventListener('DOMContentLoaded', function(){
      var form = document.querySelector('form[action$="/sites/update"]');
      if (form) bind(form);
    });
  })();
</script>

<!-- Modal genérico para exibir segredos gerados (Bootstrap 5) -->
<div class="modal fade" id="modalSecrets" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
      <div class="modal-header" style="border-color:var(--border)">
        <h5 class="modal-title">Novos Segredos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="flex" style="justify-content: space-between; align-items: center;">
          <p class="muted" style="margin:0">Guarde com segurança. Valores sensíveis podem ser exibidos apenas uma vez.</p>
          <button type="button" class="btn secondary" id="btnCopySecretsEdit">Copiar</button>
        </div>
        <pre id="modalSecretsPre" style="white-space:pre-wrap"></pre>
      </div>
      <div class="modal-footer" style="border-color:var(--border)">
        <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
  </div>

<script>
  (function(){
    function encodeFormData(form){
      var fd = new FormData(form);
      var p = new URLSearchParams();
      for (var [k,v] of fd.entries()){ p.append(k, v); }
      return p.toString();
    }
    function showSecrets(obj){
      var pre = document.getElementById('modalSecretsPre');
      var lines = [];
      if (obj.client_id) lines.push('CLIENT_ID=' + obj.client_id);
      if (obj.client_secret) lines.push('CLIENT_SECRET=' + obj.client_secret);
      if (obj.forward_secret) lines.push('FORWARD_SECRET=' + obj.forward_secret);
      if (obj.api_token) lines.push('API_TOKEN=' + obj.api_token);
      pre.textContent = lines.join('\n');
      var el = document.getElementById('modalSecrets');
      var bsModal = new bootstrap.Modal(el);
      bsModal.show();
      var copyBtn = document.getElementById('btnCopySecretsEdit');
      if (copyBtn) {
        copyBtn.onclick = function(){
          var txt = pre.innerText || pre.textContent || '';
          var done = function(){ copyBtn.textContent = 'Copiado!'; copyBtn.classList.add('ok'); setTimeout(function(){ copyBtn.textContent = 'Copiar'; copyBtn.classList.remove('ok'); }, 1200); };
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
    document.addEventListener('DOMContentLoaded', function(){
      var frmReset = document.querySelector('form[action$="/sites/reset-secrets"]');
      if (frmReset){
        // Click handler on button to avoid global data-confirm form.submit()
        var btnReset = frmReset.querySelector('.js-reset-secrets');
        if (btnReset){
          btnReset.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            var msg = btnReset.getAttribute('data-confirm') || 'Gerar novos segredos para este site?';
            var doReset = function(){
              btnReset.disabled = true;
              fetch(frmReset.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
                body: encodeFormData(frmReset),
                credentials: 'same-origin'
              }).then(function(r){ return r.json(); }).then(function(obj){
                if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
                showSecrets(obj);
              }).catch(function(err){
                if (window.Swal) Swal.fire({text:'Erro ao resetar segredos: '+err, icon:'error'}); else console.error(err);
              }).finally(function(){ btnReset.disabled = false; });
            };
            if (window.Swal){
              Swal.fire({text: msg, icon:'warning', showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Cancelar'}).then(function(res){ if (res.isConfirmed) doReset(); });
            } else {
              if (confirm(msg)) doReset();
            }
          });
        }
        // Fallback in case submit happens by other means
        frmReset.addEventListener('submit', function(e){
          e.preventDefault();
          var submitBtn = frmReset.querySelector('.js-reset-secrets') || frmReset.querySelector('button[type="submit"]');
          if (submitBtn) submitBtn.disabled = true;
          fetch(frmReset.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
            body: encodeFormData(frmReset),
            credentials: 'same-origin'
          }).then(function(r){ return r.json(); }).then(function(obj){
            if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
            showSecrets(obj);
          }).catch(function(err){
            if (window.Swal) Swal.fire({text:'Erro ao resetar segredos: '+err, icon:'error'}); else console.error(err);
          }).finally(function(){ if (submitBtn) submitBtn.disabled = false; });
        });
      }
      var frmToken = document.querySelector('form[action$="/sites/rotate-token"]');
      if (frmToken){
        var btnRotate = frmToken.querySelector('.js-rotate-token');
        if (btnRotate){
          btnRotate.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            var msg = btnRotate.getAttribute('data-confirm') || 'Gerar um novo API_TOKEN? O anterior deixará de funcionar.';
            var doRotate = function(){
              btnRotate.disabled = true;
              fetch(frmToken.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
                body: encodeFormData(frmToken),
                credentials: 'same-origin'
              }).then(function(r){ return r.json(); }).then(function(obj){
                if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
                showSecrets(obj);
              }).catch(function(err){
                if (window.Swal) Swal.fire({text:'Erro ao gerar novo token: '+err, icon:'error'}); else console.error(err);
              }).finally(function(){ btnRotate.disabled = false; });
            };
            if (window.Swal){
              Swal.fire({text: msg, icon:'warning', showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Cancelar'}).then(function(res){ if (res.isConfirmed) doRotate(); });
            } else {
              if (confirm(msg)) doRotate();
            }
          });
        }
        // Fallback submit handler
        frmToken.addEventListener('submit', function(e){
          e.preventDefault();
          var submitBtn2 = frmToken.querySelector('.js-rotate-token') || frmToken.querySelector('button[type="submit"]');
          if (submitBtn2) submitBtn2.disabled = true;
          fetch(frmToken.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
            body: encodeFormData(frmToken),
            credentials: 'same-origin'
          }).then(function(r){ return r.json(); }).then(function(obj){
            if (!obj || obj.error){ throw new Error(obj && obj.error || 'Falha'); }
            showSecrets(obj);
          }).catch(function(err){
            if (window.Swal) Swal.fire({text:'Erro ao gerar novo token: '+err, icon:'error'}); else console.error(err);
          }).finally(function(){ if (submitBtn2) submitBtn2.disabled = false; });
        });
      }
    });
  })();
</script>

<div class="panel">
  <h3>Segredos</h3>
  <p class="muted">Use com cautela. O novo CLIENT_SECRET será exibido uma única vez.</p>
  <form method="post" action="<?= $prefix ?>/sites/reset-secrets">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>" />
    <button class="btn danger js-reset-secrets" type="submit" data-confirm="Gerar novos segredos para este site? Esta ação invalida os anteriores."><svg class="ico"><use href="/assets/icons.svg#refresh"></use></svg> Resetar segredos</button>
  </form>
</div>

<div class="panel">
  <h3>API Token</h3>
  <p class="muted">Gere um novo API_TOKEN e copie-o imediatamente. Será exibido apenas uma vez.</p>
  <form method="post" action="<?= $prefix ?>/sites/rotate-token">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>" />
    <button class="btn js-rotate-token" type="submit" data-confirm="Gerar um novo API_TOKEN? O anterior deixará de funcionar."><svg class="ico"><use href="/assets/icons.svg#refresh"></use></svg> Gerar novo API_TOKEN</button>
  </form>
</div>

<div class="panel">
  <h3>Preview de Parcelas (simulação com a configuração deste site)</h3>
  <div class="flex">
    <label>Valor (centavos)<br><input class="form-control w-sm" id="qp_amount" type="number" min="100" value="10990" /></label>
    <label>BIN (6 dígitos)<br><input class="form-control w-sm" id="qp_bin" type="text" maxlength="6" value="411111" /></label>
    <label>Máx. parcelas (opcional)<br><input class="form-control w-sm" id="qp_max" type="number" min="1" placeholder="12" /></label>
    <button class="btn" type="button" id="qp_go">Simular</button>
  </div>
  <div id="qp_out" class="muted" style="margin-top:10px;white-space:pre-wrap"></div>
</div>

<script>
  (function(){
    var btn = document.getElementById('qp_go');
    var out = document.getElementById('qp_out');
    if (!btn) return;
    function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]); }); }
    btn.addEventListener('click', function(){
      var amount = parseInt(document.getElementById('qp_amount').value||'0',10);
      var bin = (document.getElementById('qp_bin').value||'').replace(/\D/g,'').slice(0,6);
      var max = parseInt(document.getElementById('qp_max').value||'0',10);
      if (!amount || bin.length<6){ out.textContent='Informe um valor e BIN válidos.'; return; }
      var url = '<?= $prefix ?>/sites/<?= (int)$site['id'] ?>/quote-preview?amount_cents='+amount+'&bin='+bin+(max?('&max_parcelas='+max):'');
      fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(resp){
          if (resp.error){ out.textContent = 'Erro: ' + resp.error; return; }
          var html = [];
          html.push('Bandeira: ' + esc(resp.brand || '-'));
          html.push('Quem paga o juro: ' + esc(resp.interest_payer || 'user'));
          // Brand icon and path
          var iconPath = resp.brand_icon || '';
          if (iconPath){
            html.push('Ícone: <code>' + esc(iconPath) + '</code>');
          }
          // Installments
          if (Array.isArray(resp.installments)){
            html.push('Parcelas:');
            resp.installments.forEach(function(p){
              var label = p && (p.label || (p.number + 'x de ' + p.amount_formatted));
              html.push(' - ' + esc(label));
            });
          }
          out.innerHTML = html.join('<br>');
          // After injecting HTML, try to render the icon and validate availability
          if (iconPath){
            var wrap = document.createElement('div');
            wrap.style.margin = '6px 0';
            var img = new Image();
            img.alt = (resp.brand || 'bandeira');
            img.style.height = '55px';
            img.style.verticalAlign = 'middle';
            img.onload = function(){ wrap.replaceChildren(img); };
            img.onerror = function(){ wrap.innerHTML = '<span class="err">Erro: ícone da bandeira não encontrado em ' + esc(iconPath) + '</span>'; };
            var sep = iconPath.indexOf('?') === -1 ? '?' : '&';
            img.src = iconPath + sep + 'v=' + Date.now();
            out.appendChild(wrap);
          }
        })
        .catch(function(e){ out.textContent = 'Falha: ' + e; });
    });
  })();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
