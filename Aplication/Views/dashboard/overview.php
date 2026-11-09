<?php
$alerts = $dashboard['alerts'];
$pnae = $dashboard['pnae'];
$percent = static fn(?float $value): string => $value === null ? '—' : number_format($value, 1, ',', '.') . '%';
$money = static fn($value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$activityLabel = ['ENTRADA' => 'Entrada', 'SAIDA' => 'Saída', 'AJUSTE' => 'Ajuste', 'ESTORNO' => 'Estorno'];
$activityIcon = ['ENTRADA' => 'south_west', 'SAIDA' => 'north_east', 'AJUSTE' => 'tune', 'ESTORNO' => 'undo'];
$reasonLabels = ['compra' => 'Compra', 'doacao' => 'Doação', 'consumo' => 'Consumo', 'perda' => 'Perda', 'vencimento' => 'Vencimento', 'descarte' => 'Descarte', 'erro_contagem' => 'Erro de contagem', 'entrada_nao_registrada' => 'Entrada não registrada', 'estorno' => 'Estorno'];
?>
<div class="overview-page">
    <header class="overview-heading">
        <div><span class="eyebrow">PAINEL DA UNIDADE</span>
            <h1>Visão geral</h1>
            <p>Alertas, atividade recente e acompanhamento das compras da unidade.</p>
        </div>
        <div class="workspace-heading-actions"><a class="workspace-button workspace-button-primary"
                href="index.php?page=saida-rapida">Registrar saída</a><a
                class="workspace-button workspace-button-secondary" href="index.php?page=entrada-documento">Registrar
                entrada</a></div>
    </header>

    <?php if (in_array('connection', $dashboard['errors'], true)): ?>
        <div class="data-notice" role="status"><span class="material-symbols-outlined" aria-hidden="true">database</span>
            <div><strong>Dados indisponíveis</strong>
                <p>Não foi possível carregar os dados desta unidade. Tente novamente em instantes.</p>
            </div>
        </div>
    <?php elseif ($dashboard['errors']): ?>
        <div class="data-notice" role="status"><span class="material-symbols-outlined" aria-hidden="true">info</span>
            <div><strong>Alguns dados ainda não estão disponíveis</strong>
                <p>Algumas informações não puderam ser carregadas. Tente novamente em instantes.</p>
            </div>
        </div>
    <?php endif; ?>

    <section class="overview-section" aria-labelledby="alerts-heading">
        <div class="section-heading">
            <div>
                <h2 id="alerts-heading">Alertas de estoque</h2>
                <p>Calculados a partir dos saldos e das validades dos lotes.</p>
            </div><span
                class="section-count"><?= count($alerts['expired']) + count($alerts['expiring']) + count($alerts['low_stock']) ?>
                alertas</span>
        </div>
        <div class="alert-grid">
            <article class="overview-card alert-card alert-danger">
                <div class="card-topline"><span class="alert-icon"><span class="material-symbols-outlined"
                            aria-hidden="true">event_busy</span></span><span
                        class="alert-count"><?= count($alerts['expired']) ?></span></div>
                <h3>Lotes vencidos</h3>
                <?php if ($alerts['expired']): ?>
                    <ul class="compact-list"><?php foreach (array_slice($alerts['expired'], 0, 3) as $lot): ?>
                            <li><span><?= e($lot['produto_nome']) ?> ·
                                    <?= e($lot['unidade_medida']) ?></span><small><?= abs((int) $lot['dias_restantes']) === 1 ? 'Venceu ontem' : 'Venceu há ' . abs((int) $lot['dias_restantes']) . ' dias' ?></small>
                            </li><?php endforeach; ?>
                    </ul><?php else: ?>
                    <p class="empty-copy">
                        <?= in_array('alerts', $dashboard['errors'], true) ? 'Não foi possível consultar as validades.' : 'Nenhum lote vencido com saldo disponível.' ?>
                    </p><?php endif; ?>
            </article>
            <article class="overview-card alert-card alert-warning">
                <div class="card-topline"><span class="alert-icon"><span class="material-symbols-outlined"
                            aria-hidden="true">schedule</span></span><span
                        class="alert-count"><?= count($alerts['expiring']) ?></span></div>
                <h3>Próximos da validade</h3>
                <?php if ($alerts['expiring']): ?>
                    <ul class="compact-list"><?php foreach (array_slice($alerts['expiring'], 0, 3) as $lot): ?>
                            <li><span><?= e($lot['produto_nome']) ?> ·
                                    <?= e($lot['unidade_medida']) ?></span><small><?= (int) $lot['dias_restantes'] === 0 ? 'Vence hoje' : 'Vence em ' . (int) $lot['dias_restantes'] . ' dias' ?></small>
                            </li><?php endforeach; ?>
                    </ul><?php else: ?>
                    <p class="empty-copy">
                        <?= in_array('alerts', $dashboard['errors'], true) ? 'Não foi possível consultar as validades.' : 'Nenhum lote dentro da janela de alerta da categoria.' ?>
                    </p><?php endif; ?>
            </article>
            <article class="overview-card alert-card alert-stock">
                <div class="card-topline"><span class="alert-icon"><span class="material-symbols-outlined"
                            aria-hidden="true">inventory_2</span></span><span
                        class="alert-count"><?= count($alerts['low_stock']) ?></span></div>
                <h3>Estoque baixo ou zerado</h3>
                <?php if ($alerts['low_stock']): ?>
                    <ul class="compact-list"><?php foreach (array_slice($alerts['low_stock'], 0, 3) as $product): ?>
                            <li><span><?= e($product['nome']) ?></span><small><?= number_format((float) $product['saldo'], 2, ',', '.') ?>
                                    / mín. <?= number_format((float) $product['estoque_minimo'], 2, ',', '.') ?>
                                    <?= e($product['unidade_medida']) ?></small></li><?php endforeach; ?>
                    </ul><?php else: ?>
                    <p class="empty-copy">
                        <?= in_array('alerts', $dashboard['errors'], true) ? 'Não foi possível consultar os saldos.' : 'Nenhum produto está abaixo do estoque mínimo.' ?>
                    </p><?php endif; ?>
            </article>
        </div>
    </section>

    <div class="overview-lower-grid">
        <section class="overview-card pnae-card" aria-labelledby="pnae-heading">
            <div class="section-heading pnae-heading">
                <div><span class="eyebrow">ACOMPANHAMENTO</span>
                    <h2 id="pnae-heading">Indicadores PNAE</h2>
                    <p>Compras com recurso federal · exercício <?= e($dashboard['exercise']) ?></p>
                </div><span class="material-symbols-outlined pnae-mark" aria-hidden="true">nutrition</span>
            </div>
            <?php if (!$pnae): ?>
                <div class="inline-empty"><span class="material-symbols-outlined" aria-hidden="true">info</span>
                    <p><?= in_array('pnae', $dashboard['errors'], true) || in_array('connection', $dashboard['errors'], true) ? 'A regra PNAE ou os dados de compras ainda não estão disponíveis.' : 'Não há compras federais registradas neste exercício.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php $metrics = [
                    ['label' => 'In natura ou minimamente processados', 'value' => $percent($pnae['percentuais']['in_natura']), 'goal' => 'mín. ' . number_format((float) $pnae['rule']['min_in_natura'], 0, ',', '.') . '%', 'amount' => $pnae['amounts']['in_natura'], 'type' => 'min', 'target' => (float) $pnae['rule']['min_in_natura'], 'actual' => $pnae['percentuais']['in_natura']],
                    ['label' => 'Processados e ultraprocessados', 'value' => $percent($pnae['percentuais']['proc_ultra']), 'goal' => 'máx. ' . number_format((float) $pnae['rule']['max_proc_ultra'], 0, ',', '.') . '%', 'amount' => $pnae['amounts']['proc_ultra'], 'type' => 'max', 'target' => (float) $pnae['rule']['max_proc_ultra'], 'actual' => $pnae['percentuais']['proc_ultra']],
                    ['label' => 'Ingredientes culinários processados', 'value' => $percent($pnae['percentuais']['ingr_culinario']), 'goal' => 'máx. ' . number_format((float) $pnae['rule']['max_ingr_culinario'], 0, ',', '.') . '%', 'amount' => $pnae['amounts']['ingr_culinario'], 'type' => 'max', 'target' => (float) $pnae['rule']['max_ingr_culinario'], 'actual' => $pnae['percentuais']['ingr_culinario']],
                    ['label' => 'Agricultura familiar', 'value' => $percent($pnae['percentuais']['agricultura_familiar']), 'goal' => 'mín. ' . number_format((float) $pnae['rule']['min_agric_familiar'], 0, ',', '.') . '%', 'amount' => $pnae['amounts']['agricultura_familiar'], 'type' => 'min', 'target' => (float) $pnae['rule']['min_agric_familiar'], 'actual' => $pnae['percentuais']['agricultura_familiar']],
                ]; ?>
                <div class="pnae-metrics">
                    <?php foreach ($metrics as $metric): ?>
                        <?php $met = $metric['actual'] !== null && ($metric['type'] === 'min' ? $metric['actual'] >= $metric['target'] : $metric['actual'] <= $metric['target']);
                        $width = $metric['actual'] === null ? 0 : min(100, max(0, $metric['actual']));
                        $goalPosition = min(100, max(0, (float) $metric['target'])); ?>
                        <div class="pnae-metric">
                            <div class="metric-label-row"><span><?= e($metric['label']) ?></span><span
                                    class="metric-state <?= $metric['actual'] === null ? 'is-unknown' : ($met ? 'is-good' : 'is-attention') ?>"><?= $metric['actual'] === null ? 'Sem dados' : ($met ? 'Dentro do limite' : 'Atenção') ?></span>
                            </div>
                            <div class="metric-value-row">
                                <strong><?= e($metric['value']) ?></strong><span><?= e($metric['goal']) ?></span>
                            </div>
                            <div class="metric-track"
                                style="--goal-position: <?= e(number_format($goalPosition, 1, '.', '')) ?>%" role="img"
                                aria-label="<?= e($metric['label']) ?>: <?= e($metric['value']) ?>; <?= e($metric['goal']) ?>">
                                <span
                                    class="<?= $metric['actual'] === null ? 'is-unknown' : ($met ? 'is-good' : 'is-attention') ?>"
                                    style="width: <?= e(number_format($width, 1, '.', '')) ?>%"></span>
                            </div>
                            <small><?= e($money($metric['amount'])) ?> no exercício</small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ((float) $pnae['amounts']['nao_classificado'] > 0): ?>
                    <p class="pnae-pending"><span class="material-symbols-outlined"
                            aria-hidden="true">pending</span><?= e($money($pnae['amounts']['nao_classificado'])) ?> em compras
                        com produto não classificado; esse valor não entra nos percentuais de processamento.</p><?php endif; ?>
                <?php if ($pnae['meta_interna_in_natura_pct'] !== null): ?>
                    <p class="unit-goal">Meta interna da unidade para in natura:
                        <strong><?= e(number_format($pnae['meta_interna_in_natura_pct'], 1, ',', '.')) ?>%</strong>
                    </p>
                <?php endif; ?>
                <?php $coveredPercent = (float) $pnae['amounts']['total_federal'] > 0 ? ((float) $pnae['amounts']['total_classificado'] / (float) $pnae['amounts']['total_federal']) * 100 : 0; ?>
            <?php endif; ?>
        </section>

        <section class="overview-card activity-card" aria-labelledby="activity-heading">
            <div class="section-heading">
                <div>
                    <h2 id="activity-heading">Atividade recente</h2>
                    <p>Últimas movimentações registradas nesta unidade.</p>
                </div><span class="material-symbols-outlined activity-mark" aria-hidden="true">history</span>
            </div>
            <?php if (!$dashboard['activities']): ?>
                <div class="activity-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">history_toggle_off</span>
                    <p><?= in_array('activities', $dashboard['errors'], true) || in_array('connection', $dashboard['errors'], true) ? 'Não foi possível consultar as movimentações.' : 'Ainda não há movimentações registradas.' ?>
                    </p>
                </div>
            <?php else: ?>
                <ol class="activity-list">
                    <?php foreach ($dashboard['activities'] as $activity): ?>
                        <?php $activityAt = $activity['operacao_criada_em'] ?? $activity['criado_em']; ?>
                        <li><span class="activity-icon activity-<?= e(strtolower($activity['tipo'])) ?>"><span
                                    class="material-symbols-outlined"
                                    aria-hidden="true"><?= e($activityIcon[$activity['tipo']] ?? 'swap_horiz') ?></span></span>
                            <div class="activity-body">
                                <div class="activity-meta">
                                    <strong><?= e($activityLabel[$activity['tipo']] ?? $activity['tipo']) ?> ·
                                        <?= e($reasonLabels[$activity['motivo']] ?? 'Outro motivo') ?></strong><time
                                        datetime="<?= e(date(DATE_ATOM, strtotime($activityAt))) ?>"><?= e(date('d/m H:i', strtotime($activityAt))) ?></time>
                                </div>
                                <p><?= e(number_format(abs((float) $activity['quantidade']), 2, ',', '.')) ?>
                                    <?= e($activity['unidade_medida']) ?> ·
                                    <?= e($activity['produto_nome']) ?>
                                    <?= !empty($activity['codigo_lote']) ? ' · lote ' . e($activity['codigo_lote']) : '' ?>
                                </p><small>Registrado por <?= e($activity['usuario_nome'] ?: 'Usuário desativado') ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

</div>