<?php ob_start(); ?>
<h1>Link de Pagamento</h1>
<div class="panel">
  <?php if (!empty($env_info)): ?>
    <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
    <div class="space"></div>
  <?php endif; ?>
  <form method="post" action="<?= $prefix ?>/utils/link">
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
        <input class="form-control" type="text" id="l_amount" placeholder="R$ 0,00" />
      </label>
      <label>Código (opcional)<br><input class="form-control" type="text" name="code" /></label>

      <label>Descrição (opcional)<br><input class="form-control" type="text" name="description" /></label>
      <label>Expira em (minutos, opcional)<br><input class="form-control" type="number" name="expires_in" min="1" /></label>
      <label>Métodos<br>
        <div class="flex" style="gap:8px;align-items:center;flex-wrap:wrap;">
          <label class="muted"><input type="checkbox" name="methods[]" value="cartao" checked /> cartão</label>
          <label class="muted"><input type="checkbox" name="methods[]" value="pix" /> pix</label>
          <label class="muted"><input type="checkbox" name="methods[]" value="boleto" /> boleto</label>
        </div>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex"><a class="btn secondary" href="<?= $prefix ?>/calculadora">Voltar</a><button class="btn">Gerar Link</button></div>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger"><?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($result)): $r=$result; ?>
  <div class="panel">
    <h3>Link Criado</h3>
    <div class="muted">URL do Checkout</div>
    <div class="flex" style="gap:8px;align-items:center;">
      <input id="linkUrl" class="form-control" type="text" readonly value="<?= htmlspecialchars((string)($r['link_pagamento_url'] ?? '')) ?>" />
      <button class="btn secondary" type="button" id="btnCopy">Copiar</button>
    </div>
    <div class="space"></div>
    <pre class="muted" style="white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars(json_encode($r, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
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
  var vis=document.getElementById('l_amount'); var hid=document.querySelector('input[name="amount_cents"]');
  if (vis && hid){
    function render(){ vis.value = formatBRLFromDigits(vis.value); }
    function sync(){ hid.value = centsFromMasked(vis.value); }
    vis.addEventListener('focus', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; setTimeout(render,0); });
    vis.addEventListener('blur', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; });
    vis.addEventListener('input', function(){ setTimeout(function(){ render(); sync(); },0); });
    render(); sync();
  }
  var form = document.querySelector('form[action$="/utils/link"]');
  if (form){
    form.addEventListener('submit', function(e){
      if (!hid || parseInt(hid.value||'0',10) <= 0){ e.preventDefault(); if(window.Swal) Swal.fire({text:'Informe um valor válido (> R$ 0,00).', icon:'warning'}); return false; }
      var checks = form.querySelectorAll('input[name="methods[]"]:checked');
      if (!checks || checks.length === 0){ e.preventDefault(); if(window.Swal) Swal.fire({text:'Selecione ao menos um método de pagamento.', icon:'warning'}); return false; }
    });
  }
  var btn = document.getElementById('btnCopy');
  if (btn){ btn.addEventListener('click', function(){ var i=document.getElementById('linkUrl'); if (!i) return; i.select(); try{ document.execCommand('copy'); btn.textContent='Copiado!'; setTimeout(function(){ btn.textContent='Copiar'; }, 1200); }catch(e){} }); }
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
