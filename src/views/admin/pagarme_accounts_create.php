<?php ob_start(); ?>
<h1>Nova Conta Pagar.me <a href="#" class="btn small secondary js-open-help" title="Ajuda"><i class="bi bi-question-circle"></i></a></h1>
<div id="envBanner" class="panel" style="margin:8px 0 14px; border-left:4px solid #f59e0b; padding:10px 12px;">
  <b>Ambiente ativo:</b> <span class="js-env-label">Homolog (Sandbox)</span>
  <div class="muted" style="margin-top:4px;">As chamadas desta conta usarão as chaves <code>sk_test_…</code>/<code>pk_test_…</code>.</div>
  <div class="muted" style="margin-top:2px;">Altere em “Ambiente Ativo”.</div>
  <div class="muted" style="margin-top:2px;">Produção usará <code>sk_…</code>/<code>pk_…</code>.</div>
  <div class="muted" style="margin-top:2px;">Webhook: <code>whsec_test_…</code> (Sandbox) / <code>whsec_…</code> (Produção)</div>
  
</div>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/pagarme-accounts/store">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-2 gap-xl">
      <label>Nome<br><input class="w-xl" type="text" name="name" required /></label>
      <label>Account UID (acc_...)<br><input class="w-xl" type="text" name="stone_account_uid" placeholder="acc_xxxxxxxxx" /></label>
      <label>Ambiente Ativo<br>
        <select class="form-select w-md" name="active_env">
          <option value="sandbox">sandbox</option>
          <option value="production">production</option>
        </select>
      </label>
      <label>Status<br>
        <select class="form-select w-md" name="status">
          <option value="active">active</option>
          <option value="disabled">disabled</option>
        </select>
      </label>
      <label>API Key (Sandbox)<br><input class="w-xxl" type="text" name="api_key_sandbox" placeholder="sk_test_xxxxxxxxxxxxxx" /></label>
      <label>API Key (Produção)<br><input class="w-xxl" type="text" name="api_key_production" placeholder="sk_xxxxxxxxxxxxxx" /></label>
      <label>Public Key (Sandbox)<br><input class="w-xxl" type="text" name="public_key_sandbox" placeholder="pk_test_xxxxxxxxxxxxxx" /></label>
      <label>Public Key (Produção)<br><input class="w-xxl" type="text" name="public_key_production" placeholder="pk_xxxxxxxxxxxxxx" /></label>
      <label>Webhook Secret (Sandbox)<br><input class="w-xxl" type="text" name="webhook_secret_sandbox" placeholder="whsec_test_xxxxxxxxxxxxxx" /></label>
      <label>Webhook Secret (Produção)<br><input class="w-xxl" type="text" name="webhook_secret_production" placeholder="whsec_xxxxxxxxxxxxxx" /></label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Criar</button>
      <a class="btn secondary" href="<?= $prefix ?>/pagarme-accounts">Voltar</a>
    </div>
  </form>
</div>
<div class="modal fade" id="modalHelpPagarme" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
      <div class="modal-header" style="border-color:var(--border)">
        <h5 class="modal-title">Ajuda – Credenciais Pagar.me</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul>
          <li><b>API Key</b>: use <code>sk_test_…</code> para Sandbox e <code>sk_…</code> para Produção.</li>
          <li><b>Public Key</b>: use <code>pk_test_…</code> para Sandbox e <code>pk_…</code> para Produção.</li>
          <li><b>Webhook Secret</b>: segredo do webhook configurado na conta (<code>whsec_test_…</code> / <code>whsec_…</code>).</li>
          <li><b>Ambiente Ativo</b>: determina quais chaves serão usadas nas chamadas.</li>
        </ul>
      </div>
      <div class="modal-footer" style="border-color:var(--border)">
        <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
  <script>
    (function(){
      function updateEnvBanner(sel){
        var env = (sel && sel.value || 'sandbox').toLowerCase();
        var box = document.getElementById('envBanner'); if (!box) return;
        var lab = box.querySelector('.js-env-label');
        if (env === 'production'){
          box.style.borderLeftColor = '#10b981';
          if (lab) lab.textContent = 'Produção';
          box.querySelectorAll('.muted')[0].innerHTML = 'As chamadas desta conta usarão as chaves <code>sk_…</code>/<code>pk_…</code>.';
        } else {
          box.style.borderLeftColor = '#f59e0b';
          if (lab) lab.textContent = 'Homolog (Sandbox)';
          box.querySelectorAll('.muted')[0].innerHTML = 'As chamadas desta conta usarão as chaves <code>sk_test_…</code>/<code>pk_test_…</code>.';
        }
      }
      document.addEventListener('change', function(e){
        var sel = e.target.closest('select[name="active_env"]'); if (!sel) return;
        updateEnvBanner(sel);
      });
      // init on load
      var sel0 = document.querySelector('select[name="active_env"]'); if (sel0) updateEnvBanner(sel0);
      document.addEventListener('click', function(e){
        var a = e.target.closest('.js-open-help'); if (!a) return;
        e.preventDefault();
        if (window.bootstrap){ new bootstrap.Modal(document.getElementById('modalHelpPagarme')).show(); }
      });
    })();
  </script>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
