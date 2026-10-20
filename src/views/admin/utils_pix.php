<?php ob_start(); ?>
<h1>Gerar PIX</h1>
<div class="panel">
  <?php if (!empty($env_info)): ?>
    <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
    <?php if (!empty($auth_check)): ?>
      <div class="muted">Auth check /customers: HTTP <?= (int)($auth_check['http'] ?? 0) ?><?= !empty($auth_check['error']) ? ' • '.$auth_check['error'] : '' ?></div>
    <?php endif; ?>
    <div class="space"></div>
  <?php endif; ?>
  <form method="post" action="<?= $prefix ?>/utils/pix">
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
        <input class="form-control" type="text" id="p_amount" placeholder="R$ 0,00" />
      </label>
      <label>Referência (opcional)<br><input class="form-control" type="text" name="client_reference_id" placeholder="ref_..." /></label>

      <label>Nome do Cliente<br><input class="form-control" type="text" name="customer_name" required /></label>
      <label>Email<br><input class="form-control" type="email" name="customer_email" required /></label>
      <label>Documento (CPF/CNPJ)<br><input class="form-control" type="text" name="customer_document" required /></label>

      <label>Telefone (DDI)<br><input class="form-control" type="text" name="customer_phone_cc" value="55" /></label>
      <label>Telefone (DDD)<br><input class="form-control" type="text" name="customer_phone_area" value="11" /></label>
      <label>Telefone (Número)<br><input class="form-control" type="text" name="customer_phone_number" required /></label>

      <label>Expira em (data/hora)<br>
        <input class="form-control" type="datetime-local" name="expires_at" step="1" value="<?= htmlspecialchars(date('Y-m-d\TH:i', time() + 3600)) ?>" />
        <small class="muted">Formato date-time; se vazio, usa +1h.</small>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex"><a class="btn secondary" href="<?= $prefix ?>/calculadora">Voltar</a><button class="btn">Gerar</button></div>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger"><?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($result)): $rp = $result; $meta = json_decode((string)($rp['metadata'] ?? ''), true) ?: []; $pix = $meta['pix'] ?? null; ?>
  <div class="panel">
    <h3>PIX Gerado</h3>
    <?php if (!empty($meta['provider_errors'])): $pe=$meta['provider_errors']; ?>
      <div class="alert danger">Falha do provedor: <?= htmlspecialchars((string)($pe[0]['message'] ?? 'Erro na adquirente')) ?></div>
      <?php if (!empty($env_info)): ?>
        <div class="muted">Ambiente: <strong><?= htmlspecialchars($env_info['env']) ?></strong> • Chave: <?= htmlspecialchars($env_info['api_key_masked']) ?></div>
      <?php endif; ?>
      <?php if (stripos((string)($pe[0]['message'] ?? ''), 'action_forbidden') !== false || stripos((string)($pe[0]['message'] ?? ''), 'sem ambiente configurado') !== false): ?>
        <div class="muted">Dica: Este tipo de transação (PIX) pode não estar habilitado para sua conta/ambiente na Pagar.me. Confirme no portal se o produto PIX está ativo no ambiente selecionado (produção ou homologação) e se a <strong>chave secreta</strong> correspondente (<code>sk_live</code> para produção, <code>sk_test</code> para homologação) está correta.</div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($pix): ?>
      <div class="muted">Expira em: <?= htmlspecialchars($pix['expires_at'] ?? '') ?></div>
      <div class="grid cols-2">
        <div>
          <div class="muted">Código PIX</div>
          <textarea class="form-control" readonly rows="6"><?= htmlspecialchars((string)($pix['qr_code'] ?? '')) ?></textarea>
        </div>
        <div>
          <div class="muted">QR Code</div>
          <?php if (!empty($pix['qr_code_url'])): ?>
            <img id="pix_img" src="<?= htmlspecialchars($pix['qr_code_url']) ?>" alt="QR Code" style="max-width:300px;display:block;" />
          <?php endif; ?>
          <div id="pix_qr" style="max-width:300px;"></div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert">Não foi possível extrair os dados do PIX.</div>
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
  var vis=document.getElementById('p_amount'); var hid=document.querySelector('input[name="amount_cents"]');
  if (vis && hid){
    function render(){ vis.value = formatBRLFromDigits(vis.value); }
    function sync(){ hid.value = centsFromMasked(vis.value); }
    vis.addEventListener('focus', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; setTimeout(render,0); });
    vis.addEventListener('blur', function(){ if(!digitsOnly(vis.value)) vis.value='R$ 0,00'; });
    vis.addEventListener('input', function(){ setTimeout(function(){ render(); sync(); },0); });
    render(); sync();
  }
  function onlyDigits(el){ if(!el) return; el.addEventListener('input', function(){ this.value = this.value.replace(/\D+/g,''); }); }
  onlyDigits(document.querySelector('input[name="customer_phone_cc"]'));
  onlyDigits(document.querySelector('input[name="customer_phone_area"]'));
  onlyDigits(document.querySelector('input[name="customer_phone_number"]'));
  // Basic client-side validation
  var form = document.querySelector('form[action$="/utils/pix"]');
  if (form){
    form.addEventListener('submit', function(e){
      if (!hid || parseInt(hid.value||'0',10) <= 0){ e.preventDefault(); if (window.Swal) Swal.fire({text:'Informe um valor válido (> R$ 0,00).', icon:'warning'}); return false; }
      var expEl = form.querySelector('input[name="expires_at"]');
      if (expEl) {
        if (!expEl.value) { e.preventDefault(); if (window.Swal) Swal.fire({text:'Informe a data/hora de expiração.', icon:'warning'}); return false; }
        var dt = new Date(expEl.value);
        if (isNaN(dt.getTime())) { e.preventDefault(); if (window.Swal) Swal.fire({text:'Data/hora de expiração inválida.', icon:'warning'}); return false; }
      }
      var ok = true;
      ['customer_name','customer_email','customer_document','customer_phone_number'].forEach(function(n){ var el=form.querySelector('[name="'+n+'"]'); if (el && !el.value){ ok=false; }});
      if(!ok){ e.preventDefault(); if (window.Swal) Swal.fire({text:'Preencha nome, email, documento e telefone.', icon:'warning'}); return false; }
    });
  }
  // Render QR code from payload (fallback if image fails or not provided)
  <?php if (!empty($result)): $rp = $result; $meta = json_decode((string)($rp['metadata'] ?? ''), true) ?: []; $pix = $meta['pix'] ?? null; if ($pix): ?>
  try {
    var qrText = <?= json_encode((string)($pix['qr_code'] ?? '')) ?>;
    var qrUrl  = <?= json_encode((string)($pix['qr_code_url'] ?? '')) ?>;
    var imgEl = document.getElementById('pix_img');
    var boxEl = document.getElementById('pix_qr');
    function renderQR(){
      if (!boxEl || !qrText) return;
      if (!window.QRCode) { setTimeout(renderQR, 120); return; }
      boxEl.innerHTML = '';
      try { new QRCode(boxEl, { text: qrText, width: 260, height: 260 }); } catch(e) {}
    }
    if (imgEl) {
      imgEl.addEventListener('error', function(){ imgEl.style.display='none'; renderQR(); });
      // Fallback timeout if image takes too long
      setTimeout(function(){
        if (imgEl && (typeof imgEl.naturalWidth === 'undefined' || imgEl.naturalWidth === 0)) {
          imgEl.style.display='none';
          renderQR();
        }
      }, 1200);
    } else {
      renderQR();
    }
  } catch (e) {}
  <?php endif; endif; ?>
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
