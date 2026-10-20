<?php ob_start(); ?>
<h1>Logs de Acesso</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/access-logs">
    <select class="form-select" name="user_id" style="min-width: 200px;">
      <option value="">Todos usuários</option>
      <?php foreach ($users as $u): ?>
      <option value="<?= (int)$u['id'] ?>" <?= ((int)($filter['userId']??0)===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" type="text" name="ip" placeholder="IP" value="<?= htmlspecialchars($filter['ip']??'') ?>" style="min-width: 150px;" />
    <input class="form-control" type="text" name="path" placeholder="Rota contém" value="<?= htmlspecialchars($filter['path']??'') ?>" style="min-width: 250px;" />
    <input class="form-control" type="date" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" />
    <input class="form-control" type="date" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/access-logs"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/access-logs/export?<?= http_build_query($filter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
  </form>
</div>
<div class="panel">
  <table id="tblAccessLogs" class="table" data-url="<?= $prefix ?>/access-logs/data">
    <thead>
      <tr><th>ID</th><th>Usuário</th><th>Método</th><th>Rota</th><th>IP</th><th>Request ID</th><th>Quando</th></tr>
    </thead>
  </table>
</div>
<script>
  (function($){
    var $form = $('form[action$="/access-logs"]');
    var $tbl = $('#tblAccessLogs');
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.user_id=$form.find('[name=user_id]').val(); d.ip=$form.find('[name=ip]').val(); d.path=$form.find('[name=path]').val(); d.from=$form.find('[name=from]').val(); d.to=$form.find('[name=to]').val(); }},
      order:[[0,'desc']],
      columns:[{data:'id'},{data:'user_name', defaultContent:''},{data:'method'},{data:'path'},{data:'ip'},{data:'request_id', defaultContent:''},{data:'created_at'}]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
