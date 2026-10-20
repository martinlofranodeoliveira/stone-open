<?php ob_start(); ?>
    <div class="mb-3">
        <h1 class="mb-1">Relatórios</h1>
        <p class="text-muted">Gere gráficos, exportações e previsões por período, site e tipo.</p>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link btn <?= $scope === 'overview' ? 'btn-primary' : 'btn-outline' ?>"
               href="<?= $prefix ?>/reports?<?= http_build_query(['scope' => 'overview', 'site_id' => $siteId, 'from' => $from, 'to' => $to]) ?>">Pagamentos:
                Cartão, PIX e Recorrente</a>
        </li>
        <li class="nav-item">
            <a class="nav-link btn <?= $scope === 'payments' ? 'btn-primary' : 'btn-outline' ?>"
               href="<?= $prefix ?>/reports?<?= http_build_query(['scope' => 'payments', 'method' => $method, 'site_id' => $siteId, 'from' => $from, 'to' => $to, 'proj_days' => $projDays ?? 30]) ?>">Pagamentos</a>
        </li>
        <li class="nav-item">
            <a class="nav-link btn <?= $scope === 'boletos' ? 'btn-primary' : 'btn-outline' ?>"
               href="<?= $prefix ?>/reports?<?= http_build_query(['scope' => 'boletos', 'site_id' => $siteId, 'from' => $from, 'to' => $to]) ?>">Boletos</a>
        </li>
        <li class="nav-item">
            <a class="nav-link btn <?= $scope === 'subscriptions' ? 'btn-primary' : 'btn-outline' ?>"
               href="<?= $prefix ?>/reports?<?= http_build_query(['scope' => 'subscriptions', 'site_id' => $siteId, 'from' => $from, 'to' => $to, 'proj_days' => $projDays ?? 30]) ?>">Assinaturas</a>
        </li>
    </ul>

    <form class="panel mb-4" method="get" action="<?= $prefix ?>/reports">
        <div class="cols-6 gap-lg row">
            <div class="col-sm-12 col-md-2">
                <label class="form-label">Tipo</label>
                <select name="scope" class="form-select" onchange="toggleMethod(this.value)">
                    <option value="overview" <?= $scope === 'overview' ? 'selected' : '' ?>>Cartão, PIX e Recorrente
                    </option>
                    <option value="payments" <?= $scope === 'payments' ? 'selected' : '' ?>>Pagamentos</option>
                    <option value="boletos" <?= $scope === 'boletos' ? 'selected' : '' ?>>Boletos</option>
                    <option value="subscriptions" <?= $scope === 'subscriptions' ? 'selected' : '' ?>>Assinaturas
                    </option>
                </select>
            </div>
            <div class="col-sm-12 col-md-2" id="methodWrap" <?= $scope === 'payments' ? '' : 'style="display:none"' ?>>
                <label class="form-label">Pagamento</label>
                <select name="method" class="form-select">
                    <option value="" <?= $method === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="credit_card" <?= $method === 'credit_card' ? 'selected' : '' ?>>Cartão de Crédito
                    </option>
                    <option value="pix" <?= $method === 'pix' ? 'selected' : '' ?>>Pix</option>
                    <option value="boleto" <?= $method === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                </select>
            </div>
            <div class="col-sm-12 col-md-2">
                <label class="form-label">Site</label>
                <select name="site_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($sites as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ((int)$siteId) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? ('Site #' . (int)$s['id']), ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-12 col-md-2">
                <label class="form-label">De</label>
                <input type="date" class="form-control" name="from"
                       value="<?= htmlspecialchars($from ?: date('Y-m-d', strtotime('-30 days')), ENT_QUOTES) ?>">
            </div>
            <div class="col-sm-12 col-md-2">
                <label class="form-label">Até</label>
                <input type="date" class="form-control" name="to"
                       value="<?= htmlspecialchars($to ?: date('Y-m-d'), ENT_QUOTES) ?>">
            </div>
            <div class="col-sm-12 col-md-2">
                <label class="form-label">Proj. (dias)</label>
                <input type="number" min="1" max="180" class="form-control" name="proj_days"
                       value="<?= (int)($projDays ?? 30) ?>">
            </div>
        </div>
        <div class="mt-3 flex gap-sm">
            <button class="btn primary" type="submit"><i class="bi bi-funnel me-1"></i> Aplicar Filtros</button>
            <?php if ($scope !== 'overview'): ?>
                <a class="btn secondary"
                   href="<?= $prefix ?>/reports/export?<?= http_build_query(['scope' => $scope, 'method' => $method, 'site_id' => $siteId, 'from' => $from, 'to' => $to]) ?>"
                   target="_blank"><i class="bi bi-filetype-csv me-1"></i> Exportar CSV</a>
                <a class="btn secondary"
                   href="<?= $prefix ?>/reports/export-xls?<?= http_build_query(['scope' => $scope, 'method' => $method, 'site_id' => $siteId, 'from' => $from, 'to' => $to]) ?>"
                   target="_blank"><i class="bi bi-file-earmark-excel me-1"></i> Exportar XLS</a>
                <a class="btn secondary"
                   href="<?= $prefix ?>/reports/export?<?= http_build_query(['scope' => $scope, 'method' => $method, 'site_id' => $siteId, 'from' => $from, 'to' => $to, 'proj_days' => $projDays, 'export' => 'forecast']) ?>"
                   target="_blank"><i class="bi bi-graph-up-arrow me-1"></i> Forecast CSV</a>
                <a class="btn secondary"
                   href="<?= $prefix ?>/reports/export-xls?<?= http_build_query(['scope' => $scope, 'method' => $method, 'site_id' => $siteId, 'from' => $from, 'to' => $to, 'proj_days' => $projDays, 'export' => 'forecast']) ?>"
                   target="_blank"><i class="bi bi-graph-up-arrow me-1"></i> Forecast XLS</a>
            <?php endif; ?>
            <button class="btn secondary" type="button" id="exportAllPng"><i class="bi bi-image me-1"></i> Exportar
                Gráficos (PNG)
            </button>
        </div>
    </form>

<?php if ($scope === 'overview'): ?>
    <div class="panel mb-4">
        <div class="flex flex-between mb-3">
            <h3 style="margin:0">Receita por Dia (Cartão, PIX, Recorrente)</h3>
            <div class="text-muted" style="font-size:0.9em">Período selecionado</div>
        </div>
        <div style="position:relative;height:340px">
            <canvas id="rptOverviewRev"></canvas>
        </div>
        <div class="mt-2">
            <button class="btn small secondary" data-export="rptOverviewRev">Baixar PNG</button>
        </div>
    </div>
    <div class="panel mb-4">
        <h3 class="mb-2">Como calculamos a previsão</h3>
        <ul class="text-muted" style="margin:0;padding-left:1.2rem">
            <li>Pix e Cartão: somamos os pagamentos com status “paid” no período selecionado (por data de atualização),
                dividimos pelo número de dias do período (inclusive) para obter a média diária e multiplicamos por <?= (int)($projDays ?? 30) ?> dias.</li>
            <li>Assinaturas: usamos o MRR das assinaturas ativas; a média diária é MRR/30 e a projeção é a diária multiplicada por <?= (int)($projDays ?? 30) ?> dias.</li>
            <li>A previsão é uma extrapolação simples da média diária; não considera sazonalidade, finais de semana, churn futuro nem upgrades/downgrades.</li>
        </ul>
    </div>
    <div class="grid cols-3 gap-lg">
        <div class="panel">
            <h3 class="mb-2">Previsão de Faturamento (Pix)</h3>
            <?php $fovPix = $data['forecast']['pix'] ?? ['total_cents' => 0]; ?>
            <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base período selecionado)</div>
            <div class="value" style="font-size:1.6em">R$ <?= number_format(((int)($fovPix['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        </div>
        <div class="panel">
            <h3 class="mb-2">Previsão de Faturamento (Cartão)</h3>
            <?php $fovCred = $data['forecast']['credit_card'] ?? ['total_cents' => 0]; ?>
            <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base período selecionado)</div>
            <div class="value" style="font-size:1.6em">R$ <?= number_format(((int)($fovCred['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        </div>
        <div class="panel">
            <h3 class="mb-2">Previsão de Faturamento (Assinaturas)</h3>
            <?php $fovRec = $data['forecast']['rec'] ?? ['total_cents' => 0]; ?>
            <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base MRR/30)</div>
            <div class="value" style="font-size:1.6em">R$ <?= number_format(((int)($fovRec['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        </div>
    </div>

    <div class="grid cols-3 gap-lg mb-4">
        <div class="panel col-span-2">
            <div class="flex flex-between mb-4">
                <h3 style="margin:0">Receita, Transações e Aprovação</h3>
                <div class="text-muted" style="font-size:0.9em">Período selecionado</div>
            </div>
            <div style="position:relative;height:340px">
                <canvas id="rptRevenue"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptRevenue">Baixar PNG</button>
            </div>
        </div>
        <div class="panel">
            <h3 class="mb-3">Métodos de Pagamento</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptMethods"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptMethods">Baixar PNG</button>
            </div>
            <div class="space"></div>
            <h3 class="mb-3">Status</h3>
            <div style="position:relative;height:220px">
                <canvas id="rptStatus"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptStatus">Baixar PNG</button>
            </div>
        </div>
    </div>
<?php elseif ($scope === 'payments'): ?>
    <div class="grid cols-2 gap-lg mb-4">
        <div class="panel">
            <h3 class="mb-2">Previsão de Faturamento (Pix)</h3>
            <?php $f = $data['forecast']['pix'] ?? ['total_cents' => 0, 'by_site' => []]; ?>
            <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base período selecionado)</div>
            <div class="value" style="font-size:1.6em">
                R$ <?= number_format(((int)($f['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        </div>
        <div class="panel">
            <h3 class="mb-2">Previsão de Faturamento (Cartão)</h3>
            <?php $fc = $data['forecast']['credit_card'] ?? ['total_cents' => 0, 'by_site' => []]; ?>
            <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base período selecionado)</div>
            <div class="value" style="font-size:1.6em">
                R$ <?= number_format(((int)($fc['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="panel mb-4">
        <h3 class="mb-2">Como calculamos a previsão (Pagamentos)</h3>
        <ul class="text-muted" style="margin:0;padding-left:1.2rem">
            <li>Base: soma de pagamentos “paid” no período selecionado (por data de atualização), filtrando por site e método quando aplicável.</li>
            <li>Média diária = total do período ÷ número de dias do período (inclusive).</li>
            <li>Projeção = média diária × <?= (int)($projDays ?? 30) ?> (campo “Proj. (dias)”).</li>
            <li>Observação: inclui pagamentos recorrentes caso existam; a taxa de aprovação não entra na projeção, apenas nos gráficos.</li>
        </ul>
    </div>

    <div class="grid cols-3 gap-lg mb-4">
        <div class="panel">
            <h3 class="mb-3">Comparação Pix x Cartão (Receita por dia)</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptPixVsCard"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptPixVsCard">Baixar PNG</button>
            </div>
        </div>
        <div class="panel col-span-2">
            <div class="flex flex-between mb-4">
                <h3 style="margin:0">Receita, Transações e Aprovação</h3>
                <div class="text-muted" style="font-size:0.9em">Período selecionado</div>
            </div>
            <div style="position:relative;height:340px">
                <canvas id="rptRevenue"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptRevenue">Baixar PNG</button>
            </div>
        </div>
    </div>

    <div class="grid cols-3 gap-lg mb-4">
        <div class="panel">
            <h3 class="mb-3">Métodos de Pagamento</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptMethods"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptMethods">Baixar PNG</button>
            </div>
        </div>
        <div class="panel">
            <h3 class="mb-3">Status</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptStatus"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptStatus">Baixar PNG</button>
            </div>
        </div>
        <div class="panel">
            <h3 class="mb-3">Top 5 Sites por Receita</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptTopSites"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptTopSites">Baixar PNG</button>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-between mb-3">
            <h3 style="margin:0">Lista de Pagamentos (100 mais recentes)</h3>
            <a class="btn small"
               href="<?= $prefix ?>/payments?<?= http_build_query(['site_id' => $siteId, 'type' => $method, 'from' => $from, 'to' => $to]) ?>">Abrir
                lista completa</a>
        </div>
        <div style="overflow:auto;">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Site</th>
                    <th>Ref</th>
                    <th>Valor</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Criado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($data['list'] ?? []) as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['site_name'] ?? ('Site #' . (int)$r['site_id']), ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($r['client_reference_id'] ?? '', ENT_QUOTES) ?></td>
                        <td>R$ <?= number_format(((int)$r['amount_cents']) / 100, 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($r['payment_method_type'] ?? '', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars(substr((string)$r['created_at'], 0, 19), ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
<?php elseif ($scope === 'boletos'): ?>
    <div class="panel mb-4">
        <div class="flex flex-between mb-4">
            <h3 style="margin:0">Receita, Transações e Aprovação (Boletos)</h3>
            <div class="text-muted" style="font-size:0.9em">Período selecionado</div>
        </div>
        <div style="position:relative;height:340px">
            <canvas id="rptBoletoRevenue"></canvas>
        </div>
        <div class="mt-2">
            <button class="btn small secondary" data-export="rptBoletoRevenue">Baixar PNG</button>
        </div>
    </div>
    <div class="grid cols-2 gap-lg mb-4">
        <div class="panel">
            <h3 class="mb-3">Status</h3>
            <div style="position:relative;height:260px">
                <canvas id="rptBoletoStatus"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptBoletoStatus">Baixar PNG</button>
            </div>
        </div>
        <div class="panel">
            <h3 class="mb-3">Últimos Boletos</h3>
            <div style="overflow:auto;">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Site</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Criado</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($data['list'] ?? []) as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['site_name'] ?? ('Site #' . (int)$r['site_id']), ENT_QUOTES) ?></td>
                            <td>R$ <?= number_format(((int)$r['amount_cents']) / 100, 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars(substr((string)$r['created_at'], 0, 19), ENT_QUOTES) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel mb-4">
        <h3 class="mb-2">Previsão (Assinaturas)</h3>
        <?php $fs = $data['forecast']['subs'] ?? ['total_cents' => 0, 'by_site' => []]; ?>
        <div class="text-muted">Próximos <?= (int)($projDays ?? 30) ?> dias (base MRR / 30)</div>
        <div class="value" style="font-size:1.8em">
            R$ <?= number_format(((int)($fs['total_cents'] ?? 0)) / 100, 2, ',', '.') ?></div>
        <div class="space"></div>
        <div style="max-height:260px;overflow:auto;">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>Site</th>
                    <th>MRR</th>
                    <th>Diária Média</th>
                    <th>Previsto</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice(($fs['by_site'] ?? []), 0, 10) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['site_name'], ENT_QUOTES) ?></td>
                        <td>R$ <?= number_format(((int)$row['mrr_cents']) / 100, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format(((int)$row['daily_avg_cents']) / 100, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format(((int)$row['forecast_cents']) / 100, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel mb-4">
        <h3 class="mb-2">Como calculamos a previsão (Assinaturas)</h3>
        <ul class="text-muted" style="margin:0;padding-left:1.2rem">
            <li>Partimos do MRR somado de assinaturas com status “active”.</li>
            <li>Média diária = MRR ÷ 30.</li>
            <li>Projeção = média diária × <?= (int)($projDays ?? 30) ?> (campo “Proj. (dias)”).</li>
            <li>Limitações: não antecipa novos contratos, cancelamentos (churn) ou mudanças de plano durante o período projetado.</li>
        </ul>
    </div>

    <div class="grid cols-3 gap-lg mb-4">
        <div class="panel col-span-2">
            <div class="flex flex-between mb-4">
                <h3 style="margin:0">Novas Assinaturas</h3>
                <div class="text-muted" style="font-size:0.9em">Período selecionado</div>
            </div>
            <div style="position:relative;height:280px">
                <canvas id="rptSubsNew"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptSubsNew">Baixar PNG</button>
            </div>
        </div>
        <div class="panel">
            <h3 class="mb-3">Assinaturas: Ativas x Atrasadas</h3>
            <div style="position:relative;height:240px">
                <canvas id="rptSubsAp"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptSubsAp">Baixar PNG</button>
            </div>
            <div class="space"></div>
            <h3 class="mb-3">Status</h3>
            <div style="position:relative;height:220px">
                <canvas id="rptSubsStatus"></canvas>
            </div>
            <div class="mt-2">
                <button class="btn small secondary" data-export="rptSubsStatus">Baixar PNG</button>
            </div>
        </div>
    </div>

    <div class="panel mb-4">
        <h3 class="mb-3">Cancelamentos por dia</h3>
        <div style="position:relative;height:240px">
            <canvas id="rptSubsCancel"></canvas>
        </div>
        <div class="mt-2">
            <button class="btn small secondary" data-export="rptSubsCancel">Baixar PNG</button>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-between mb-3">
            <h3 style="margin:0">Lista de Assinaturas (100 mais recentes)</h3>
            <a class="btn small"
               href="<?= $prefix ?>/subscriptions?<?= http_build_query(['site_id' => $siteId, 'from' => $from, 'to' => $to]) ?>">Abrir
                lista completa</a>
        </div>
        <div style="overflow:auto;">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Site</th>
                    <th>Plano</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Criado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($data['list'] ?? []) as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['site_name'] ?? ('Site #' . (int)$r['site_id']), ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars((string)($r['plan_id'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= $r['amount_cents'] !== null ? ('R$ ' . number_format(((int)$r['amount_cents']) / 100, 2, ',', '.')) : '-' ?></td>
                        <td><?= htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars(substr((string)$r['created_at'], 0, 19), ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

    <script src="/assets/chart.js"></script>
    <script>
        function toggleMethod(scope) {
            document.getElementById('methodWrap').style.display = (scope === 'payments') ? '' : 'none';
        }

        (function () {
            const fmtInt = new Intl.NumberFormat('pt-BR');
            const fmtCur = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
            const fmtDateTitle = function (l) {
                if (!l) return '';
                if (/^\d{4}-\d{2}-\d{2}$/.test(l)) {
                    var parts = l.split('-');
                    var y = parts[0], m = parts[1], d = parts[2];
                    return d + '/' + m + '/' + y;
                }
                return l;
            };
            const theme = getComputedStyle(document.documentElement);
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = theme.getPropertyValue('--txt-sec').trim();
            Chart.defaults.scale.grid.borderColor = theme.getPropertyValue('--border').trim();
            Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';
            // Increase pixel ratio to export wider/crisper PNGs
            Chart.defaults.devicePixelRatio = 2;

            // Helper: suggested max based on 95th percentile to evitar "explodir" eixo
            function suggestedMax(arr) {
                var v = (arr || []).filter(function (n) {
                    return typeof n === 'number';
                });
                if (!v.length) return undefined;
                var sorted = v.slice().sort(function (a, b) {
                    return a - b;
                });
                var idx = Math.floor(0.95 * (sorted.length - 1));
                var q95 = sorted[idx] || 0;
                return q95 * 1.2 || undefined;
            }

            const scope = <?= json_encode($scope) ?>;
            const reportData = JSON.parse(<?= json_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>);
            // Some environments may inject the full view context instead of only $data.
            // Normalize a root object so charts always find expected keys.
            const root = (reportData && (reportData.series || reportData.byStatus || reportData.topSites || typeof reportData.active !== 'undefined'))
                ? reportData
                : ((reportData && reportData.data) ? reportData.data : {});

            function downloadPng(id) {
                const c = document.getElementById(id);
                if (!c) return;
                const url = c.toDataURL('image/png', 1.0);
                const a = document.createElement('a');
                a.href = url;
                a.download = id + '.png';
                a.click();
            }

            document.getElementById('exportAllPng').addEventListener('click', function () {
                var list = document.querySelectorAll('button[data-export]');
                Array.prototype.forEach.call(list, function (btn) {
                    downloadPng(btn.getAttribute('data-export'));
                });
            });
            var list2 = document.querySelectorAll('button[data-export]');
            Array.prototype.forEach.call(list2, function (btn) {
                btn.addEventListener('click', function () {
                    downloadPng(btn.getAttribute('data-export'));
                });
            });

            if (scope === 'overview') {
                const s = root && root.series ? root.series : {
                    days: [],
                    card_revenue: [],
                    pix_revenue: [],
                    rec_revenue: []
                };
                const days = s.days || [];
                const card = (s.card_revenue || []).map(n => (Number(n) || 0) / 100);
                const pix = (s.pix_revenue || []).map(n => (Number(n) || 0) / 100);
                const rec = (s.rec_revenue || []).map(n => (Number(n) || 0) / 100);
                const ctx = document.getElementById('rptOverviewRev').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: days, datasets: [
                            {
                                label: 'Cartão',
                                data: card,
                                borderColor: '#0d6efd',
                                backgroundColor: 'rgba(13,110,253,.1)',
                                tension: .2
                            },
                            {
                                label: 'PIX',
                                data: pix,
                                borderColor: '#198754',
                                backgroundColor: 'rgba(25,135,84,.1)',
                                tension: .2
                            },
                            {
                                label: 'Recorrente',
                                data: rec,
                                borderColor: '#6f42c1',
                                backgroundColor: 'rgba(111,66,193,.1)',
                                tension: .2
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {y: {beginAtZero: true, suggestedMax: suggestedMax(card.concat(pix, rec))}}
                    }
                });
                // Combined: Receita, Transações e Aprovação (unificado)
                const countsAll = (s.all_counts || []).map(n => Number(n) || 0);
                const revenueAll = (s.all_revenue || []).map(n => (Number(n) || 0) / 100);
                const paidCounts = (s.all_paid_counts || []).map(n => Number(n) || 0);
                const apprAll = countsAll.map((c, i) => c > 0 ? (paidCounts[i] / c) * 100 : 0);
                const ctxR = document.getElementById('rptRevenue').getContext('2d');
                new Chart(ctxR, {
                    data: {
                        labels: days,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Receita (R$)',
                                data: revenueAll,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,0.12)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true,
                                yAxisID: 'y'
                            },
                            {
                                type: 'bar',
                                label: 'Transações',
                                data: countsAll,
                                backgroundColor: '#6366f1',
                                borderRadius: 4,
                                yAxisID: 'y1'
                            },
                            {
                                type: 'line',
                                label: 'Aprovação (%)',
                                data: apprAll,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,0.12)',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                tension: 0.3,
                                yAxisID: 'y2'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {position: 'top'},
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var label = ctx.dataset.label || '';
                                        var y = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : 0;
                                        if (ctx.dataset.yAxisID === 'y') return label + ': ' + fmtCur.format(y);
                                        if (ctx.dataset.yAxisID === 'y2') return label + ': ' + (y.toFixed(1)) + '%';
                                        return label + ': ' + fmtInt.format(y);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {grid: {display: false}},
                            y: { position: 'left', ticks: { callback: v => fmtCur.format(v) }, suggestedMax: suggestedMax(revenueAll) },
                            y1: { position: 'right', grid: {drawOnChartArea: false}, ticks: { callback: v => fmtInt.format(v) }, suggestedMax: suggestedMax(countsAll) },
                            y2: { position: 'right', grid: {display: false}, ticks: { callback: v => (typeof v === 'number' ? v.toFixed(0) : v) + '%' }, suggestedMax: 100 }
                        }
                    }
                });

                // Métodos (Pix, Cartão, Assinaturas)
                const methodMapOv = { 'credit_card': 'Cartão de Crédito', 'pix': 'Pix', 'rec': 'Assinaturas', 'unknown': 'Desconhecido' };
                const byMethodLabelsOv = (root.byMethod || []).map(r => methodMapOv[(r.m || 'unknown')] || (r.m || 'Desconhecido'));
                const byMethodCountsOv = (root.byMethod || []).map(r => Number(r.c) || 0);
                const ctxM = document.getElementById('rptMethods').getContext('2d');
                new Chart(ctxM, {
                    type: 'doughnut',
                    data: { labels: byMethodLabelsOv, datasets: [{ data: byMethodCountsOv, backgroundColor: ['#6366f1', '#10b981', '#6f42c1'], borderWidth: 0 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right' } } }
                });

                // Status unificado
                const statusMap = { 'paid': 'Pago', 'pending': 'Pendente', 'processing': 'Processando', 'authorized': 'Autorizado', 'canceled': 'Cancelado', 'failed': 'Falhou', 'refunded': 'Reembolsado', 'chargeback': 'Chargeback', 'created': 'Criado' };
                var byS = root.byStatus || [];
                var statusOrder = ['created', 'pending', 'processing', 'authorized', 'paid', 'refunded', 'chargeback', 'canceled', 'failed'];
                var countsMap = {};
                byS.forEach(function (r) { countsMap[(r.s || '').toLowerCase()] = Number(r.c) || 0; });
                var byStatusLabels = [], byStatusCounts = [];
                statusOrder.forEach(function (k) { if (typeof countsMap[k] !== 'undefined') { byStatusLabels.push(statusMap[k] || k); byStatusCounts.push(countsMap[k]); } });
                var ctxS = document.getElementById('rptStatus').getContext('2d');
                new Chart(ctxS, { type: 'bar', data: { labels: byStatusLabels, datasets: [{ label: 'Pagamentos', data: byStatusCounts, backgroundColor: '#3b82f6' }] }, options: { indexAxis: 'y' } });
            } else
                if (scope === 'payments') {
                    var s = (root && root.series) ? root.series : {days: [], counts: [], revenue: [], approval: []};
                    const days = s.days || [];
                    const counts = (s.counts || []).map(function (n) {
                        return Number(n) || 0;
                    });
                    const revenue = (s.revenue || []).map(function (n) {
                        return (Number(n) || 0) / 100;
                    });
                    const appr = (s.approval || []).map(function (n) {
                        return Number(n) || 0;
                    });

                    const ctxR = document.getElementById('rptRevenue').getContext('2d');
                    new Chart(ctxR, {
                        data: {
                            labels: days,
                            datasets: [
                                {
                                    type: 'line',
                                    label: 'Receita (R$)',
                                    data: revenue,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16,185,129,0.12)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'bar',
                                    label: 'Transações',
                                    data: counts,
                                    backgroundColor: '#6366f1',
                                    borderRadius: 4,
                                    yAxisID: 'y1'
                                },
                                {
                                    type: 'line',
                                    label: 'Aprovação (%)',
                                    data: appr,
                                    borderColor: '#f59e0b',
                                    backgroundColor: 'rgba(245,158,11,0.12)',
                                    borderDash: [6, 4],
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: false,
                                    yAxisID: 'y2'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            spanGaps: true,
                            normalized: true,
                            plugins: {
                                legend: {position: 'top'}, tooltip: {
                                    mode: 'index', intersect: false, callbacks: {
                                        title: function (items) {
                                            return (items && items[0]) ? fmtDateTitle(items[0].label) : '';
                                        }, label: function (ctx) {
                                            var label = ctx.dataset.label || '';
                                            var y = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : 0;
                                            if (ctx.dataset.yAxisID === 'y') return label + ': ' + fmtCur.format(y);
                                            if (ctx.dataset.yAxisID === 'y2') return label + ': ' + (y.toFixed(1)) + '%';
                                            return label + ': ' + fmtInt.format(y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {grid: {display: false}},
                                y: {
                                    position: 'left', ticks: {
                                        callback: function (v) {
                                            return fmtCur.format(v);
                                        }
                                    }, suggestedMax: suggestedMax(revenue)
                                },
                                y1: {
                                    position: 'right', grid: {drawOnChartArea: false}, ticks: {
                                        callback: function (v) {
                                            return fmtInt.format(v);
                                        }
                                    }, suggestedMax: suggestedMax(counts)
                                },
                                y2: {
                                    position: 'right', grid: {display: false}, ticks: {
                                        callback: function (v) {
                                            return (typeof v === 'number' ? v.toFixed(0) : v) + '%';
                                        }
                                    }, suggestedMax: 100
                                }
                            }
                        }
                    });

                    const methodMap = {
                        'credit_card': 'Cartão de Crédito',
                        'pix': 'Pix',
                        'boleto': 'Boleto',
                        'debit_card': 'Débito',
                        'unknown': 'Desconhecido'
                    };
                    const byMethodLabels = (root.byMethod || []).map(function (r) {
                        return methodMap[(r.m || 'unknown')] || (r.m || 'Desconhecido');
                    });
                    const byMethodCounts = (root.byMethod || []).map(function (r) {
                        return Number(r.c) || 0;
                    });
                    const ctxM = document.getElementById('rptMethods').getContext('2d');
                    new Chart(ctxM, {
                        type: 'doughnut',
                        data: {
                            labels: byMethodLabels,
                            datasets: [{
                                data: byMethodCounts,
                                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {legend: {position: 'right'}}
                        }
                    });

                    const statusMap = {
                        'paid': 'Pago',
                        'pending': 'Pendente',
                        'processing': 'Processando',
                        'authorized': 'Autorizado',
                        'canceled': 'Cancelado',
                        'failed': 'Falhou',
                        'refunded': 'Reembolsado',
                        'chargeback': 'Chargeback',
                        'created': 'Criado'
                    };
                    var byS = root.byStatus || [];
                    var statusOrder = ['created', 'pending', 'processing', 'authorized', 'paid', 'refunded', 'chargeback', 'canceled', 'failed'];
                    var countsMap = {};
                    byS.forEach(function (r) {
                        countsMap[(r.s || '').toLowerCase()] = Number(r.c) || 0;
                    });
                    var byStatusLabels = [], byStatusCounts = [];
                    statusOrder.forEach(function (k) {
                        if (typeof countsMap[k] !== 'undefined') {
                            byStatusLabels.push(statusMap[k] || k);
                            byStatusCounts.push(countsMap[k]);
                        }
                    });
                    var ctxS = document.getElementById('rptStatus').getContext('2d');
                    new Chart(ctxS, {
                        type: 'bar',
                        data: {
                            labels: byStatusLabels,
                            datasets: [{label: 'Pagamentos', data: byStatusCounts, backgroundColor: '#3b82f6'}]
                        },
                        options: {indexAxis: 'y'}
                    });

                    const topNames = (root.topSites || []).map(function (r) {
                        return r.n || 'N/A';
                    });
                    const topVals = (root.topSites || []).map(function (r) {
                        return (Number(r.v) || 0) / 100;
                    });
                    const ctxT = document.getElementById('rptTopSites').getContext('2d');
                    new Chart(ctxT, {
                        type: 'bar',
                        data: {
                            labels: topNames,
                            datasets: [{
                                label: 'Receita (R$)',
                                data: topVals,
                                backgroundColor: '#10b981',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {display: false}, tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            var v = (ctx.parsed && typeof ctx.parsed.x === 'number') ? ctx.parsed.x : 0;
                                            return 'Receita: ' + fmtCur.format(v);
                                        }
                                    }
                                }, scales: {
                                    x: {
                                        ticks: {
                                            callback: function (v) {
                                                return fmtCur.format(v);
                                            }
                                        }, suggestedMax: suggestedMax(topVals)
                                    }, y: {grid: {display: false}}
                                }
                            }
                        }
                    });

                    // Pix x Crédito (Receita)
                    var rbm = root.revByMethod || {days: [], pix: [], credit: []};
                    var daysM = rbm.days || [];
                    var pixVals = (rbm.pix || []).map(function (n) {
                        return (Number(n) || 0) / 100;
                    });
                    var credVals = (rbm.credit || []).map(function (n) {
                        return (Number(n) || 0) / 100;
                    });
                    var ctxPV = document.getElementById('rptPixVsCard').getContext('2d');
                    new Chart(ctxPV, {
                        type: 'line', data: {
                            labels: daysM, datasets: [
                                {
                                    label: 'Pix (R$)',
                                    data: pixVals,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16,185,129,0.12)',
                                    fill: true
                                },
                                {
                                    label: 'Cartão (R$)',
                                    data: credVals,
                                    borderColor: '#6366f1',
                                    backgroundColor: 'rgba(99,102,241,0.12)',
                                    fill: true
                                }
                            ]
                        }
                    });
                } else if (scope === 'boletos') {
                    var sB = (root && root.series) ? root.series : {days: [], counts: [], revenue: [], approval: []};
                    const daysB = sB.days || [];
                    const countsB = (sB.counts || []).map(function (n) { return Number(n) || 0; });
                    const revenueB = (sB.revenue || []).map(function (n) { return (Number(n) || 0) / 100; });
                    const apprB = (sB.approval || []).map(function (n) { return Number(n) || 0; });

                    const ctxB = document.getElementById('rptBoletoRevenue').getContext('2d');
                    new Chart(ctxB, {
                        data: {
                            labels: daysB,
                            datasets: [
                                {
                                    type: 'line',
                                    label: 'Receita (R$)',
                                    data: revenueB,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16,185,129,0.12)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'bar',
                                    label: 'Transações',
                                    data: countsB,
                                    backgroundColor: '#6366f1',
                                    borderRadius: 4,
                                    yAxisID: 'y1'
                                },
                                {
                                    type: 'line',
                                    label: 'Aprovação (%)',
                                    data: apprB,
                                    borderColor: '#f59e0b',
                                    backgroundColor: 'rgba(245,158,11,0.12)',
                                    borderDash: [6, 4],
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: false,
                                    yAxisID: 'y2'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            spanGaps: true,
                            normalized: true,
                            plugins: {
                                legend: {position: 'top'}, tooltip: {
                                    mode: 'index', intersect: false, callbacks: {
                                        title: function (items) {
                                            return (items && items[0]) ? fmtDateTitle(items[0].label) : '';
                                        }, label: function (ctx) {
                                            var label = ctx.dataset.label || '';
                                            var y = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : 0;
                                            if (ctx.dataset.yAxisID === 'y') return label + ': ' + fmtCur.format(y);
                                            if (ctx.dataset.yAxisID === 'y2') return label + ': ' + (y.toFixed(1)) + '%';
                                            return label + ': ' + fmtInt.format(y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {grid: {display: false}},
                                y: { position: 'left', ticks: { callback: function (v) { return fmtCur.format(v); } }, suggestedMax: suggestedMax(revenueB) },
                                y1: { position: 'right', grid: {drawOnChartArea: false}, ticks: { callback: function (v) { return fmtInt.format(v); } }, suggestedMax: suggestedMax(countsB) },
                                y2: { position: 'right', grid: {display: false}, ticks: { callback: function (v) { return (typeof v === 'number' ? v.toFixed(0) : v) + '%'; } }, suggestedMax: 100 }
                            }
                        }
                    });

                    const statusMapB = { 'paid': 'Pago', 'pending': 'Pendente', 'processing': 'Processando', 'authorized': 'Autorizado', 'canceled': 'Cancelado', 'failed': 'Falhou', 'refunded': 'Reembolsado', 'chargeback': 'Chargeback', 'created': 'Criado' };
                    var bySB = root.byStatus || [];
                    var statusOrderB = ['created', 'pending', 'processing', 'authorized', 'paid', 'refunded', 'chargeback', 'canceled', 'failed'];
                    var countsMapB = {};
                    bySB.forEach(function (r) { countsMapB[(r.s || '').toLowerCase()] = Number(r.c) || 0; });
                    var byStatusLabelsB = [], byStatusCountsB = [];
                    statusOrderB.forEach(function (k) { if (typeof countsMapB[k] !== 'undefined') { byStatusLabelsB.push(statusMapB[k] || k); byStatusCountsB.push(countsMapB[k]); } });
                    var ctxBS = document.getElementById('rptBoletoStatus').getContext('2d');
                    new Chart(ctxBS, { type: 'bar', data: { labels: byStatusLabelsB, datasets: [{ label: 'Boletos', data: byStatusCountsB, backgroundColor: '#3b82f6' }] }, options: { indexAxis: 'y' } });
                } else {
                    var s2 = (root && root.series) ? root.series : {days: [], newCounts: []};
                    const days = s2.days || [];
                    const newCounts = (s2.newCounts || []).map(function (n) {
                        return Number(n) || 0;
                    });
                    const active = Number(root.active || 0);
                    const pastDue = Number(root.pastDue || 0);

                    var ctxN = document.getElementById('rptSubsNew').getContext('2d');
                    new Chart(ctxN, {
                        type: 'line',
                        data: {
                            labels: days,
                            datasets: [{
                                label: 'Novas',
                                data: newCounts,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59,130,246,0.15)',
                                fill: true
                            }]
                        }
                    });

                    const ctxA = document.getElementById('rptSubsAp').getContext('2d');
                    new Chart(ctxA, {
                        type: 'doughnut',
                        data: {
                            labels: ['Ativas', 'Atrasadas'],
                            datasets: [{
                                data: [active, pastDue],
                                backgroundColor: ['#10b981', '#f59e0b'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {legend: {position: 'right'}}
                        }
                    });

                    var mapSubNames = {
                        'active': 'Ativa',
                        'trialing': 'Em teste',
                        'past_due': 'Atrasada',
                        'paused': 'Pausada',
                        'canceled': 'Cancelada',
                        'expired': 'Expirada',
                        'deleted': 'Excluída'
                    };
                    var subsOrder = ['trialing', 'active', 'past_due', 'paused', 'canceled', 'expired', 'deleted'];
                    var subCountsMap = {};
                    (root.byStatus || []).forEach(function (r) {
                        subCountsMap[(r.st || '').toLowerCase()] = Number(r.c) || 0;
                    });
                    var statLabels = [], statCounts = [];
                    subsOrder.forEach(function (k) {
                        if (typeof subCountsMap[k] !== 'undefined') {
                            statLabels.push(mapSubNames[k] || k);
                            statCounts.push(subCountsMap[k]);
                        }
                    });
                    var ctxSubsStatus = document.getElementById('rptSubsStatus').getContext('2d');
                    new Chart(ctxSubsStatus, {
                        type: 'bar',
                        data: {
                            labels: statLabels,
                            datasets: [{label: 'Assinaturas', data: statCounts, backgroundColor: '#6366f1'}]
                        },
                        options: {indexAxis: 'y'}
                    });

                    // Cancelamentos por dia
                    var sc = root.subsCanceledByDay || [];
                    var cDays = sc.map(function (r) {
                        return r.d;
                    });
                    var cCounts = sc.map(function (r) {
                        return Number(r.c) || 0;
                    });
                    var ctxCanc = document.getElementById('rptSubsCancel').getContext('2d');
                    new Chart(ctxCanc, {
                        type: 'bar',
                        data: {
                            labels: cDays,
                            datasets: [{label: 'Cancelamentos', data: cCounts, backgroundColor: '#ef4444'}]
                        }
                    });
                }
            }
        )
            ();
    </script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
