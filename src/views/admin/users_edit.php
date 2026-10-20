<?php ob_start(); ?>
<h1>Editar Usuário</h1>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/users/update">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>" />
    <div class="grid cols-2 gap-lg">
      <label>Nome <a href="#" class="help-hint" data-help="user_name" title="Ajuda sobre nome do usuário"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-xl" type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required /></label>
      <label>Email <a href="#" class="help-hint" data-help="user_email" title="Ajuda sobre email"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-xl" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required /></label>
      <label>Nova Senha (opcional) <a href="#" class="help-hint" data-help="user_password" title="Ajuda sobre senha"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-md" type="password" name="password" placeholder="Deixe em branco p/ manter" /></label>
      <label>Função <a href="#" class="help-hint" data-help="user_role" title="Ajuda sobre função/perfil"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="role">
          <?php $lab=['admin'=>'Administrador','operator'=>'Operador','viewer'=>'Leitor']; foreach(['admin','operator','viewer'] as $r): ?>
            <option value="<?= $r ?>" <?= $user['role']===$r?'selected':'' ?>><?= $lab[$r] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status <a href="#" class="help-hint" data-help="user_status" title="Ajuda sobre status do usuário"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="status">
          <?php $labS=['active'=>'Ativo','disabled'=>'Desativado']; foreach(['active','disabled'] as $s): ?>
            <option value="<?= $s ?>" <?= $user['status']===$s?'selected':'' ?>><?= $labS[$s] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Salvar</button>
      <a class="btn secondary" href="<?= $prefix ?>/users">Voltar</a>
    </div>
  </form>
</div>
<div class="panel">
  <h3>Perfis de acesso</h3>
  <ul>
    <li><b>Administrador</b>: acesso total ao Admin. Pode gerenciar usuários, planos, sites e configurações.</li>
    <li><b>Operador</b>: acesso operacional (leituras e ações do dia a dia), porém sem gerenciar usuários.</li>
    <li><b>Leitor</b>: somente leitura em todo o Admin.</li>
  </ul>
  <p class="muted">Altere a função acima conforme a responsabilidade deste usuário.</p>
</div>

<div class="panel">
  <h3>Autenticação de Dois Fatores (2FA)</h3>
  <?php if (!empty($user['twofa_secret'])): ?>
    <div class="muted">2FA está habilitado para este usuário.</div>
    <form method="post" action="<?= $prefix ?>/users/2fa/disable" style="margin-top:10px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="id" value="<?= (int)$user['id'] ?>" />
      <button class="btn danger" type="submit">Desabilitar 2FA</button>
    </form>
  <?php else: ?>
    <div class="muted">2FA está desabilitado. Habilite para maior segurança.</div>
    <form method="post" action="<?= $prefix ?>/users/2fa/enable" id="formEnable2FA" style="margin-top:10px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="id" value="<?= (int)$user['id'] ?>" />
      <button class="btn" type="submit">Habilitar 2FA</button>
    </form>
    <!-- Modal 2FA -->
    <div class="modal fade" id="modal2FA" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">
          <div class="modal-header" style="border-color:var(--border)">
            <h5 class="modal-title">Configurar 2FA</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Escaneie o QR Code no seu autenticador (Google Authenticator, Authy, etc.). Depois, digite o código de 6 dígitos para validar.</p>
            <div class="flex" style="gap:20px;align-items:center;justify-content:flex-start;">
              <div id="qrcodeBox" style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;background:var(--bg);border:1px solid var(--border);border-radius:8px;"></div>
              <div>
                <div class="muted" style="font-size:0.9em">Chave (manual): <code id="twofaSecret"></code></div>
                <div class="muted" style="font-size:0.9em;word-break:break-all">URI: <code id="otpauthUri"></code></div>
              </div>
            </div>
            <div class="space"></div>
            <form id="formVerify2FA" method="post" action="<?= $prefix ?>/users/2fa/verify">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
              <input type="hidden" name="id" value="<?= (int)$user['id'] ?>" />
              <label>Código (OTP)<br><input class="form-control w-sm" type="text" name="otp" inputmode="numeric" pattern="\d{6}" placeholder="000000" required /></label>
              <div class="space"></div>
              <button class="btn" type="submit">Validar</button>
            </form>
            <div id="twofaMsg" class="muted" style="margin-top:8px"></div>
          </div>
          <div class="modal-footer" style="border-color:var(--border)">
            <button type="button" class="btn secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
  (function(){
    var frmEnable = document.getElementById('formEnable2FA');
    if (frmEnable) {
      frmEnable.addEventListener('submit', function(e){
        e.preventDefault();
        var btn = frmEnable.querySelector('button[type="submit"]'); if (btn) btn.disabled = true;
        fetch(frmEnable.action, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'}, body: new URLSearchParams(new FormData(frmEnable)).toString(), credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(obj){
            if (!obj || !obj.ok) throw new Error(obj && obj.error || 'Falha ao habilitar 2FA');
            // Fill modal
            document.getElementById('twofaSecret').textContent = obj.secret || '';
            document.getElementById('otpauthUri').textContent = obj.otpauth_uri || '';
            var box = document.getElementById('qrcodeBox'); box.innerHTML = '';
            var uri = obj.otpauth_uri || '';
            try { uri = decodeURIComponent(uri); } catch(e) {}
            // Generate QR locally; keep the manual setup available if rendering fails.
            var rendered = false;
            if (window.QRCode) {
              try { new QRCode(box, { text: uri, width: 200, height: 200 }); } catch(e) {}
              rendered = !!(box.querySelector('canvas') || box.querySelector('img'));
            }
            if (!rendered) box.textContent = 'Use a chave ou URI ao lado.';
            var modal = new bootstrap.Modal(document.getElementById('modal2FA'));
            modal.show();
          })
          .catch(function(err){ if (window.Swal) Swal.fire({text:String(err), icon:'error'}); else console.error(err); })
          .finally(function(){ if (btn) btn.disabled = false; });
      });
    }
    var frmVerify = document.getElementById('formVerify2FA');
    if (frmVerify){
      frmVerify.addEventListener('submit', function(e){
        e.preventDefault();
        var btn = frmVerify.querySelector('button[type="submit"]'); if (btn) btn.disabled = true;
        var msg = document.getElementById('twofaMsg'); msg.textContent='';
        fetch(frmVerify.action, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'}, body: new URLSearchParams(new FormData(frmVerify)).toString(), credentials:'same-origin'}).
          then(function(r){ return r.json(); }).
          then(function(obj){
            if (obj && obj.ok){
              msg.textContent = 'Código validado! Recarregando...';
              setTimeout(function(){ window.location.reload(); }, 600);
            } else {
              msg.textContent = 'Código inválido. Tente novamente.';
            }
          }).
          catch(function(err){ if (window.Swal) Swal.fire({text:String(err), icon:'error'}); else console.error(err); }).
          finally(function(){ if (btn) btn.disabled = false; });
      });
    }
  })();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
