<?php
$menuGroups = [
    'OPERAÇÃO' => [
        ['label' => 'Visão geral', 'page' => 'visao-geral', 'icon' => 'space_dashboard'],
        ['label' => 'Saída rápida', 'page' => 'saida-rapida', 'icon' => 'output', 'permission' => 'registrar_saida'],
        ['label' => 'Entrada por documento', 'page' => 'entrada-documento', 'icon' => 'input', 'permission' => 'registrar_entrada'],
        ['label' => 'Estoque e lotes', 'page' => 'estoque', 'icon' => 'inventory_2', 'permission' => 'consultar'],
        ['label' => 'Movimentações', 'page' => 'movimentacoes', 'icon' => 'swap_vert', 'permission' => 'consultar'],
        ['label' => 'Alertas e validade', 'page' => 'alertas', 'icon' => 'notifications_active'],
        ['label' => 'Pendências de revisão', 'page' => 'pendencias', 'icon' => 'fact_check', 'permission' => 'revisar_pendencias'],
        ['label' => 'Lista de compras', 'page' => 'lista-compras', 'icon' => 'shopping_cart', 'permission' => 'registrar_entrada'],
    ],
    'CADASTROS' => [
        ['label' => 'Produtos', 'page' => 'produtos', 'icon' => 'category', 'permission' => 'cadastrar_produto'],
        ['label' => 'Fornecedores', 'page' => 'fornecedores', 'icon' => 'storefront', 'permission' => 'cadastrar_produto'],
        ['label' => 'Categorias', 'page' => 'categorias', 'icon' => 'label', 'permission' => 'gerir_usuarios'],
        ['label' => 'Documentos fiscais', 'page' => 'documentos', 'icon' => 'description', 'permission' => 'consultar'],
    ],
    'RELATÓRIOS E PNAE' => [ ['label' => 'Relatórios e PNAE', 'page' => 'relatorios', 'icon' => 'query_stats', 'permission' => 'ver_relatorios'] ],
];
$permissionIds = $permissionIds ?? [];
?>
<aside class="app-sidebar" id="app-sidebar" aria-label="Navegação principal">
    <a class="sidebar-brand" href="index.php?page=visao-geral"><img src="Arquivos/Imagens/logo.png" alt="" width="38" height="38"><span><strong>Controle de Alimentos</strong></span></a>
    <div class="sidebar-unit"><span class="material-symbols-outlined" aria-hidden="true">domain</span><span><small>Unidade ativa</small><strong><?= e($user['unidade_nome'] ?? 'Unidade') ?></strong></span></div>
    <nav class="sidebar-nav">
        <?php foreach ($menuGroups as $group => $items): ?>
            <?php $visibleItems = array_values(array_filter($items, static fn($item) => empty($item['permission']) || in_array($item['permission'], $permissionIds, true))); ?>
            <?php if (!$visibleItems) continue; ?>
            <span class="nav-section-label"><?= e($group) ?></span>
            <?php foreach ($visibleItems as $item): ?>
                <?php $activePage = (string)($currentPage ?? ''); $active = $activePage === $item['page'] || ($item['page']==='movimentacoes' && $activePage==='ajuste-estoque') || ($item['page']==='produtos' && $activePage==='produto-form') || ($item['page']==='fornecedores' && $activePage==='fornecedor-form') || ($item['page']==='categorias' && $activePage==='categoria-form') || ($item['page']==='usuarios' && $activePage==='usuario-form'); ?>
                <?php if (($item['available'] ?? true) === false): ?>
                    <span class="sidebar-link is-unavailable" aria-disabled="true"><span class="material-symbols-outlined" aria-hidden="true"><?= e($item['icon']) ?></span><span><?= e($item['label']) ?></span><small>Em breve</small></span>
                <?php else: ?>
                    <a class="sidebar-link <?= $active ? 'is-active' : '' ?>" href="index.php?page=<?= e($item['page']) ?>" <?= $active ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined" aria-hidden="true"><?= e($item['icon']) ?></span><span><?= e($item['label']) ?></span></a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <?php if (in_array('gerir_usuarios', $permissionIds, true)): ?><div class="sidebar-admin-links"><span class="nav-section-label">ADMINISTRAÇÃO</span><a class="sidebar-link <?= in_array($currentPage,['usuarios','usuario-form'],true)?'is-active':'' ?>" href="index.php?page=usuarios"><span class="material-symbols-outlined" aria-hidden="true">manage_accounts</span><span>Usuários</span></a><a class="sidebar-link <?= $currentPage==='configuracoes'?'is-active':'' ?>" href="index.php?page=configuracoes"><span class="material-symbols-outlined" aria-hidden="true">settings</span><span>Configurações</span></a></div><?php endif; ?>
        <div class="sidebar-profile"><span class="user-avatar" aria-hidden="true"><?= e($userInitial) ?></span><span><strong><?= e($user['nome'] ?? 'Usuário') ?></strong><small>Conta da unidade</small></span></div>
        <div class="font-scale-controls sidebar-font-scale" role="group" aria-label="Tamanho do texto">
            <button type="button" class="font-scale-btn" data-font-scale-step="-1" aria-label="Diminuir fonte" title="Diminuir fonte">A−</button>
            <span class="font-scale-current" data-font-scale-current aria-live="polite">A</span>
            <button type="button" class="font-scale-btn" data-font-scale-step="1" aria-label="Aumentar fonte" title="Aumentar fonte">A+</button>
        </div>
        <form method="post" action="index.php?page=login&amp;action=logout"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="sidebar-logout" type="submit"><span class="material-symbols-outlined" aria-hidden="true">logout</span>Sair do sistema</button></form>
    </div>
</aside>
