<?php ob_start(); ?>
<h1>Pagamento com Cartão</h1>
<div class="panel">
  <?php if (!empty($env_info)): ?>
    <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
    <div class="space"></div>
  <?php endif; ?>
  <form method="post" action="<?= $prefix ?>/utils/card">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-3">
      <label>Site<br>
        <select class="form-select" name="site_id" required>
          <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Valor<br>
        <input type="hidden" name="amount_cents" />
        <input class="form-control" type="text" id="c_amount" placeholder="R$ 0,00" />
      </label>
      <label>Referência<br><input class="form-control" type="text" name="client_reference_id" value="ref_<?= date('YmdHis') ?>" /></label>

      <label>Parcelas<br><input class="form-control" type="number" name="installments" min="1" value="1" /></label>
      <label>Tipo de Operação<br>
        <select class="form-select" name="operation_type">
          <option value="auth_and_capture">Autorização e Captura</option>
          <option value="auth_only">Somente Autorização</option>
        </select>
      </label>
      <div></div>

      <label>Número do Cartão<br><input class="form-control" type="text" name="card_number" maxlength="19" required /></label>
      <label>Nome impresso<br><input class="form-control" type="text" name="card_holder" required /></label>
      <label>Validade (MM/AA)<br>
        <div class="flex" style="gap:8px;align-items:center">
          <input class="form-control" type="number" name="card_exp_month" min="1" max="12" placeholder="MM" style="max-width:90px" required />
          <input class="form-control" type="number" name="card_exp_year" min="24" max="99" placeholder="AA" style="max-width:90px" required />
          <input class="form-control" type="text" name="card_cvv" placeholder="CVV" style="max-width:110px" required />
        </div>
      </label>

      <label>Nome do Cliente<br><input class="form-control" type="text" name="customer_name" /></label>
      <label>Email<br><input class="form-control" type="email" name="customer_email" /></label>
      <label>Documento<br><input class="form-control" type="text" name="customer_document" /></label>
    </div>
    <div class="space"></div>
    <div class="flex"><a class="btn secondary" href="<?= $prefix ?>/calculadora">Voltar</a><button class="btn">Pagar</button></div>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger"><?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($result)): $p=$result; $rp=json_decode((string)($p['response_payload']??''),true)?:[]; $tx=$rp['charges'][0]['last_transaction']??[]; ?>
  <div class="panel">
    <h3>Resultado</h3>
    <div class="muted">Status: <strong><?= htmlspecialchars(strtolower((string)($p['status']??''))) ?></strong></div>
    <?php if (!empty($tx['card'])): $card=$tx['card']; ?>
      <div class="muted">Cartão: <?= htmlspecialchars(($card['brand']??'').' •••• '.($card['last_four_digits']??'')) ?></div>
    <?php endif; ?>
    <pre class="muted" style="white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars(json_encode($p, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </div>
  <?php if (!empty($debug)): ?>
    <div class="panel">
      <h3>Config/Debug</h3>
      <div class="muted">Base URL: <?= htmlspecialchars($env_info['base_url'] ?? '') ?></div>
      <div class="muted">Chave (masc): <?= htmlspecialchars($env_info['api_key_masked'] ?? '') ?></div>
      <div class="space"></div>
      <div class="muted">Payload enviado</div>
      <pre class="muted" style="white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars(json_encode($debug['request'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php if (!empty($debug['response'])): ?>
        <div class="muted">Resposta</div>
        <pre class="muted" style="white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars(json_encode($debug['response'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
(function(){
  function digitsOnly(s){ return String(s||'').replace(/\D/g,''); }
  function formatBRLFromDigits(d){ d=digitsOnly(d); if(d==='') d='0'; d=d.replace(/^0+/,''); if(d==='') d='0'; if(d.length===1) d='0'+d; var ip=d.slice(0,-2)||'0', dp=d.slice(-2); ip=ip.replace(/\B(?=(\d{3})+(?!\d))/g,'.'); return 'R$ '+ip+','+dp; }
  function centsFromMasked(str){ var d=digitsOnly(str); return d===''?0:parseInt(d,10); }
  var vis=document.getElementById('c_amount'); var hid=document.querySelector('input[name="amount_cents"]');
  if (vis && hid){
    function render(){ vis.value = formatBRLFromDigits(vis.value); }
    function sync(){ hid.value = centsFromMasked(vis.value); }
    vis.addEventListener('focus', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; setTimeout(render,0); });
    vis.addEventListener('blur', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; });
    vis.addEventListener('input', function(){ setTimeout(function(){ render(); sync(); },0); });
    render(); sync();
  }
  // Masks for card
  function onlyDigits(el){ el.addEventListener('input', function(){ this.value = this.value.replace(/\D+/g,''); }); }
  var num = document.querySelector('input[name="card_number"]');
  if (num){ num.addEventListener('input', function(){ var v=this.value.replace(/\D+/g,'').slice(0,19); this.value=v.replace(/(.{4})/g,'$1 ').trim(); }); }
  var cvv = document.querySelector('input[name="card_cvv"]'); if (cvv){ onlyDigits(cvv); cvv.maxLength=4; }
  var mm = document.querySelector('input[name="card_exp_month"]'); if (mm){ onlyDigits(mm); }
  var yy = document.querySelector('input[name="card_exp_year"]'); if (yy){ onlyDigits(yy); }
  // Basic validation on submit
  var form = document.querySelector('form[action$="/utils/card"]');
  if (form){
    form.addEventListener('submit', function(e){
      if (!hid || parseInt(hid.value||'0',10) <= 0){ e.preventDefault(); if(window.Swal) Swal.fire({text:'Informe um valor válido (> R$ 0,00).', icon:'warning'}); return false; }
      var n = (num && num.value) ? num.value.replace(/\D+/g,'') : '';
      var m = parseInt(mm && mm.value || '0',10), y = parseInt(yy && yy.value || '0',10), c = (cvv && cvv.value) ? cvv.value.replace(/\D+/g,'') : '';
      if (n.length < 13 || n.length > 19 || m < 1 || m > 12 || y < 24 || y > 99 || c.length < 3){ e.preventDefault(); if(window.Swal) Swal.fire({text:'Dados do cartão inválidos. Verifique número, validade e CVV.', icon:'warning'}); return false; }
    });
  }
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
