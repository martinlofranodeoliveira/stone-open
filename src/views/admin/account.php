<?php ob_start(); ?>
<h1>Configurações da Conta</h1>

<div class="panel">
  <h3>Autenticação HTTP Basic para Webhooks (opcional)</h3>
  <p class="muted">Se habilitada, o endpoint <code>/webhooks/stone</code> exigirá <code>Authorization: Basic</code> com o usuário e senha abaixo. Se desabilitada, não haverá validação por Basic (apenas a verificação de assinatura, se configurada por ambiente).</p>
  <p class="muted">Dúvidas? Veja instruções passo a passo em <a href="<?= $prefix ?>/help#webhooks-basic">Ajuda → Webhooks – Autenticação Basic</a>.</p>

  <form method="post" action="<?= $prefix ?>/account/save">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-2 gap-xl">
      <label>
        <input type="checkbox" name="enable_basic" value="1" <?= $basic_enabled ? 'checked' : '' ?> />
        Habilitar autenticação Basic
      </label>
      <div></div>

      <label data-basic-field>Usuário (Basic)<br>
        <input class="w-xl" type="text" name="basic_user" value="<?= htmlspecialchars($basic_user) ?>" placeholder="ex.: pagarme" />
      </label>
      <label data-basic-field>Senha (Basic)<br>
        <input class="w-xl" type="password" name="basic_pass" placeholder="<?= $basic_enabled ? 'Deixe em branco para manter a atual' : 'Defina uma senha' ?>" />
      </label>

      
    </div>
    <div class="space"></div>
    <button class="btn" type="submit">Salvar</button>
  </form>
</div>

<div class="panel">
  <h3>Teste de Autenticação Pagar.me</h3>
  <p class="muted">Execute um teste de autenticação usando as credenciais da <strong>conta Pagar.me selecionada</strong> (armazenadas no banco) — sem usar variáveis do <code>.env</code>.</p>
  <div class="space"></div>
  <label>Conta Pagar.me para testar<br>
    <select id="accountId" class="form-select w-xl">
      <?php foreach (($accounts ?? []) as $acc): $st = (string)($acc['status'] ?? ''); $dis = ($st!=='active'); ?>
        <option value="<?= (int)$acc['id'] ?>" <?= $dis ? 'disabled' : '' ?>><?= htmlspecialchars((string)$acc['name']) ?> (<?= htmlspecialchars((string)$acc['active_env'] ?? 'sandbox') ?><?= $dis? ', desativada' : '' ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="space"></div>
  <div class="btn-group">
    <button class="btn" type="button" data-auth-test="production">Testar em Produção</button>
    <button class="btn secondary" type="button" data-auth-test="sandbox">Testar em Homologação</button>
  </div>
  <div class="space"></div>
  <div id="authTestResult"></div>
</div>

<script>
  (function(){
    function bind(frm){
      var chk = frm.querySelector('input[name="enable_basic"]');
      var u = frm.querySelector('input[name="basic_user"]');
      var p = frm.querySelector('input[name="basic_pass"]');
      if (!chk || !u || !p) return;
      function update(){
        var on = chk.checked;
        u.disabled = !on; p.disabled = !on;
        if (!on) { u.required = false; p.required = false; }
        else { u.required = true; }
        var fields = frm.querySelectorAll('[data-basic-field]');
        fields.forEach(function(el){ el.style.display = on ? '' : 'none'; });
      }
      chk.addEventListener('change', update);
      update();
    }
    document.addEventListener('DOMContentLoaded', function(){
      var form = document.querySelector('form[action$="/account/save"]');
      if (form) bind(form);

      // Bind auth test buttons
      function showResult(obj){
        var box = document.getElementById('authTestResult');
        if (!box) return;
        var env = (obj && obj.testedEnv) || '';
        var label = env === 'sandbox' ? 'homologação' : env || 'ambiente';
        var html = '';
        if (obj && obj.status === 'ok'){
          html += '<div class="alert success">[OK] Conexão autenticada (' + (obj.http||0) + ') em ' + label + '.</div>';
          if (typeof obj.count === 'number') html += '<div class="muted">Clientes retornados: ' + obj.count + '</div>';
        } else if (obj) {
          var code = obj.errorCode ? (' - ' + obj.errorCode) : '';
          html += '<div class="alert danger">[ERRO] HTTP ' + (obj.http||0) + code + ': ' + (obj.message||'Falha') + '</div>';
          if (obj.advice) html += '<div class="muted">Dica: ' + obj.advice + '</div>';
        } else {
          html += '<div class="alert danger">Falha inesperada ao testar.</div>';
        }
        box.innerHTML = html;
      }

      function runTest(env){
        var sel = document.getElementById('accountId');
        var acc = sel && sel.value ? String(sel.value) : '';
        var url = '<?= $prefix ?>' + '/account/test-auth?env=' + encodeURIComponent(env) + (acc?('&account_id=' + encodeURIComponent(acc)):'');
        var box = document.getElementById('authTestResult');
        if (box) box.innerHTML = '<div class="muted">Testando ' + (env==='sandbox'?'homologação':'produção') + '...</div>';
        fetch(url, { headers: { 'Accept':'application/json' } })
          .then(function(r){ return r.json().catch(function(){ return null; }); })
          .then(function(obj){ showResult(obj); })
          .catch(function(){ showResult(null); });
      }

      document.querySelectorAll('[data-auth-test]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var env = this.getAttribute('data-auth-test') || 'production';
          runTest(env);
        });
      });
    });
  })();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
