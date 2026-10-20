<?php ob_start(); ?>
<h1>Planos</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/plans">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['siteId']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels=['active'=>'Ativo','inactive'=>'Inativo']; foreach (['active','inactive'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filter['status']??'')===$st?'selected':'' ?>><?= $labels[$st] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="env" class="form-select" style="min-width: 150px;">
      <option value="">Todos ambientes</option>
      <option value="production" <?= ($filter['env']??'')==='production'?'selected':'' ?>>Produção</option>
      <option value="sandbox" <?= ($filter['env']??'')==='sandbox'?'selected':'' ?>>Sandbox</option>
    </select>
    <input type="text" class="form-control" name="q" placeholder="Nome ou Código" value="<?= htmlspecialchars($filter['q']??'') ?>" style="min-width: 200px;" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/plans"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn" href="<?= $prefix ?>/plans/create"><i class="bi bi-plus-lg"></i> Novo Plano</a>
  </form>
</div>
<div class="panel">
  <table id="tblPlans" class="table" data-url="<?= $prefix ?>/plans/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Ambiente</th><th>Nome</th><th>Código</th><th>Valor</th><th>Intervalo</th><th>Status</th><th>Stone</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($plans as $p): ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td><?= htmlspecialchars($p['site_name']) ?></td>
      <?php $env = (string)($p['env'] ?? 'sandbox'); $envLabel = $env === 'production' ? 'Produção' : 'Sandbox'; $envClass = $env === 'production' ? 'ok' : 'warn'; ?>
      <td><span class="badge <?= $envClass ?>"><?= $envLabel ?></span></td>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td class="muted"><?= htmlspecialchars($p['code']) ?></td>
      <td>R$ <?= number_format(((int)$p['amount_cents'])/100,2,',','.') ?></td>
      <td><?= htmlspecialchars($p['interval_human'] ?? ((int)$p['interval_count'] . ' / ' . (string)$p['interval_unit'])) ?></td>
      <?php $st=$p['status']; $stLabel = $st==='active'?'Ativo':($st==='inactive'?'Inativo':$st); $stClass = $st==='active'?'ok':($st==='inactive'?'warn':''); ?>
      <td><span class="badge <?= $stClass ?>"><?= htmlspecialchars($stLabel) ?></span></td>
      <td class="muted"><?= $p['stone_plan_id'] ? htmlspecialchars((string)$p['stone_plan_id']) : '—' ?></td>
      <td>
        <div class="action-grid">
        <a class="btn-icon primary" title="Editar" href="<?= $prefix ?>/plans/<?= (int)$p['id'] ?>"><i class="bi bi-pencil-square"></i></a>
        <form method="post" action="<?= $prefix ?>/plans/toggle" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? \App\Support\Security\Csrf::token()) ?>" />
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
          <?php if ($p['status']==='active'): ?>
            <button class="btn-icon warning" title="Desativar" data-confirm="Desativar este plano?"><i class="bi bi-pause-circle"></i></button>
          <?php else: ?>
            <button class="btn-icon success" title="Ativar" data-confirm="Ativar este plano?"><i class="bi bi-play-circle"></i></button>
          <?php endif; ?>
        </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<script>
  (function($){
    var $form = $('form[action$="/plans"]');
    var $tbl = $('#tblPlans');
    var csrfToken = '<?= htmlspecialchars($csrf ?? \App\Support\Security\Csrf::token()) ?>';
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.site_id=$form.find('[name=site_id]').val(); d.status=$form.find('[name=status]').val(); d.env=$form.find('[name=env]').val(); d.q=$form.find('[name=q]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'}, {data:'site_name'}, {data:'env', render:function(v){ var env=String(v||'sandbox'); var label= env==='production' ? 'Produção' : 'Sandbox'; var cls= env==='production' ? 'ok' : 'warn'; return '<span class="badge '+cls+'">'+label+'</span>'; }},
        {data:'name'}, {data:'code'},
        {data:'amount_cents', render:function(v){return 'R$ ' + (v/100).toFixed(2).replace('.',',');}},
        {data:'interval_desc'}, {data:'status', render:function(v){ var lab=(v==='active'?'Ativo':(v==='inactive'?'Inativo':v)); var cls=(v==='active'?'ok':(v==='inactive'?'warn':'')); return '<span class="badge '+cls+'">'+lab+'</span>'; }}, {data:'stone_plan_id'},
        {data:null, orderable:false, render:function(row){
          var btn = '<div class="action-grid"><a class="btn-icon primary" title="Editar" href="<?= $prefix ?>/plans/'+row.id+'"><i class="bi bi-pencil-square"></i></a> ';
          btn += '<form method="post" action="<?= $prefix ?>/plans/toggle" style="display:inline">'
             +'<input type="hidden" name="csrf" value="'+csrfToken+'" />'
             +'<input type="hidden" name="id" value="'+row.id+'" />';
          if (row.status==='active') {
            btn += '<button class="btn-icon warning" title="Desativar" data-confirm="Desativar este plano?"><i class="bi bi-pause-circle"></i></button>';
          } else {
            btn += '<button class="btn-icon success" title="Ativar" data-confirm="Ativar este plano?"><i class="bi bi-play-circle"></i></button>';
          }
          btn += '</form></div>';
          return btn;
        }}
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
