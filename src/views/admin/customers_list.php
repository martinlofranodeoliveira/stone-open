<?php use App\Helpers\Helper;

ob_start(); ?>
<h1>Clientes</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/customers">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['siteId']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="text" class="form-control" name="q" placeholder="Nome/Email/Documento" value="<?= htmlspecialchars($filter['q']??'') ?>" style="min-width: 250px;" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/customers"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/customers/export?<?= http_build_query($filter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/customers/export-xls?<?= http_build_query($filter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>
<div class="panel">
  <table id="tblCustomers" class="table" data-url="<?= $prefix ?>/customers/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Nome</th><th>Email</th><th>Documento</th><th>Criado</th></tr>
    </thead>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><?= htmlspecialchars($c['site_name']) ?></td>
      <td><?= htmlspecialchars((string)$c['name']) ?></td>
      <td class="muted"><?= htmlspecialchars((string)$c['email']) ?></td>
      <td class="muted"><?= htmlspecialchars((string)$c['document']) ?></td>
      <td><?= htmlspecialchars(Helper::formatDate2BR($c['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<script>
  (function($){
    var $form = $('form[action$="/customers"]');
    var $tbl = $('#tblCustomers');
    function fmtBrDate(v){
      if (!v) return '';
      var m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
      if (!m) return v;
      var y=m[1], mo=m[2], d=m[3], hh=m[4], mm=m[5];
      var out = d + '/' + mo + '/' + y;
      if (hh && mm) out += ' ' + hh + ':' + mm;
      return out;
    }
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.site_id=$form.find('[name=site_id]').val(); d.from=$form.find('[name=from]').val(); d.to=$form.find('[name=to]').val(); d.q=$form.find('[name=q]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'},
        {data:'site_name'},
        {data:'name'},
        {data:'email'},
        {data:'document'},
        {data:'created_at', render:function(v){ return fmtBrDate(v); }}
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
