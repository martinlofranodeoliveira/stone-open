<?php ob_start(); ?>
<h1>Novo Site</h1>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/sites/store">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-2 gap-xl">
      <label>Nome <a href="#" class="help-hint" data-help="site_name" title="Ajuda sobre o nome do site"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" style="width: 100%" type="text" name="name" required /></label>
      <label>Domínio <a href="#" class="help-hint" data-help="domain" title="Ajuda sobre domínio"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" type="text" style="width: 100%" name="domain" placeholder="ex.: merchant.example.invalid" /></label>
      <label>Callback URL <a href="#" class="help-hint" data-help="callback_url" title="Ajuda sobre Callback URL"><i class="bi bi-question-circle"></i></a><br><input class="w-xxl" style="width: 100%" type="text" name="callback_url" placeholder="https://merchant.example.invalid/webhooks/stone" required /></label>
      <label>Stone Account ID <a href="#" class="help-hint" data-help="stone_account_id" title="Id da conta no Pagar.me (account.id)"><i class="bi bi-question-circle"></i></a><br><input class="w-xl" style="width: 100%" type="text" name="stone_account_id" placeholder="acc_xxxxxxxxxxxxxxxxx" /></label>
      <label>Conta Pagar.me <a href="#" class="help-hint" data-help="pagarme_account" title="Conta (credenciais) a ser usada por este site"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-xl" name="pagarme_account_id" required>
          <option value="">Selecione...</option>
          <?php foreach (($accounts ?? []) as $acc): ?>
            <option value="<?= (int)$acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['active_env']) ?><?= $acc['stone_account_uid']? ', '.htmlspecialchars($acc['stone_account_uid']):'' ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Cadastre as contas em Admin → Contas Pagar.me.</div>
      </label>
      <label>Status <a href="#" class="help-hint" data-help="site_status" title="Ajuda sobre status"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="status">
          <?php foreach (['active','suspended','disabled'] as $st): ?>
            <option value="<?= $st ?>"><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quem paga o juro? <a href="#" class="help-hint" data-help="interest_payer" title="Ajuda sobre quem paga o juro"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="interest_payer">
          <option value="user" selected>Usuário (comprador)</option>
          <option value="merchant">Vendedor (site)</option>
        </select>
        <div class="muted">Apenas para simulação/cálculo de parcelas retornadas pelo Hub.</div>
      </label>
      <label>Parcelas sem juros (quando vendedor paga) <a href="#" class="help-hint" data-help="installments_free" title="Ajuda sobre parcelas sem juros"><i class="bi bi-question-circle"></i></a><br>
        <input class="form-control w-sm" type="number" min="1" name="installments_free" value="1" />
      </label>
      <label>Exigir billing_address no cartão? <a href="#" class="help-hint" data-help="billing_address" title="Exige endereço de cobrança quando antifraude está habilitado"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="require_billing_address">
          <option value="">Padrão do sistema</option>
          <option value="1">Sim</option>
          <option value="0">Não</option>
        </select>
        <div class="muted">Em sandbox/homologação essa exigência é desativada automaticamente.</div>
      </label>
      <label>Rate limit/min <a href="#" class="help-hint" data-help="rate_limit" title="Ajuda sobre rate limit"><i class="bi bi-question-circle"></i></a><br>
        <input class="form-control w-sm" type="number" min="1" name="rate_limit_per_minute" value="400" />
        <div class="muted">Máximo de requisições por minuto permitidas para este site nas rotas privadas. Controle simples por janelamento de 1 minuto.</div>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Criar</button>
      <a class="btn secondary" href="<?= $prefix ?>/sites">Voltar</a>
    </div>
  </form>
  <div class="muted" style="margin-top:8px;">Os segredos (CLIENT_SECRET, FORWARD_SECRET e API_TOKEN) serão mostrados após a criação.</div>
  
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
            if (window.Swal) Swal.fire({text:'Informe um número válido de parcelas sem juros quando o vendedor paga.', icon:'warning'});
            inp.focus();
          }
        }
      });
      update();
    }
    document.addEventListener('DOMContentLoaded', function(){
      var form = document.querySelector('form[action$="/sites/store"]');
      if (form) bind(form);
    });
  })();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
