<?php ob_start(); ?>
<h1>Logs da API</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/api-logs">
    <select class="form-select" name="site_id" style="min-width:200px">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= ($filter['site_id'] ?? null)==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" name="method" style="min-width:120px">
      <option value="">Método</option>
      <?php foreach (['GET','POST','PUT','PATCH','DELETE'] as $m): ?>
        <option value="<?= $m ?>" <?= ($filter['method']??'')===$m?'selected':'' ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" type="text" name="path" placeholder="Rota contém..." value="<?= htmlspecialchars($filter['path'] ?? '') ?>" style="min-width:240px" />
    <input class="form-control" type="number" name="status" placeholder="HTTP" value="<?= htmlspecialchars((string)($filter['status'] ?? '')) ?>" style="width:100px" />
    <input class="form-control" type="datetime-local" name="from" value="<?= htmlspecialchars($filter['from'] ?? '') ?>" />
    <input class="form-control" type="datetime-local" name="to" value="<?= htmlspecialchars($filter['to'] ?? '') ?>" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/api-logs"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <a class="btn" href="<?= $prefix ?>/api-logs/export?<?= http_build_query($filter) ?>"><i class="bi bi-download"></i> CSV</a>
    <a class="btn" href="<?= $prefix ?>/api-logs/export-xls?<?= http_build_query($filter) ?>"><i class="bi bi-download"></i> XLS</a>
  </form>
</div>

<div class="panel">
  <table id="tblApiLogs" class="table" data-url="<?= $prefix ?>/api-logs/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Método</th><th>Rota</th><th>HTTP</th><th>ms</th><th>IP</th><th>Quando</th></tr>
    </thead>
  </table>
</div>

<script>
  (function($){
    var $tbl = $('#tblApiLogs');
    var url = $tbl.data('url') + '?<?= http_build_query($filter) ?>';
    $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:url },
      order:[[0,'desc']],
      columns:[
        {data:'id'},
        {data:'site_name'},
        {data:'method'},
        {data:'path'},
        {data:'status'},
        {data:'duration_ms'},
        {data:'ip'},
        {data:'created_at'}
      ]
    });
  })(jQuery);
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';

