<?php
$records = $workspace['records'] ?? [];
$pagination = $workspace['pagination'] ?? null;
$record = $workspace['record'] ?? null;
$product = $workspace['product'] ?? null;
$categories = $workspace['categories'] ?? [];
$permissions = $workspace['permissions'] ?? [];
$selected = $workspace['selectedPermissions'] ?? [];
$base = 'index.php?page=';
$esc = static fn($v) => e($v);
?>
<section class="workspace-page">
    <header class="workspace-heading">
        <div><span class="workspace-eyebrow">GESTÃO DO SISTEMA</span>
            <h1><?= e($pageTitle) ?></h1>
            <p>Gerencie as informações e acessos da unidade.</p>
        </div>
        <?php if (in_array($currentPage, ['produtos', 'fornecedores', 'categorias'], true)): ?>
            <div class="workspace-heading-actions"><a class="workspace-button workspace-button-primary"
                    href="<?= $base . ($currentPage === 'produtos' ? 'produto-form' : ($currentPage === 'fornecedores' ? 'fornecedor-form' : 'categoria-form')) ?>"><span
                        class="material-symbols-outlined" aria-hidden="true">add</span>Novo cadastro</a></div>
        <?php endif; ?>
    </header>
    <?php if (!empty($loadError)): ?>
        <div class="workspace-card workspace-load-error" role="alert"><strong>Não foi possível carregar esta tela. Tente
                novamente.</strong><a class="workspace-button workspace-button-secondary"
                href="<?= e('index.php?' . http_build_query($_GET)) ?>">Tentar novamente</a></div>
    <?php elseif (!empty($message)): ?>
        <div class="workspace-message" role="status"><span class="material-symbols-outlined">info</span><?= e($message) ?>
        </div><?php elseif (isset($_GET['saved'])): ?>
        <div class="workspace-message is-success" role="status"><span
                class="material-symbols-outlined">check_circle</span>Alterações salvas.</div>
    <?php elseif (isset($_GET['demo_restaurada'])): ?>
        <div class="workspace-message is-success" role="status"><span
                class="material-symbols-outlined">check_circle</span>Dados de demonstração restaurados.</div><?php endif; ?>

    <?php if (empty($loadError)): ?>
        <?php if (in_array($currentPage, ['produto-form', 'produtos'], true)): $p = $product ?? []; ?>
            <?php if ($currentPage === 'produto-form'): ?>
                <form class="workspace-card workspace-form" method="post" action="index.php?page=produto-form&amp;action=save">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id"
                        value="<?= e($p['id'] ?? '') ?>">
                    <div class="workspace-form-grid">
                        <label class="workspace-field"><span>Nome do produto</span><input required name="nome"
                                value="<?= e($p['nome'] ?? '') ?>"></label>
                        <label class="workspace-field"><span>Categoria</span><select required name="categoria_id">
                                <option value="">Selecione</option><?php foreach ($categories as $c): ?>
                                    <option value="<?= e($c['id']) ?>" <?= (int) ($p['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['nome']) ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="workspace-field"><span>Unidade de medida</span><select
                                name="unidade_medida"><?php foreach (['kg', 'L', 'un', 'pct'] as $u): ?>
                                    <option <?= ($p['unidade_medida'] ?? 'kg') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="workspace-field"><span>Estoque mínimo</span><input type="number" min="0" step="0.001"
                                name="estoque_minimo" value="<?= e($p['estoque_minimo'] ?? '0') ?>"></label>
                        <label class="workspace-field"><span>Grau de processamento</span><select
                                name="grau_processamento"><?php foreach (['in_natura_minimamente_processado' => 'In natura ou minimamente processado', 'ingrediente_culinario' => 'Ingrediente culinário', 'processado' => 'Processado', 'ultraprocessado' => 'Ultraprocessado', 'nao_classificado' => 'Não classificado'] as $v => $label): ?>
                                    <option value="<?= e($v) ?>" <?= ($p['grau_processamento'] ?? 'nao_classificado') === $v ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="workspace-check"><input type="checkbox" name="aceita_fracao" value="1"
                                <?= !empty($p['aceita_fracao']) ? 'checked' : '' ?>>Aceita quantidade fracionária</label><label
                            class="workspace-check"><input type="checkbox" name="vedado_pnae" value="1"
                                <?= !empty($p['vedado_pnae']) ? 'checked' : '' ?>>Vedado para compra com recurso federal</label>
                        <label class="workspace-field workspace-field-wide"><span>Observações</span><textarea name="observacoes"
                                rows="3"><?= e($p['observacoes'] ?? '') ?></textarea></label>
                    </div>
                    <div class="workspace-form-actions"><a href="<?= $base ?>produtos">Cancelar</a><button
                            class="workspace-button workspace-button-primary">Salvar produto</button></div>
                </form><?php else: ?>
                <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                        value="produtos"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                        value="<?= e($query['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                            class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                            value="<?= e($query['q'] ?? '') ?>" placeholder="Buscar produto ou categoria"></label><label
                        class="workspace-field-inline"><span>Categoria</span><select name="categoria_id">
                            <option value="">Todas</option><?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (string) ($query['categoria_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['nome']) ?></option><?php endforeach; ?>
                        </select></label><label class="workspace-field-inline"><span>Unidade</span><select name="unidade_medida">
                            <option value="">Todas</option>
                            <?php foreach (['kg' => 'kg', 'L' => 'L', 'un' => 'un', 'pct' => 'pct'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($query['unidade_medida'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?></option><?php endforeach; ?>
                        </select></label><label class="workspace-field-inline"><span>Quantidade fracionária</span><select
                            name="fracao">
                            <option value="">Todas</option>
                            <option value="1" <?= ($query['fracao'] ?? '') === '1' ? 'selected' : '' ?>>Aceita</option>
                            <option value="0" <?= ($query['fracao'] ?? '') === '0' ? 'selected' : '' ?>>Não aceita</option>
                        </select></label><label class="workspace-field-inline"><span>Compra com PNAE</span><select
                            name="vedado_pnae">
                            <option value="">Todas</option>
                            <option value="0" <?= ($query['vedado_pnae'] ?? '') === '0' ? 'selected' : '' ?>>Permitida</option>
                            <option value="1" <?= ($query['vedado_pnae'] ?? '') === '1' ? 'selected' : '' ?>>Vedada</option>
                        </select></label><button class="workspace-button workspace-button-secondary"
                        type="submit">Filtrar</button><a class="workspace-button workspace-button-secondary"
                        href="index.php?page=produtos">Limpar</a></form>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Unidade</th>
                                <th>Estoque mínimo</th>
                                <th>Classificação</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($records as $r): ?>
                                <tr>
                                    <th><?= e($r['nome']) ?></th>
                                    <td><?= e($r['categoria_nome']) ?></td>
                                    <td><?= e($r['unidade_medida']) ?></td>
                                    <td><?= e($r['estoque_minimo']) ?></td>
                                    <td><?= e(str_replace('_', ' ', $r['grau_processamento'])) ?></td>
                                    <td><a href="<?= $base ?>produto-form&amp;id=<?= e($r['id']) ?>">Editar</a> · <form
                                            style="display:inline" method="post" action="index.php?page=produtos&amp;action=archive">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden"
                                                name="id" value="<?= e($r['id']) ?>"><button class="table-action">Arquivar</button>
                                        </form>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$records): ?>
                        <div class="workspace-empty">
                            <strong><?= !empty($query['q']) || !empty($query['categoria_id']) || !empty($query['unidade_medida']) || isset($query['fracao']) || isset($query['vedado_pnae']) ? 'Nenhum produto corresponde aos filtros' : 'Nenhum produto cadastrado' ?></strong>
                            <p><?= !empty($query['q']) || !empty($query['categoria_id']) || !empty($query['unidade_medida']) || isset($query['fracao']) || isset($query['vedado_pnae']) ? 'Altere ou limpe os filtros para ver outros produtos.' : 'Cadastre produtos para registrar lotes e acompanhar saldos.' ?>
                            </p>
                        </div><?php endif; ?>
                </div><?php endif; ?>

        <?php elseif (in_array($currentPage, ['fornecedor-form', 'fornecedores'], true)): ?>
            <?php if ($currentPage === 'fornecedor-form'):
                $f = array_replace($workspace['record'] ?? [], $formValues ?? []); ?>
                <form class="workspace-card workspace-form" method="post" action="index.php?page=fornecedor-form&amp;action=save">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id"
                        value="<?= e($f['id'] ?? '') ?>">
                    <div class="workspace-form-grid"><label class="workspace-field"><span>Nome / razão social</span><input
                                name="nome" required value="<?= e($f['nome'] ?? '') ?>"></label><label
                            class="workspace-field"><span>CPF/CNPJ</span><input name="cpf_cnpj"
                                value="<?= e($f['cpf_cnpj'] ?? '') ?>"></label><label
                            class="workspace-field"><span>E-mail</span><input type="email" name="email"
                                value="<?= e($f['email'] ?? '') ?>"></label><label class="workspace-field"><span>Telefone</span><input
                                name="telefone" value="<?= e($f['telefone'] ?? '') ?>"></label><label
                            class="workspace-field"><span>Prazo médio de reposição (dias)</span><input type="number" min="0"
                                name="prazo_reposicao_dias" value="<?= e($f['prazo_reposicao_dias'] ?? '') ?>"></label><label
                            class="workspace-check"><input type="checkbox" name="agricultura_familiar" value="1"
                                <?= !empty($f['agricultura_familiar']) ? 'checked' : '' ?>>Fornecedor da agricultura familiar</label>
                    </div>
                    <div class="workspace-form-actions"><a href="<?= $base ?>fornecedores">Cancelar</a><button
                            class="workspace-button workspace-button-primary">Salvar fornecedor</button></div>
                </form><?php else: ?>
                <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                        value="fornecedores"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                        value="<?= e($query["por_pagina"] ?? 20) ?>"><label class="workspace-search"><span
                            class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                            value="<?= e($query["q"] ?? "") ?>" placeholder="Nome, CPF/CNPJ ou e-mail"></label><button
                        class="workspace-button workspace-button-secondary">Buscar</button><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=fornecedores">Limpar</a></form>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Fornecedor</th>
                                <th>Documento</th>
                                <th>Contato</th>
                                <th>Prazo</th>
                                <th>Agricultura familiar</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($records as $r): ?>
                                <tr>
                                    <th><?= e($r['nome']) ?></th>
                                    <td><?= e($r['cpf_cnpj'] ?: '—') ?></td>
                                    <td><?= e($r['email'] ?: $r['telefone'] ?: '—') ?></td>
                                    <td><?= e($r['prazo_reposicao_dias'] === null ? '—' : $r['prazo_reposicao_dias'] . ' dias') ?></td>
                                    <td><?= $r['agricultura_familiar'] ? 'Sim' : 'Não' ?></td>
                                    <td><a href="<?= $base ?>fornecedor-form&amp;id=<?= e($r['id']) ?>">Editar</a> · <form
                                            style="display:inline" method="post"
                                            action="index.php?page=fornecedores&amp;action=deactivate"><input type="hidden"
                                                name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id"
                                                value="<?= e($r['id']) ?>"><button class="table-action">Desativar</button></form>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$records): ?>
                        <div class="workspace-empty"><strong>Nenhum fornecedor cadastrado</strong></div><?php endif; ?>
                </div><?php endif; ?>

        <?php elseif (in_array($currentPage, ['categorias', 'categoria-form'], true)): ?>
            <?php if ($currentPage === 'categoria-form'):
                $category = $workspace['record'] ?? []; ?>
                <form class="workspace-card workspace-form" method="post" action="index.php?page=categoria-form&amp;action=save">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id"
                        value="<?= e($category['id'] ?? '') ?>">
                    <div class="workspace-form-grid"><label class="workspace-field workspace-field-wide"><span>Nome da
                                categoria</span><input name="nome" required value="<?= e($category['nome'] ?? '') ?>"></label><label
                            class="workspace-field"><span>Antecedência do alerta de validade (dias)</span><input type="number"
                                min="0" name="janela_alerta_validade_dias"
                                value="<?= e($category['janela_alerta_validade_dias'] ?? 0) ?>"></label><label
                            class="workspace-field"><span>Validade padrão (dias)</span><input type="number" min="1"
                                name="validade_padrao_dias" value="<?= e($category['validade_padrao_dias'] ?? '') ?>"></label><label
                            class="workspace-check"><input type="checkbox" name="ativo" value="1"
                                <?= !isset($category['ativo']) || $category['ativo'] ? 'checked' : '' ?>>Categoria ativa</label></div>
                    <div class="workspace-form-actions"><a href="index.php?page=categorias">Cancelar</a><button
                            class="workspace-button workspace-button-primary" type="submit">Salvar categoria</button></div>
                </form>
            <?php else: ?>
                <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                        value="categorias"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                        value="<?= e($query["por_pagina"] ?? 20) ?>"><label class="workspace-search"><span
                            class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                            value="<?= e($query["q"] ?? "") ?>" placeholder="Buscar categoria"></label><label
                        class="workspace-field-inline"><span>Status</span><select name="status">
                            <option value="">Todas</option>
                            <option value="1" <?= ($query["status"] ?? "") === "1" ? "selected" : "" ?>>Ativas</option>
                            <option value="0" <?= ($query["status"] ?? "") === "0" ? "selected" : "" ?>>Inativas</option>
                        </select></label><button class="workspace-button workspace-button-secondary">Filtrar</button><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=categorias">Limpar</a></form>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th>Alerta de validade</th>
                                <th>Validade padrão</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($records as $r): ?>
                                <tr>
                                    <th><?= e($r['nome']) ?></th>
                                    <td><?= (int) $r['janela_alerta_validade_dias'] ?> dias</td>
                                    <td><?= $r['validade_padrao_dias'] === null ? '—' : (int) $r['validade_padrao_dias'] . ' dias' ?></td>
                                    <td><span
                                            class="workspace-tag <?= $r['ativo'] ? 'is-success' : 'is-muted' ?>"><?= $r['ativo'] ? 'Ativa' : 'Inativa' ?></span>
                                    </td>
                                    <td><a href="index.php?page=categoria-form&amp;id=<?= (int) $r['id'] ?>">Editar</a></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table><?php if (!$records): ?>
                        <div class="workspace-empty"><strong>Nenhuma categoria cadastrada</strong>
                            <p>Adicione uma categoria para organizar os produtos.</p>
                        </div><?php endif; ?>
                </div><?php endif; ?>

        <?php elseif (in_array($currentPage, ['usuarios', 'usuario-form'], true)): $u = $workspace['record'] ?? []; ?>
            <?php if ($currentPage === 'usuarios'): ?>
                <div class="user-list-header">
                    <form class="workspace-toolbar" method="get">
                        <input type="hidden" name="page" value="usuarios">
                        <label class="workspace-search"><span class="material-symbols-outlined"
                                aria-hidden="true">search</span><input name="q" value="<?= e($_GET['q'] ?? '') ?>"
                                placeholder="Buscar por nome ou e-mail"></label>
                        <button class="workspace-button workspace-button-secondary">Buscar</button>
                    </form>
                    <a class="workspace-button workspace-button-primary" href="index.php?page=usuario-form"><span
                            class="material-symbols-outlined" aria-hidden="true">person_add</span>Novo usuário</a>
                </div>
                <?php if (!$records): ?>
                    <div class="workspace-card workspace-empty"><span class="material-symbols-outlined"
                            aria-hidden="true">group</span><strong>Nenhum usuário encontrado</strong>
                        <p>Cadastre as pessoas que precisam acessar esta unidade.</p>
                    </div>
                <?php else: ?>
                    <div class="user-cards">
                        <?php foreach ($records as $r):
                            $initial = function_exists('mb_strtoupper') && function_exists('mb_substr')
                                ? mb_strtoupper(mb_substr((string) ($r['nome'] ?? 'U'), 0, 1))
                                : strtoupper(substr((string) ($r['nome'] ?? 'U'), 0, 1));
                            $userPermIds = array_filter(explode(',', (string) ($r['permission_ids'] ?? '')));
                            $userPerms = array_filter($permissions, static fn($p) => in_array($p['id'], $userPermIds, true));
                            ?>
                            <div class="user-card <?= $r['ativo'] ? '' : 'is-inactive' ?>">
                                <div class="user-card-top">
                                    <span class="user-avatar user-avatar-lg" aria-hidden="true"><?= e($initial) ?></span>
                                    <div class="user-card-info">
                                        <strong><?= e($r['nome']) ?></strong>
                                        <span><?= e($r['email']) ?></span>
                                    </div>
                                    <span
                                        class="workspace-tag <?= $r['ativo'] ? 'is-success' : 'is-muted' ?>"><?= $r['ativo'] ? '&#9679; Ativo' : '&#9675; Inativo' ?></span>
                                </div>
                                <div class="user-card-perms">
                                    <?php if ($userPerms):
                                        foreach ($userPerms as $perm): ?>
                                            <span class="workspace-tag"><?= e($perm['nome']) ?></span>
                                        <?php endforeach; else: ?>
                                        <span class="user-no-perms">Sem permissões atribuídas</span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-card-actions">
                                    <a class="workspace-button workspace-button-secondary"
                                        href="index.php?page=usuario-form&amp;id=<?= (int) $r['id'] ?>"><span
                                            class="material-symbols-outlined" aria-hidden="true">edit</span>Editar usuário</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <form class="workspace-card workspace-form user-form" method="post"
                    action="index.php?page=usuario-form&amp;action=save" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= e($u['id'] ?? '') ?>">
                    <h2 class="workspace-subheading">Dados da conta</h2>
                    <div class="workspace-form-grid">
                        <label class="workspace-field"><span>Nome completo</span><input name="person_label" id="person_label"
                                autocomplete="off" required value="<?= e($u['nome'] ?? '') ?>" placeholder="Ex.: Maria Silva"></label>
                        <label class="workspace-field"><span>E-mail</span><input type="email" name="account_address"
                                id="account_address" autocomplete="off" required value="<?= e($u['email'] ?? '') ?>"
                                placeholder="usuario@escola.gov.br"></label>
                        <label
                            class="workspace-field workspace-field-wide"><span><?= $u ? 'Nova senha <small>(opcional &middot; mín. 12 caracteres)</small>' : 'Senha inicial <small>(mín. 12 caracteres)</small>' ?></span><input
                                type="password" name="initial_secret" id="initial_secret" autocomplete="new-password" minlength="12"
                                <?= $u ? '' : 'required' ?>
                                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"><small>Combine
                                letras, números e símbolos.</small></label>
                        <?php if ($u): ?><label class="workspace-check workspace-field-wide"><input type="checkbox" name="ativo"
                                    value="1" <?= !empty($u['ativo']) ? 'checked' : '' ?>>Conta ativa &mdash; usuário consegue acessar o
                                sistema</label><?php endif; ?>
                    </div>
                    <h2 class="workspace-subheading" style="margin-top:4px">Permissões de acesso</h2>
                    <p class="workspace-muted" style="margin:-4px 0 16px">Marque o que este usuário pode fazer no sistema.</p>
                    <?php
                    $groups = ['operacao' => 'Operação', 'cadastro' => 'Cadastro', 'gestao' => 'Gestão', 'consulta' => 'Consulta', 'administracao' => 'Administração'];
                    $groupIcons = ['operacao' => 'swap_vert', 'cadastro' => 'category', 'gestao' => 'bar_chart', 'consulta' => 'visibility', 'administracao' => 'admin_panel_settings'];
                    foreach ($groups as $group => $groupLabel):
                        $groupPermissions = array_filter($permissions, static fn($permission) => $permission['grupo'] === $group);
                        if (!$groupPermissions)
                            continue;
                        ?>
                        <fieldset class="permission-group">
                            <legend><span class="material-symbols-outlined"
                                    aria-hidden="true"><?= $groupIcons[$group] ?></span><?= e($groupLabel) ?></legend>
                            <div class="permission-grid">
                                <?php foreach ($groupPermissions as $p): ?>
                                    <label class="workspace-check"><input type="checkbox" name="permissoes[]" value="<?= e($p['id']) ?>"
                                            <?= in_array($p['id'], $selected, true) ? 'checked' : '' ?>><span><strong><?= e($p['nome']) ?></strong><small><?= e($p['descricao']) ?></small></span></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <div class="workspace-form-actions">
                        <a href="index.php?page=usuarios">Cancelar</a>
                        <button
                            class="workspace-button workspace-button-primary"><?= $u ? 'Salvar alterações' : 'Criar usuário' ?></button>
                    </div>
                </form>
            <?php endif; ?>

        <?php elseif ($currentPage === 'configuracoes'): ?>
            <form class="workspace-card workspace-form" method="post" action="index.php?page=configuracoes&amp;action=save">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="settings-list">
                    <?php foreach ($records as $r):
                        $value = $r['valor_unidade'] ?? $r['valor_padrao'] ?? '';
                        $allowed = $r['valores_permitidos'] ? json_decode($r['valores_permitidos'], true) : null; ?><label
                            class="workspace-field"><span><?= e($r['nome']) ?><?php if (!$r['editavel_pela_unidade']): ?><small> ·
                                        Somente
                                        leitura</small><?php endif; ?></span><small><?= e($r['descricao']) ?></small><?php if (($r['tipo_valor'] === 'opcao') && is_array($allowed) && array_is_list($allowed)): ?><select
                                    name="config[<?= e($r['chave']) ?>]" <?= !$r['editavel_pela_unidade'] ? 'disabled' : '' ?>><?php foreach ($allowed as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= (string) $value === (string) $opt ? 'selected' : '' ?>><?= e($opt) ?>
                                        </option><?php endforeach; ?>
                                </select><?php elseif ($r['tipo_valor'] === 'booleano'): ?><select name="config[<?= e($r['chave']) ?>]"
                                    <?= !$r['editavel_pela_unidade'] ? 'disabled' : '' ?>>
                                    <option value="1" <?= $value === '1' ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= $value === '0' ? 'selected' : '' ?>>Não</option>
                                </select><?php else: ?><input name="config[<?= e($r['chave']) ?>]" value="<?= e($value) ?>"
                                    <?= !$r['editavel_pela_unidade'] ? 'disabled' : '' ?>
                                    <?= in_array($r['tipo_valor'], ['inteiro', 'decimal'], true) ? 'type="number"' : '' ?>><?php endif; ?></label><?php endforeach; ?></div>
                <div class="workspace-form-actions"><button class="workspace-button workspace-button-primary">Salvar
                        configurações</button></div>
            </form><?php if (getenv('APP_ENV') === 'demo'): ?>
                <form class="workspace-card demo-restore" method="post" action="index.php?page=configuracoes&amp;action=restoreDemo"
                    onsubmit="return confirm('Isso substituirá os registros fictícios da unidade de demonstração. Restaurar agora?');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><span><strong>Ambiente de
                            demonstração</strong><small>Restaura produtos, movimentos e contas fictícias.</small></span><button
                        class="workspace-button workspace-button-secondary" type="submit">Restaurar dados de demonstração</button>
                </form><?php endif; ?>

        <?php elseif ($currentPage === 'documentos'): ?>
            <form class="workspace-toolbar product-filters" method="get">
                <input type="hidden" name="page" value="documentos">
                <input type="hidden" name="pagina" value="1">
                <input type="hidden" name="por_pagina" value="<?= e($query["por_pagina"] ?? 20) ?>">
                <label class="workspace-search"><span class="material-symbols-outlined" aria-hidden="true">search</span><input
                        name="q" value="<?= e($query["q"] ?? "") ?>" placeholder="Número do documento ou fornecedor"></label>
                <label class="workspace-field-inline"><span>Status</span><select name="status">
                        <option value="">Todos</option>
                        <option value="ativo" <?= ($query["status"] ?? "") === "ativo" ? "selected" : "" ?>>Ativo</option>
                        <option value="cancelado" <?= ($query["status"] ?? "") === "cancelado" ? "selected" : "" ?>>Cancelado</option>
                    </select></label>
                <button class="workspace-button workspace-button-secondary">Filtrar</button>
                <a class="workspace-button workspace-button-secondary" href="index.php?page=documentos">Limpar</a>
            </form>
            <div class="workspace-card workspace-table-wrap">
                <table class="workspace-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Fornecedor / doador</th>
                            <th>Emissão</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Registrado por</th>
                            <?php if ($canCancelDocuments): ?>
                                <th>Ação</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($records as $r):
                        $tipoLabel = match ($r['tipo'] ?? '') {
                            'nota_fiscal_eletronica' => 'NF-e',
                            'nota_produtor_rural' => 'Prod. rural',
                            'termo_doacao' => 'Doação',
                            default => 'Documento',
                        };
                        ?>
                            <tr>
                                <th>
                                    <a class="doc-row-link" href="index.php?page=documento&amp;id=<?= (int) $r['id'] ?>">
                                        <strong><?= e($r['numero'] ?: 'Sem número') ?></strong>
                                        <small class="workspace-muted"><?= e($tipoLabel) ?></small>
                                    </a>
                                </th>
                                <td><?= e($r['fornecedor_nome'] ?? '—') ?></td>
                                <td><?= e($r['emitido_em'] ?? '—') ?></td>
                                <td><?= $r['valor_total'] === null ? '—' : 'R$ ' . number_format((float) $r['valor_total'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="workspace-tag <?= $r['status'] === 'ativo' ? 'is-success' : 'is-muted' ?>">
                                        <?= $r['status'] === 'ativo' ? 'Ativo' : 'Cancelado' ?>
                                    </span>
                                    <?php if ($r['motivo_cancelamento']): ?><small class="workspace-muted">
                                            <?= e($r['motivo_cancelamento']) ?></small><?php endif; ?>
                                </td>
                                <td><?= e($r['registrado_por']) ?></td>
                                <?php if ($canCancelDocuments): ?>
                                    <td><?php if ($r['status'] === 'ativo'): ?>
                                            <form method="post" action="index.php?page=documentos&amp;action=save"
                                                onsubmit="return confirm('Cancelar este documento? O histórico será preservado.');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="documento_id" value="<?= e($r['id']) ?>">
                                                <label class="sr-only" for="reason-<?= e($r['id']) ?>">Motivo do cancelamento</label>
                                                <input id="reason-<?= e($r['id']) ?>" name="motivo" required placeholder="Motivo">
                                                <button class="table-action">Cancelar</button>
                                            </form>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$records): ?>
                    <div class="workspace-empty"><strong>Nenhum documento registrado</strong></div><?php endif; ?>
            </div>

        <?php elseif ($currentPage === 'documento'):
            $doc = $workspace['document'] ?? null;
            $docItems = $workspace['items'] ?? [];
            $operationId = $workspace['operationId'] ?? null;
            ?>
            <?php if (!$doc): ?>
                <div class="workspace-card workspace-empty">
                    <span class="material-symbols-outlined" aria-hidden="true">description</span>
                    <strong>Documento não encontrado nesta unidade.</strong>
                    <a class="workspace-button workspace-button-secondary" href="index.php?page=documentos">Voltar à lista</a>
                </div>
            <?php else:
                $tipoNome = match ($doc['tipo'] ?? '') {
                    'nota_fiscal_eletronica' => 'Nota fiscal eletrônica',
                    'nota_produtor_rural' => 'Nota de produtor rural',
                    'termo_doacao' => 'Termo de doação',
                    default => 'Outro documento',
                };
                $isCanceled = ($doc['status'] ?? '') === 'cancelado';
                ?>
                <div class="doc-detail-header">
                    <a class="workspace-button workspace-button-secondary" href="index.php?page=documentos">
                        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Voltar à lista
                    </a>
                    <?php if ($operationId): ?>
                        <a class="workspace-button workspace-button-secondary"
                            href="index.php?page=operacao&amp;id=<?= (int) $operationId ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">swap_vert</span>Ver operação
                            #<?= (int) $operationId ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="workspace-card doc-summary-card <?= $isCanceled ? 'is-canceled' : '' ?>">
                    <div class="doc-summary-top">
                        <div>
                            <span class="workspace-eyebrow"><?= e($tipoNome) ?></span>
                            <h2 class="doc-summary-number"><?= e($doc['numero'] ?: 'Sem número') ?><?php if ($doc['serie']): ?>
                                    <small>série <?= e($doc['serie']) ?></small><?php endif; ?></h2>
                        </div>
                        <span class="workspace-tag <?= $isCanceled ? 'is-muted' : 'is-success' ?>" style="align-self:flex-start">
                            <?= $isCanceled ? 'Cancelado' : 'Ativo' ?>
                        </span>
                    </div>
                    <dl class="doc-meta-grid">
                        <div class="doc-meta-item">
                            <dt>Fornecedor / doador</dt>
                            <dd>
                                <?= e($doc['fornecedor_nome'] ?? '—') ?>
                                <?php if ($doc['fornecedor_cnpj'] ?? null): ?><small
                                        class="workspace-muted"><?= e($doc['fornecedor_cnpj']) ?></small><?php endif; ?>
                                <?php if (!empty($doc['agricultura_familiar'])): ?><span class="workspace-tag is-info">Agricultura
                                        familiar</span><?php endif; ?>
                            </dd>
                        </div>
                        <div class="doc-meta-item">
                            <dt>Valor total</dt>
                            <dd><?= $doc['valor_total'] === null ? '—' : 'R$ ' . number_format((float) $doc['valor_total'], 2, ',', '.') ?></dd>
                        </div>
                        <div class="doc-meta-item">
                            <dt>Emissão</dt>
                            <dd><?= e($doc['emitido_em'] ?? '—') ?></dd>
                        </div>
                        <div class="doc-meta-item">
                            <dt>Registro no sistema</dt>
                            <dd><?= e($doc['criado_em'] ? date('d/m/Y H:i', strtotime($doc['criado_em'])) : '—') ?> por
                                <?= e($doc['registrado_por'] ?? '—') ?></dd>
                        </div>
                        <?php if (!empty($doc['destinatario_conferido'])): ?>
                            <div class="doc-meta-item">
                                <dt>Destinatário conferido</dt>
                                <dd><span class="workspace-tag is-success">Sim</span></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($doc['identificacao_pnae'])): ?>
                            <div class="doc-meta-item">
                                <dt>Identificação PNAE</dt>
                                <dd><span class="workspace-tag is-info">Identificado</span></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($doc['anexo'])): ?>
                            <div class="doc-meta-item">
                                <dt>Anexo</dt>
                                <dd><a href="<?= e($doc['anexo']) ?>" target="_blank" rel="noopener">Abrir arquivo <span
                                            class="material-symbols-outlined"
                                            style="font-size:1em;vertical-align:middle">open_in_new</span></a></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($isCanceled): ?>
                        <div class="doc-cancel-info">
                            <span class="material-symbols-outlined" aria-hidden="true">cancel</span>
                            <div>
                                <strong>Cancelado<?= $doc['cancelado_por_nome'] ? ' por ' . e($doc['cancelado_por_nome']) : '' ?></strong>
                                <?php if ($doc['motivo_cancelamento'] ?? null): ?>
                                    <p><?= e($doc['motivo_cancelamento']) ?></p><?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($canCancelDocuments): ?>
                        <details class="doc-cancel-form">
                            <summary><span class="material-symbols-outlined" aria-hidden="true">cancel</span>Cancelar este documento
                            </summary>
                            <form method="post" action="index.php?page=documentos&amp;action=save"
                                onsubmit="return confirm('Cancelar este documento? O histórico será preservado.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="documento_id" value="<?= (int) $doc['id'] ?>">
                                <label class="workspace-field"><span>Motivo do cancelamento</span><input name="motivo" required
                                        placeholder="Descreva o motivo…"></label>
                                <button class="workspace-button workspace-button-danger" type="submit">Confirmar cancelamento</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>

                <div class="workspace-card">
                    <h2 class="workspace-subheading">Itens recebidos</h2>
                    <?php if (!$docItems): ?>
                        <div class="workspace-empty"><strong>Nenhum lote vinculado a este documento.</strong></div>
                    <?php else: ?>
                        <div class="workspace-table-wrap">
                            <table class="workspace-table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Quantidade</th>
                                        <th>Lote</th>
                                        <th>Validade</th>
                                        <th>Valor do lote</th>
                                        <th>Origem do recurso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $totalItens = 0;
                                    foreach ($docItems as $item):
                                        $totalItens += (float) ($item['valor_item'] ?? 0); ?>
                                        <tr>
                                            <th><?= e($item['produto_nome']) ?></th>
                                            <td><?= number_format((float) $item['quantidade'], 3, ',', '.') ?> <small
                                                    class="workspace-muted"><?= e($item['unidade_medida']) ?></small></td>
                                            <td><?= e($item['codigo_lote'] ?: '—') ?></td>
                                            <td>
                                                <?= $item['validade'] ? e(date('d/m/Y', strtotime($item['validade']))) : '—' ?>
                                                <?php if (!empty($item['validade_estimada'])): ?><span class="workspace-tag is-warning"
                                                        title="Data estimada">Estimada</span><?php endif; ?>
                                            </td>
                                            <td><?= $item['valor_item'] === null ? '—' : 'R$ ' . number_format((float) $item['valor_item'], 2, ',', '.') ?>
                                            </td>
                                            <td><?= e($item['origem_recurso'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" style="text-align:right">Total dos itens</th>
                                        <th>R$ <?= number_format($totalItens, 2, ',', '.') ?></th>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; /* doc exists */ ?>
        <?php endif; /* currentPage */ ?>

        <?php require VIEW_PATH . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>