<?php $gradeLabels = ['in_natura_minimamente_processado' => 'In natura ou minimamente processado', 'ingrediente_culinario' => 'Ingrediente culinário', 'processado' => 'Processado', 'ultraprocessado' => 'Ultraprocessado', 'nao_classificado' => 'Sem classificação'];
$rule = $report['rule'] ?? null;
$total = (float) ($report['total'] ?? 0);
$family = (float) ($report['family'] ?? 0); ?>
<section class="workspace-page">
    <header class="workspace-heading">
        <div><span class="workspace-eyebrow">ACOMPANHAMENTO · DADOS DA UNIDADE</span>
            <h1>Relatórios e PNAE</h1>
            <p>Indicadores calculados a partir das entradas registradas. Não substituem a apuração nem a prestação de
                contas oficial.</p>
        </div>
        <form class="workspace-toolbar" method="get"><input type="hidden" name="page" value="relatorios"><label
                class="workspace-field-inline">Exercício<input name="ano" type="number" min="2000" max="2100"
                    value="<?= e($report['year'] ?? date('Y')) ?>"></label><button
                class="workspace-button workspace-button-secondary">Consultar</button></form>
    </header>
    <?php if ($message): ?>
        <div class="workspace-message"><span class="material-symbols-outlined">info</span><?= e($message) ?></div>
    <?php endif; ?>
    <div class="report-grid">
        <article class="workspace-card report-card"><span class="workspace-eyebrow">COMPRAS PNAE
                FEDERAL</span><strong>R$ <?= number_format($total, 2, ',', '.') ?></strong><small>Total de valores
                registrados no exercício de <?= e($report['year'] ?? '') ?>.</small></article>
        <article class="workspace-card report-card"><span class="workspace-eyebrow">AGRICULTURA
                FAMILIAR</span><strong><?= $total > 0 ? number_format($family / $total * 100, 1, ',', '.') : '—' ?><?= $total > 0 ? '%' : '' ?></strong><small>R$
                <?= number_format($family, 2, ',', '.') ?> em valores identificados como compra direta da agricultura
                familiar.</small></article>
        <article class="workspace-card report-card"><span class="workspace-eyebrow">CLASSIFICAÇÃO
                PENDENTE</span><strong>R$
                <?= number_format((float) ($report['unclassified'] ?? 0), 2, ',', '.') ?></strong><small>Compras sem
                classificação do produto; fora dos percentuais por grupo.</small></article>
    </div>
    <article class="workspace-card">
        <div class="workspace-section-heading">
            <h2>Composição das compras por processamento</h2><span>Indicador de acompanhamento</span>
        </div><?php if ($total > 0): ?>
            <div class="report-bars">
                <?php foreach ($gradeLabels as $key => $label):
                    $amount = (float) (($report['byGrade'] ?? [])[$key] ?? 0); ?>
                    <div class="report-bar-row"><span><?= e($label) ?></span>
                        <div class="report-bar-track"><i style="width:<?= min(100, $amount / $total * 100) ?>%"></i></div>
                        <strong>R$
                            <?= number_format($amount, 2, ',', '.') ?> ·
                            <?= number_format($amount / $total * 100, 1, ',', '.') ?>%</strong>
                    </div><?php endforeach; ?>
            </div><?php else: ?>
            <div class="workspace-empty"><strong>Sem compras PNAE no período</strong>
                <p>Os percentuais aparecerão depois de registrar entradas com recurso PNAE federal e valores dos itens.</p>
            </div><?php endif; ?>
        <?php if ($rule): ?>
            <p class="report-rule"><?= e($rule['referencia_normativa']) ?></p><?php else: ?>
            <p class="report-rule">Não há regra PNAE cadastrada que corresponda ao exercício consultado.</p><?php endif; ?>
    </article>
    <article class="workspace-card">
        <div class="workspace-section-heading">
            <h2>Perdas, vencimentos e descartes</h2><span><?= count($report['waste'] ?? []) ?></span>
        </div><?php if (!empty($report['waste'])): ?>
            <div class="workspace-table-wrap">
                <table class="workspace-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Quantidade baixada</th>
                            <th>Último registro</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($report['waste'] as $row): ?>
                            <tr>
                                <th><?= e($row['nome']) ?></th>
                                <td><?= e($row['quantidade']) ?>         <?= e($row['unidade_medida']) ?></td>
                                <td><?= e($row['ultima']) ?></td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div><?php else: ?>
            <div class="workspace-empty compact">
                <p>Não há perdas registradas neste período.</p>
            </div><?php endif; ?>
    </article>
</section>