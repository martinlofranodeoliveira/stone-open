<?php ob_start(); ?>
<h1>Editar Conta Pagar.me <a href="#" class="btn small secondary js-open-help" title="Ajuda"><i class="bi bi-question-circle"></i></a></h1>
<?php 
  $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
  $isProd = $env === 'production';
?>
<div id="envBanner" class="panel" style="margin:8px 0 14px; border-left:4px solid <?= $isProd ? '#10b981' : '#f59e0b' ?>; padding:10px 12px;">
  <b>Ambiente ativo:</b> <span class="js-env-label"><?= $isProd ? 'Produção' : 'Homolog (Sandbox)' ?></span>
  <div class="muted" style="margin-top:4px;">As chamadas desta conta usarão as chaves <code><?= $isProd ? 'sk_…' : 'sk_test_…' ?></code>/<code><?= $isProd ? 'pk_…' : 'pk_test_…' ?></code>.</div>
  <div class="muted" style="margin-top:2px;">Altere em “Ambiente Ativo”.</div>
  <div class="muted" style="margin-top:2px;">Webhook: <code><?= $isProd ? 'whsec_…' : 'whsec_test_…' ?></code></div>
</div>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/pagarme-accounts/update">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>" />
    <input type="hidden" name="initial_env" value="<?= htmlspecialchars((string)($acc['active_env'] ?? 'sandbox')) ?>" />
    <div class="grid cols-2 gap-xl">
      <label>Nome<br><input class="w-xl" type="text" name="name" value="<?= htmlspecialchars((string)$acc['name']) ?>" required /></label>
      <label>Account UID (acc_...)<br><input class="w-xl" type="text" name="stone_account_uid" value="<?= htmlspecialchars((string)($acc['stone_account_uid'] ?? '')) ?>" placeholder="acc_xxxxxxxxx" /></label>
      <label>Ambiente Ativo<br>
        <select class="form-select w-md" name="active_env">
          <?php foreach(['sandbox','production'] as $env): ?>
            <option value="<?= $env ?>" <?= (($acc['active_env'] ?? 'sandbox')===$env?'selected':'') ?>><?= $env ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status<br>
        <select class="form-select w-md" name="status">
          <?php foreach(['active','disabled'] as $st): ?>
            <option value="<?= $st ?>" <?= (($acc['status'] ?? 'active')===$st?'selected':'') ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>API Key (Sandbox)<br><input class="w-xxl" type="text" name="api_key_sandbox" value="<?= htmlspecialchars((string)($acc['api_key_sandbox'] ?? '')) ?>" placeholder="sk_test_xxxxxxxxxxxxxx" /></label>
      <label>API Key (Produção)<br><input class="w-xxl" type="text" name="api_key_production" value="<?= htmlspecialchars((string)($acc['api_key_production'] ?? '')) ?>" placeholder="sk_xxxxxxxxxxxxxx" /></label>
      <label>Public Key (Sandbox)<br><input class="w-xxl" type="text" name="public_key_sandbox" value="<?= htmlspecialchars((string)($acc['public_key_sandbox'] ?? '')) ?>" placeholder="pk_test_xxxxxxxxxxxxxx" /></label>
      <label>Public Key (Produção)<br><input class="w-xxl" type="text" name="public_key_production" value="<?= htmlspecialchars((string)($acc['public_key_production'] ?? '')) ?>" placeholder="pk_xxxxxxxxxxxxxx" /></label>
      <label>Webhook Secret (Sandbox)<br><input class="w-xxl" type="text" name="webhook_secret_sandbox" value="<?= htmlspecialchars((string)($acc['webhook_secret_sandbox'] ?? '')) ?>" placeholder="whsec_test_xxxxxxxxxxxxxx" /></label>
      <label>Webhook Secret (Produção)<br><input class="w-xxl" type="text" name="webhook_secret_production" value="<?= htmlspecialchars((string)($acc['webhook_secret_production'] ?? '')) ?>" placeholder="whsec_xxxxxxxxxxxxxx" /></label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Salvar</button>
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
          box.querySelectorAll('.muted')[2].innerHTML = 'Webhook: <code>whsec_…</code>';
        } else {
          box.style.borderLeftColor = '#f59e0b';
          if (lab) lab.textContent = 'Homolog (Sandbox)';
          box.querySelectorAll('.muted')[0].innerHTML = 'As chamadas desta conta usarão as chaves <code>sk_test_…</code>/<code>pk_test_…</code>.';
          box.querySelectorAll('.muted')[2].innerHTML = 'Webhook: <code>whsec_test_…</code>';
        }
      }
      var sel0 = document.querySelector('select[name="active_env"]');
      var prevEnv = sel0 ? (sel0.value||'sandbox') : 'sandbox';
      if (sel0) updateEnvBanner(sel0);
      document.addEventListener('change', function(e){
        var sel = e.target.closest('select[name="active_env"]'); if (!sel) return;
        var next = (sel.value||'sandbox').toLowerCase();
        if (next === (prevEnv||'sandbox')) return;
        var proceed = function(){ prevEnv = next; updateEnvBanner(sel); };
        var cancel = function(){ sel.value = prevEnv; updateEnvBanner(sel); };
        if (window.Swal) {
          Swal.fire({
            text: 'Trocar ambiente? Isso pode interromper pagamentos se as chaves estiverem incorretas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, trocar',
            cancelButtonText: 'Cancelar'
          }).then(function(res){ if (res.isConfirmed) proceed(); else cancel(); });
        } else {
          if (confirm('Trocar ambiente? Isso pode interromper pagamentos se as chaves estiverem incorretas.')) proceed(); else cancel();
        }
      });
      document.addEventListener('click', function(e){
        var a = e.target.closest('.js-open-help'); if (!a) return;
        e.preventDefault();
        if (window.bootstrap){ new bootstrap.Modal(document.getElementById('modalHelpPagarme')).show(); }
      });

      // Guardar confirmação também no submit do formulário
      var form = document.querySelector('form[action$="/pagarme-accounts/update"]');
      if (form) {
        form.addEventListener('submit', function(ev){
          // evitar loop se já confirmado
          if (form.getAttribute('data-confirmed') === '1') return;
          var sel = form.querySelector('select[name="active_env"]');
          var initial = (form.querySelector('input[name="initial_env"]').value || 'sandbox').toLowerCase();
          var current = (sel && sel.value || 'sandbox').toLowerCase();
          if (initial === current) return; // nada mudou

          ev.preventDefault();
          var proceed = function(){ form.setAttribute('data-confirmed','1'); form.submit(); };
          if (window.Swal) {
            Swal.fire({
              text: 'Trocar ambiente? Isso pode interromper pagamentos se as chaves estiverem incorretas.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sim, salvar',
              cancelButtonText: 'Cancelar'
            }).then(function(res){ if (res.isConfirmed) proceed(); });
          } else {
            if (confirm('Você alterou o ambiente. Isso pode interromper pagamentos se as chaves estiverem incorretas. Deseja salvar assim mesmo?')) proceed();
          }
        });
      }
    })();
  </script>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
