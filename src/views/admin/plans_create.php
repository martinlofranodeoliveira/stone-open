<?php ob_start(); ?>
<h1>Novo Plano</h1>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/plans/store">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-3">
      <?php if (!empty($_GET['error'])): ?>
        <div class="col-span-3">
          <div class="alert danger"><?= htmlspecialchars((string)$_GET['error']) ?></div>
        </div>
      <?php endif; ?>
      <label class="col-span-1">Site<br>
        <select class="form-select" name="site_id" required>
          <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="col-span-2">Nome<br><input class="form-control" type="text" name="name" required /></label>

      <label class="col-span-1">Código<br><input class="form-control" type="text" name="code" placeholder="Opcional" /></label>
      <label class="col-span-2">Descrição<br><input class="form-control" type="text" name="description" placeholder="Opcional" /></label>

      <label class="col-span-1">Valor<br>
        <input class="form-control" type="hidden" name="amount_cents" min="0" step="1" required />
        <input class="form-control" type="text" name="amount_display" placeholder="R$ 0,00" />
      </label>
      <label class="col-span-1">Moeda<br><input class="form-control" type="text" name="currency" value="BRL" maxlength="3" /></label>
      <label class="col-span-1">Status<br>
        <select class="form-select" name="status">
          <option value="active">Ativo</option>
          <option value="inactive">Inativo</option>
        </select>
      </label>

      <label class="col-span-1">Intervalo <a href="#" class="help-hint" data-help="interval_unit" title="Ajuda sobre intervalo"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select" name="interval_unit" data-field="interval_unit">
          <?php $labI=['day'=>'Dia','week'=>'Semana','month'=>'Mês','year'=>'Ano']; foreach (['day','week','month','year'] as $iu): ?>
          <option value="<?= $iu ?>"><?= $labI[$iu] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="col-span-1">Qtd Intervalo <a href="#" class="help-hint" data-help="interval_count" title="Ajuda sobre quantidade do intervalo"><i class="bi bi-question-circle"></i></a><br><input class="form-control" type="number" name="interval_count" data-field="interval_count" value="1" min="1" /></label>
      <label class="col-span-1">Ciclos de Cobrança <a href="#" class="help-hint" data-help="billing_cycles" title="Ajuda sobre ciclos de cobrança"><i class="bi bi-question-circle"></i></a><br><input class="form-control" type="number" name="billing_cycles" value="1" min="1" /></label>
      <label class="col-span-1">Tipo de Cobrança <a href="#" class="help-hint" data-help="billing_type" title="Ajuda sobre tipo de cobrança"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select" name="billing_type" data-field="billing_type">
          <option value="">Padrão (Pré-pago)</option>
          <option value="prepaid">Pré-pago</option>
          <option value="postpaid">Pós-pago</option>
          <option value="exact_day">Dia exato (exige dia)</option>
        </select>
      </label>
      <label class="col-span-1">Dia da Cobrança (1..31) <a href="#" class="help-hint" data-help="billing_day" title="Ajuda sobre dia da cobrança"><i class="bi bi-question-circle"></i></a><br><input class="form-control" type="number" name="billing_day" data-field="billing_day" min="1" max="31" placeholder="Somente para exact_day" /></label>
      <label class="col-span-1">Parcelas (ex.: 1,2,3,6,12) <a href="#" class="help-hint" data-help="installments" title="Ajuda sobre parcelas"><i class="bi bi-question-circle"></i></a><br><input class="form-control" type="text" name="installments" placeholder="Opcional" /></label>
      <label class="col-span-1">Descrição na Fatura (≤13)<br><input class="form-control" type="text" name="statement_descriptor" data-field="statement_descriptor" maxlength="13" placeholder="Opcional" /></label>
      <label class="col-span-1">Dias de Teste (trial)<br><input class="form-control" type="number" name="trial_period_days" min="0" placeholder="Opcional" /></label>
      <!-- Métodos de pagamento ocultados conforme solicitação -->

    </div>
    <div class="space"></div>
    <div class="flex">
      <a href="<?= $prefix ?>/plans" class="btn secondary">Cancelar</a>
      <button class="btn">Salvar</button>
    </div>
  </form>
</div>
<script>
  (function(){
    function digitsOnly(s){ return String(s||'').replace(/\D/g,''); }
    function formatBRLFromDigits(d){
      d = digitsOnly(d);
      if (d === '') d = '0';
      d = d.replace(/^0+/, '');
      if (d === '') d = '0';
      if (d.length === 1) d = '0' + d;
      var intPart = d.slice(0,-2) || '0';
      var decPart = d.slice(-2);
      intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return 'R$ ' + intPart + ',' + decPart;
    }
    function centsFromMasked(str){ var d = digitsOnly(str); return d === '' ? 0 : parseInt(d,10); }
    function maskAndBind(form){
      var hid = form.querySelector('input[name="amount_cents"]');
      var vis = form.querySelector('input[name="amount_display"]');
      if (!hid || !vis) return;
      // init from hidden
      var init = parseInt(hid.value||'0',10);
      vis.value = init ? formatBRLFromDigits(String(init)) : 'R$ 0,00';
      function render(){ vis.value = formatBRLFromDigits(vis.value); }
      function sync(){ hid.value = String(centsFromMasked(vis.value)); }
      vis.addEventListener('focus', function(){ if (!digitsOnly(vis.value)) vis.value = 'R$ 0,00'; setTimeout(render,0); });
      vis.addEventListener('blur', function(){ if (!digitsOnly(vis.value)) vis.value = 'R$ 0,00'; });
      vis.addEventListener('input', function(){ setTimeout(function(){ render(); sync(); },0); });
      form.addEventListener('submit', function(){ sync(); });
    }
    document.addEventListener('DOMContentLoaded', function(){
      var form = document.querySelector('form[action$="/plans/store"]');
      if (form) maskAndBind(form);
      // highlight hint field if provided in query
      var params = new URLSearchParams(location.search);
      var field = params.get('hint_field');
      if (field){
        var sel = form.querySelector('[name="'+field+'"], [data-field="'+field+'"]').closest('label');
        if (sel){ sel.style.outline = '2px solid #f66'; sel.scrollIntoView({behavior:'smooth', block:'center'}); }
      }
    });
  })();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
