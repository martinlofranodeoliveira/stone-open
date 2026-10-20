<?php ob_start(); ?>
<h1>Novo Usuário</h1>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/users/store">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <div class="grid cols-2 gap-lg">
      <label>Nome <a href="#" class="help-hint" data-help="user_name" title="Ajuda sobre nome do usuário"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-xl" type="text" name="name" required /></label>
      <label>Email <a href="#" class="help-hint" data-help="user_email" title="Ajuda sobre email"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-xl" type="email" name="email" required /></label>
      <label>Senha <a href="#" class="help-hint" data-help="user_password" title="Ajuda sobre senha"><i class="bi bi-question-circle"></i></a><br><input class="form-control w-md" type="password" name="password" required /></label>
      <label>Função <a href="#" class="help-hint" data-help="user_role" title="Ajuda sobre função/perfil"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="role">
          <?php $lab=['admin'=>'Administrador','operator'=>'Operador','viewer'=>'Leitor']; foreach(['admin','operator','viewer'] as $r): ?>
            <option value="<?= $r ?>"><?= $lab[$r] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status <a href="#" class="help-hint" data-help="user_status" title="Ajuda sobre status do usuário"><i class="bi bi-question-circle"></i></a><br>
        <select class="form-select w-md" name="status">
          <option value="active">Ativo</option>
          <option value="disabled">Desativado</option>
        </select>
      </label>
    </div>
    <div class="space"></div>
    <div class="flex">
      <button class="btn" type="submit">Criar</button>
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
  <p class="muted">Você pode alterar a função do usuário posteriormente na tela de edição.</p>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
