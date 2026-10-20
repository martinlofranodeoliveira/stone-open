<?php ob_start(); ?>
<h1>Gerar Boleto</h1>
<div class="panel">
  <?php if (!empty($env_info)): ?>
    <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
    <?php if (!empty($auth_check)): ?>
      <div class="muted">Auth check /customers: HTTP <?= (int)($auth_check['http'] ?? 0) ?><?= !empty($auth_check['error']) ? ' • '.$auth_check['error'] : '' ?></div>
    <?php endif; ?>
    <div class="space"></div>
  <?php endif; ?>
  <form method="post" action="<?= $prefix ?>/utils/boleto">
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
        <input class="form-control" type="text" id="b_amount" placeholder="R$ 0,00" />
      </label>
      <label>Referência (opcional)<br><input class="form-control" type="text" name="client_reference_id" placeholder="ref_..." /></label>

      <label>Nome do Cliente<br><input class="form-control" type="text" name="customer_name" required /></label>
      <label>Email<br><input class="form-control" type="email" name="customer_email" required /></label>
      <label>Documento (CPF/CNPJ)<br><input class="form-control" type="text" name="customer_document" required /></label>

      <label>Vencimento<br>
        <input class="form-control" type="date" name="due_at" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+3 days'))) ?>" />
      </label>
      <label>Nº Documento (opcional)<br><input class="form-control" type="text" name="document_number" /></label>
      <label>Banco (opcional)<br><input class="form-control" type="text" name="bank" placeholder="001, 033..." /></label>

      <label>Tipo (opcional)<br>
        <select class="form-select" name="type">
          <option value="">Padrão</option>
          <option value="DM">DM</option>
          <option value="DS">DS</option>
          <option value="NP">NP</option>
          <option value="RC">RC</option>
          <option value="ND">ND</option>
        </select>
      </label>
      <label>Instruções (uma por linha, opcional)<br>
        <textarea class="form-control" name="instructions" rows="3" placeholder="Pague até o vencimento&#10;Após vencimento, sujeita a juros"></textarea>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex"><a class="btn secondary" href="<?= $prefix ?>/calculadora">Voltar</a><button class="btn">Gerar</button></div>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger"><?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($result)): $rp = $result; $meta = json_decode((string)($rp['metadata'] ?? ''), true) ?: []; $boleto = $meta['boleto'] ?? null; ?>
  <div class="panel">
    <h3>Boleto Gerado</h3>
    <?php if (!empty($meta['provider_errors'])): $pe=$meta['provider_errors']; ?>
      <div class="alert danger">Falha do provedor: <?= htmlspecialchars((string)($pe[0]['message'] ?? 'Erro na adquirente')) ?></div>
      <?php if (!empty($env_info)): ?>
        <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($boleto): ?>
      <div class="muted">Vencimento: <?= htmlspecialchars((string)($boleto['due_at'] ?? '')) ?></div>
      <div class="muted">Linha digitável: <?= htmlspecialchars((string)($boleto['line'] ?? '')) ?></div>
      <div class="muted">Código de barras: <?= htmlspecialchars((string)($boleto['barcode'] ?? '')) ?></div>
      <?php if (!empty($boleto['url'])): ?>
        <div class="muted">Link: <a href="<?= htmlspecialchars($boleto['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($boleto['url']) ?></a></div>
      <?php endif; ?>
      <?php if (!empty($boleto['pdf'])): ?>
        <div class="muted">PDF: <a href="<?= htmlspecialchars($boleto['pdf']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($boleto['pdf']) ?></a></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert">Não foi possível extrair os dados do boleto.</div>
    <?php endif; ?>
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
  var vis=document.getElementById('b_amount'); var hid=document.querySelector('input[name="amount_cents"]');
  if (vis && hid){
    function render(){ vis.value = formatBRLFromDigits(vis.value); }
    function sync(){ hid.value = centsFromMasked(vis.value); }
    vis.addEventListener('focus', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; setTimeout(render,0); });
    vis.addEventListener('blur', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; });
    vis.addEventListener('input', function(){ setTimeout(function(){ render(); sync(); },0); });
    render(); sync();
  }
  var form = document.querySelector('form[action$="/utils/boleto"]');
  if (form){
    form.addEventListener('submit', function(e){
      if (!hid || parseInt(hid.value||'0',10) <= 0){ e.preventDefault(); if (window.Swal) Swal.fire({text:'Informe um valor válido (> R$ 0,00).', icon:'warning'}); return false; }
      var due = form.querySelector('input[name="due_at"]');
      if (due && !due.value){ e.preventDefault(); if (window.Swal) Swal.fire({text:'Informe a data de vencimento.', icon:'warning'}); return false; }
      var ok = true;
      ['customer_name','customer_email','customer_document'].forEach(function(n){ var el=form.querySelector('[name="'+n+'"]'); if (el && !el.value){ ok=false; }});
      if(!ok){ e.preventDefault(); if (window.Swal) Swal.fire({text:'Preencha nome, email e documento.', icon:'warning'}); return false; }
    });
  }
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
