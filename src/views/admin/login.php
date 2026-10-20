<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
    <link id="favicon" rel="shortcut icon" href="/assets/images/speedometer.png?rand=<?= rand() ?>" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Admin</title>
  <link rel="stylesheet" href="/assets/bootstrap.min.css">
  <script>
    (function(){try{var t=localStorage.getItem('adminTheme');document.documentElement.setAttribute('data-theme', t||'light');}catch(e){document.documentElement.setAttribute('data-theme','light');}})();
  </script>
    <script src="/assets/sweetalert2.all.min.js"></script>
    <link href="/assets/sweetalert2.min.css" rel="stylesheet">

    <link rel="stylesheet" href="/assets/admin.css?Ver=<?= VERSAO_SISTEMA ?>" />
    <style>.full{width: 100%;}</style>
</head>
<body>
  <div class="container">
    <div class="panel" style="max-width:560px;margin:60px auto;">
      <div style="text-align:center;margin-bottom:18px;">
        <div style="font-weight:800;font-size:40px;line-height:1;color:#16a34a;letter-spacing:1px;">Sistema Pagamentos</div>
      </div>
      <?php if (!empty($error)): ?>
        <?php if ($error === 'csrf'): ?>
          <div class="badge err">Sessão expirada ou token inválido. Recarregue a página e tente novamente.</div>
        <?php elseif ($error === 'captcha'): ?>
          <div class="badge err">Captcha inválido. Atualize e tente novamente.</div>
        <?php else: ?>
          <div class="badge err">Credenciais inválidas</div>
        <?php endif; ?>
      <?php endif; ?>
      <form method="post" action="<?= $prefix ?>/login">
        <div class="space"></div>
        <div class="grid">
          <label>CPF ou E-mail<br><input class="full" type="email" name="email" placeholder="user@example.com" required /></label>
          <label>Senha<br><input class="full" type="password" name="password" placeholder="Informe sua senha de acesso" required /></label>
          <label>OTP (2FA, se habilitado)<br><input class="full" type="text" name="otp" inputmode="numeric" pattern="\d{6}" placeholder="000000" /></label>
          <div class="captcha-field">
            <label>Captcha</label>
            <div class="captcha-row">
              <div class="captcha-box">
                <img id="captchaImage" class="captcha-image" alt="Captcha" />
              </div>
              <button class="btn secondary btn-sm captcha-refresh" type="button" id="captchaRefresh">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Atualizar
              </button>
            </div>
            <input class="full" type="text" name="captcha" placeholder="Informe o captcha" autocomplete="off" autocapitalize="characters" maxlength="6" required />
            <div class="captcha-hint">Dica: Use letras em maiúsculas.</div>
          </div>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
        </div>
        <div class="space"></div>
        <div class="flex" style="justify-content:space-between;align-items:center;">
          <a href="#" class="btn secondary" onclick="Swal.fire({text:'Peça ao administrador para redefinir sua senha.', icon:'info'})">Esqueci minha senha</a>
          <button type="submit" class="btn">Entrar</button>
        </div>
      </form>
      <div class="space"></div>
    </div>
  </div>
  <script>
    (function(){
      var img = document.getElementById('captchaImage');
      var btn = document.getElementById('captchaRefresh');
      var spinner = btn ? btn.querySelector('.spinner-border') : null;
      function themeParam(){
        var t = document.documentElement.getAttribute('data-theme') || 'light';
        return (t === 'dark') ? 'dark' : 'light';
      }
      function refreshCaptcha(){
        if (!img) return;
        if (spinner) spinner.classList.remove('d-none');
        img.onload = function(){ if (spinner) spinner.classList.add('d-none'); };
        img.onerror = function(){ if (spinner) spinner.classList.add('d-none'); };
        img.src = '<?= $prefix ?>/captcha?theme=' + themeParam() + '&t=' + Date.now();
      }
      if (btn) btn.addEventListener('click', refreshCaptcha);
      refreshCaptcha();
    })();
  </script>
</body>
</html>
