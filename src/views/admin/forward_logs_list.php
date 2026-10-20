<?php ob_start(); ?>
<h1>Logs de Forward</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/forward-logs">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['siteId']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="number" class="form-control" name="notification_id" placeholder="ID Notificação" value="<?= htmlspecialchars($filter['notificationId']??'') ?>" style="min-width: 150px;" />
    <select name="success" class="form-select" style="min-width: 120px;">
      <option value="">Todos</option>
      <option value="1" <?= ($filter['success']??'')==='1'?'selected':'' ?>>Sucesso</option>
      <option value="0" <?= ($filter['success']??'')==='0'?'selected':'' ?>>Falha</option>
    </select>
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/forward-logs"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/forward-logs/export?<?= http_build_query($filter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/forward-logs/export-xls?<?= http_build_query($filter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>
<div class="panel">
  <table id="tblForwardLogs" class="table" data-url="<?= $prefix ?>/forward-logs/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Notificação</th><th>Pagamento</th><th>Tentativa</th><th>HTTP</th><th>Sucesso</th><th>Enviado</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= (int)$l['id'] ?></td>
      <td title="<?= htmlspecialchars((string)($l['target_url'] ?? '')) ?>"><?= htmlspecialchars($l['site_name']) ?></td>
      <td><?= (int)$l['notification_id'] ?></td>
      <td><?= (int)$l['payment_id'] ?></td>
      
      <td><?= (int)$l['attempt'] ?></td>
      <td><?= (int)$l['response_status'] ?></td>
      <td><span class="badge <?= ((int)$l['success'])===1?'ok':'err' ?>"><?= ((int)$l['success'])===1?'Sim':'Não' ?></span></td>
      <?php $dt = !empty($l['sent_at']) ? date('d/m/Y H:i', strtotime((string)$l['sent_at'])) : ''; ?>
      <td><?= htmlspecialchars($dt) ?></td>
      <td class="right" style="white-space: nowrap;">
        <form method="post" action="<?= $prefix ?>/webhooks/resend" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="ids[]" value="<?= (int)$l['notification_id'] ?>" />
          <button class="btn-icon" title="Reentregar webhooks desta notificação"><i class="bi bi-arrow-clockwise"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<script>
  (function($){
    var $form = $('form[action$="/forward-logs"]');
    var $tbl = $('#tblForwardLogs');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';
    function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); }); }
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.site_id=$form.find('[name=site_id]').val(); d.notification_id=$form.find('[name=notification_id]').val(); d.success=$form.find('[name=success]').val(); d.from=$form.find('[name=from]').val(); d.to=$form.find('[name=to]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'},
        {data:null, render:function(row){ var name=row&&row.site_name? String(row.site_name):''; var url=row&&row.target_url? String(row.target_url):''; var $s=$('<span>').text(name); if(url){ $s.attr('title', url); } return $s.prop('outerHTML'); }},
        {data:'notification_id'},
        {data:'payment_id'},
        {data:'attempt'},
        {data:'response_status'},
        {data:'success', render:function(v){ var ok=(v===1||v==='1'||v===true); return '<span class="badge '+(ok?'ok':'err')+'">'+(ok?'Sim':'Não')+'</span>'; }},
        {data:'sent_at', render:function(v){ if(!v) return ''; var d=new Date(v); if(!isNaN(d)) return d.toLocaleString('pt-BR'); return v; }},
        {data:null, orderable:false, render:function(row){
          var html = '';
          html += '<div class="action-grid">';
          html += '<form method="post" action="<?= $prefix ?>/webhooks/resend" style="display:inline">'
               + '<input type="hidden" name="csrf" value="'+csrfToken+'" />'
               + '<input type="hidden" name="ids[]" value="'+row.notification_id+'" />'
               + '<button class="btn-icon info" title="Reentregar" data-confirm="Reentregar este webhook?"><i class="bi bi-arrow-clockwise"></i></button>'
               + '</form>';
          html += '</div>';
          return html; }
        }
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
