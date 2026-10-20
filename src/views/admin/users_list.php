<?php ob_start(); ?>
<h1>Usuários do Admin</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/users">
    <input type="text" class="form-control" name="q" placeholder="Nome/Email" value="<?= htmlspecialchars($q??'') ?>" style="min-width: 250px;" />
    <select name="role" class="form-select" style="min-width: 150px;">
      <option value="">Todas as funções</option>
      <?php foreach(['admin','operator','viewer'] as $r): ?>
        <option value="<?= $r ?>" <?= ($role??'')===$r?'selected':'' ?>><?= $r ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php foreach(['active','disabled'] as $s): ?>
        <option value="<?= $s ?>" <?= ($status??'')===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/users"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <?php if (($currentRole ?? '') === 'admin'): ?>
      <a class="btn" href="<?= $prefix ?>/users/create"><i class="bi bi-plus-lg"></i> Novo Usuário</a>
    <?php endif; ?>
  </form>
</div>
<div class="panel">
  <table class="table">
    <tr><th>ID</th><th>Nome</th><th>Email</th><th>Função</th><th>Status</th><th>Criado</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td>
      <td><?= htmlspecialchars($u['name']) ?></td>
      <td class="muted"><?= htmlspecialchars($u['email']) ?></td>
      <td><?php $labR=['admin'=>'Administrador','operator'=>'Operador','viewer'=>'Leitor']; echo htmlspecialchars($labR[$u['role']] ?? (string)$u['role']); ?></td>
      <td><?php $labS=['active'=>'Ativo','disabled'=>'Desativado']; $st=$u['status']; ?><span class="badge <?= $st==='active'?'ok':'err' ?>"><?= htmlspecialchars($labS[$st] ?? (string)$st) ?></span></td>
      <td><?= htmlspecialchars($u['created_at']) ?></td>
      <td class="right" style="white-space: nowrap;">
        <div class="action-grid">
        <?php if (($currentRole ?? '') === 'admin'): ?>
          <a class="btn-icon primary" href="<?= $prefix ?>/users/<?= (int)$u['id'] ?>" title="Editar">
            <i class="bi bi-pencil"></i>
          </a>
          <form method="post" action="<?= $prefix ?>/users/toggle" style="display:inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
            <button class="btn-icon <?= $u['status']==='active'?'warning':'success' ?>" type="submit" title="<?= $u['status']==='active'?'Bloquear':'Desbloquear' ?>" data-confirm="<?= $u['status']==='active'?'Desativar este usuário?':'Ativar este usuário?' ?>">
              <i class="bi bi-<?= $u['status']==='active'?'lock-fill':'unlock-fill' ?>"></i>
            </button>
          </form>
        <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
