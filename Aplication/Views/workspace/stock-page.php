<?php
$page = (string) ($_GET['page'] ?? 'estoque');
$data = $workspace ?? [];
$rows = $data['records'] ?? [];
$pagination = $data['pagination'] ?? null;
$fmtQty = static fn($value): string => number_format((float) $value, 2, ',', '.');
$fmtDate = static fn($value): string => $value ? date('d/m/Y', strtotime((string) $value)) : '—';
$typeLabel = ['ENTRADA' => 'Entrada', 'SAIDA' => 'Saída', 'AJUSTE' => 'Ajuste', 'ESTORNO' => 'Estorno'];
$reasonLabel = ['compra' => 'Compra', 'doacao' => 'Doação', 'consumo' => 'Consumo', 'perda' => 'Perda', 'vencimento' => 'Vencimento', 'descarte' => 'Descarte', 'erro_contagem' => 'Erro de contagem', 'entrada_nao_registrada' => 'Entrada não registrada', 'estorno' => 'Estorno'];
$originLabel = ['pnae_federal' => 'PNAE federal', 'recurso_proprio' => 'Recurso próprio', 'doacao' => 'Doação'];
$gradeLabel = ['in_natura_minimamente_processado' => 'In natura ou minimamente processado', 'ingrediente_culinario' => 'Ingrediente culinário', 'processado' => 'Processado', 'ultraprocessado' => 'Ultraprocessado', 'nao_classificado' => 'Não classificado'];
?>
<div class="workspace-page">
    <header class="workspace-heading">
        <div><span class="workspace-eyebrow">ESTOQUE E OPERAÇÕES</span>
            <h1><?= e($pageTitle) ?></h1>
            <p><?= e(match ($page) {
                'saida-rapida' => 'Registre consumo, perdas, vencimentos e descartes por produto.',
                'entrada-documento' => 'Registre um documento e os lotes recebidos em uma única operação.',
                'movimentacoes' => 'Pesquise entradas, saídas, ajustes e estornos registrados.',
                'operacao' => 'Consulte as linhas registradas e o histórico da operação.',
                'estoque' => 'Saldos e próximas validades calculados pelos movimentos dos lotes.',
                'produto' => 'Consulte lotes, saldos e histórico deste produto.',
                'ajuste-estoque' => 'Corrija a contagem com motivo e observação rastreáveis.',
                'alertas' => 'Priorize os lotes por validade e os produtos abaixo do mínimo.',
                'pendencias' => 'Revise divergências e diferenças geradas pelos registros.',
                'lista-compras' => 'Produtos abaixo do mínimo, agrupados pelo fornecedor mais recente.',
                default => 'Acompanhe os registros desta unidade.',
            }) ?></p>
        </div>
        <div class="workspace-heading-actions">
            <?php if ($page === 'movimentacoes'): ?>
                <?php if (in_array('registrar_entrada', $permissionIds ?? [], true)): ?><a
                        class="workspace-button workspace-button-primary" href="index.php?page=entrada-documento"><span
                            class="material-symbols-outlined" aria-hidden="true">input</span>Registrar
                        entrada</a><?php endif; ?>
                <?php if (in_array('registrar_saida', $permissionIds ?? [], true)): ?><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=saida-rapida"><span
                            class="material-symbols-outlined" aria-hidden="true">output</span>Registrar
                        saída</a><?php else: ?><button class="workspace-button workspace-button-secondary" type="button"
                        disabled title="Solicite a permissão Registrar saída ao administrador"><span
                            class="material-symbols-outlined" aria-hidden="true">output</span>Registrar
                        saída</button><?php endif; ?>
                <?php if (in_array('ajustar_estoque', $permissionIds ?? [], true)): ?><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=ajuste-estoque"><span
                            class="material-symbols-outlined" aria-hidden="true">tune</span>Ajustar estoque</a><?php endif; ?>
            <?php endif; ?>
            <?php if (in_array($page, ['estoque', 'alertas'], true)): ?><a
                    class="workspace-button workspace-button-primary" href="index.php?page=entrada-documento"><span
                        class="material-symbols-outlined" aria-hidden="true">add</span>Nova entrada</a><?php endif; ?>
            <?php if ($page === 'estoque'): ?><a class="workspace-button workspace-button-secondary"
                    href="index.php?page=saida-rapida"><span class="material-symbols-outlined"
                        aria-hidden="true">remove</span>Registrar saída</a><?php endif; ?>
            <?php if ($page === 'produto' && !empty($data['product'])): ?><a
                    class="workspace-button workspace-button-secondary"
                    href="index.php?page=produto-form&amp;id=<?= (int) $data['product']['id'] ?>">Editar
                    produto</a><?php endif; ?>
        </div>
    </header>

    <?php if (!empty($loadError)): ?><?php elseif (!empty($message)): ?>
        <div class="workspace-message" role="alert"><span class="material-symbols-outlined"
                aria-hidden="true">info</span><?= e($message) ?></div><?php elseif (!empty($_GET['saved'])): ?>
        <div class="workspace-message is-success" role="status"><span class="material-symbols-outlined"
                aria-hidden="true">check_circle</span>Operação registrada no histórico.</div>
    <?php elseif (!empty($_GET['reversed'])): ?>
        <div class="workspace-message is-success" role="status"><span class="material-symbols-outlined"
                aria-hidden="true">check_circle</span>Estorno registrado; as linhas originais foram preservadas.</div>
    <?php elseif (!empty($_GET['revisada'])): ?>
        <div class="workspace-message is-success" role="status"><span class="material-symbols-outlined"
                aria-hidden="true">check_circle</span>Pendência marcada como revisada.</div><?php endif; ?>

    <?php if (!empty($loadError)): ?>
        <div class="workspace-card workspace-load-error" role="alert"><span class="material-symbols-outlined"
                aria-hidden="true">cloud_off</span>
            <div><strong><?= e($message) ?></strong><a class="workspace-button workspace-button-secondary"
                    href="<?= e('index.php?' . http_build_query($_GET)) ?>">Tentar novamente</a></div>
        </div><?php else: ?>

        <?php if ($page === 'saida-rapida'): ?>
            <form style="width: 100%;" class="workspace-card workspace-form quick-exit-form" method="post"
                action="index.php?page=saida-rapida&amp;action=save">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <?php if (empty($data['products'])): ?>
                    <div class="workspace-empty"><span class="material-symbols-outlined"
                            aria-hidden="true">inventory_2</span><strong>Nenhum produto cadastrado</strong>
                        <p>Cadastre um produto antes de registrar saídas.</p>
                        <?php if (in_array('cadastrar_produto', $permissionIds ?? [], true)): ?><a
                                class="workspace-button workspace-button-primary" href="index.php?page=produto-form">Cadastrar
                                produto</a><?php endif; ?>
                    </div><?php else: ?>
                    <?php if (!empty($data['mostUsed'])): ?>
                        <section class="quick-most-used" aria-label="Produtos usados com frequência"><strong>Usados
                                recentemente</strong>
                            <div class="quick-most-used-list"><?php foreach ($data['mostUsed'] as $used): ?><button type="button"
                                        class="quick-product-chip"
                                        data-use-product="<?= (int) $used['id'] ?>"><?= e($used['nome']) ?></button><?php endforeach; ?>
                            </div>
                        </section><?php endif; ?>
                    <div class="workspace-form-grid">
                        <div class="workspace-field quick-product-field">
                            <span>Produto</span>
                            <div class="quick-product-combobox" data-product-combobox>
                                <input type="hidden" name="produto_id" data-stock-product value="">
                                <div class="combobox-input-wrap">
                                    <span class="material-symbols-outlined combobox-icon" aria-hidden="true">search</span>
                                    <input type="text"
                                           class="combobox-search-input"
                                           data-product-search-input
                                           placeholder="Buscar produto por nome (ex.: arroz, feijão)..."
                                           autocomplete="off"
                                           aria-expanded="false"
                                           aria-haspopup="listbox"
                                           role="combobox"
                                           required>
                                    <button type="button" class="combobox-clear-btn" data-product-clear-btn aria-label="Limpar produto selecionado" hidden title="Limpar seleção">
                                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                    </button>
                                    <span class="combobox-spinner" data-product-spinner hidden aria-hidden="true"></span>
                                </div>
                                <div class="combobox-dropdown" data-product-dropdown hidden role="listbox">
                                    <ul class="combobox-results" data-product-results></ul>
                                </div>
                                <script type="application/json" data-initial-products>
                                    <?= json_encode(array_map(static fn($p) => [
                                        'id' => (int) $p['id'],
                                        'nome' => (string) $p['nome'],
                                        'unidade_medida' => (string) $p['unidade_medida'],
                                        'aceita_fracao' => (int) $p['aceita_fracao'],
                                        'saldo' => round((float) $p['saldo'], 3),
                                    ], $data['products'] ?? []), JSON_UNESCAPED_UNICODE) ?>
                                </script>
                            </div>
                            <small data-selected-balance>O lote com validade mais próxima será sugerido primeiro (FEFO).</small>
                        </div>
                        <label class="workspace-field quick-field-reason"><span>Motivo da saída</span><select name="motivo"
                                required>
                                <option value="consumo">Consumo</option>
                                <option value="perda">Perda</option>
                                <option value="vencimento">Vencimento</option>
                                <option value="descarte">Descarte</option>
                            </select></label>
                        <label class="workspace-field quick-field-quantity workspace-field-wide"><span>Quantidade</span>
                            <div class="workspace-input-combo"><button type="button" data-qty-step="-1"
                                    aria-label="Diminuir quantidade">−</button><input name="quantidade" type="number" min="1"
                                    step="1" required inputmode="decimal" value="1"><button type="button" data-qty-step="1"
                                    aria-label="Aumentar quantidade">+</button><span data-selected-unit>unidade</span></div>
                            <div class="quick-quantity-shortcuts"><?php foreach ([1, 5, 10, 20] as $n): ?><button type="button"
                                        data-qty-set="<?= $n ?>"><?= $n ?></button><?php endforeach; ?></div><small
                                class="quick-balance-after" data-balance-after aria-live="polite">Saldo ficará: —</small>
                        </label>
                        <div class="workspace-field workspace-field-wide overdraw-fields" data-overdraw-fields hidden><label
                                class="workspace-field"><span>Motivo da diferença</span><select name="motivo_excedente">
                                    <option value="">Selecione</option>
                                    <option value="entrada_nao_registrada">Entrada não registrada</option>
                                    <option value="erro_contagem">Erro de contagem</option>
                                </select></label><small>A diferença será registrada para revisão. O saldo não ficará
                                negativo.</small></div>
                        <label class="workspace-field workspace-field-wide"><span>Observação
                                <small>(opcional)</small></span><textarea name="observacao" rows="3" maxlength="1000"
                                placeholder="Ex.: preparo do almoço"></textarea></label>
                    </div>
                    <div class="workspace-form-actions"><a href="index.php?page=estoque">Cancelar</a><button
                            class="workspace-button workspace-button-primary" type="submit"><span class="material-symbols-outlined"
                                aria-hidden="true">check</span>Confirmar saída</button></div>
                <?php endif; ?>
            </form>

        <?php elseif ($page === 'entrada-documento'): ?>
            <form class="workspace-card workspace-form" method="post" enctype="multipart/form-data"
                action="index.php?page=entrada-documento&amp;action=save" data-entry-form>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <h2 class="workspace-subheading">Documento de entrada</h2>
                <div class="workspace-form-grid">
                    <label class="workspace-field"><span>Tipo de documento</span><select name="tipo_documento" required>
                            <option value="nota_fiscal_eletronica">Nota fiscal eletrônica</option>
                            <option value="nota_produtor_rural">Nota de produtor rural</option>
                            <option value="termo_doacao">Termo de doação</option>
                            <option value="outro">Outro</option>
                        </select></label>
                    <label class="workspace-field"><span>Origem do recurso</span><select name="origem_recurso" required
                            data-resource>
                            <option value="">Selecione a origem</option>
                            <option value="pnae_federal">PNAE federal</option>
                            <option value="recurso_proprio">Recurso próprio</option>
                            <option value="doacao">Doação</option>
                        </select></label>
                    <label class="workspace-field" data-purchase-field><span>Fornecedor</span><select name="fornecedor_id">
                            <option value="">Sem fornecedor (ex.: doação)</option>
                            <?php foreach ($data['suppliers'] as $supplier): ?>
                                <option value="<?= (int) $supplier['id'] ?>">
                                    <?= e($supplier['nome']) ?>
                                    <?= $supplier['agricultura_familiar'] ? ' · Agricultura familiar' : '' ?>
                                </option><?php endforeach; ?>
                        </select></label>
                    <!-- <label class="workspace-field" data-donation-field hidden><span>Doador</span><input name="doador_nome"
                            maxlength="220"></label> -->
                    <label class="workspace-field" data-purchase-field><span>Número</span><input name="numero"
                            maxlength="80"></label>
                    <label class="workspace-field" data-purchase-field><span>Série</span><input name="serie"
                            maxlength="40"></label>
                    <label class="workspace-field" data-purchase-field><span>Emitido em</span><input name="emitido_em"
                            type="date"></label>
                    <label class="workspace-field"><span>Recebido em</span><input name="recebido_em" type="date"
                            value="<?= date('Y-m-d') ?>" required></label>
                    <label class="workspace-field" data-purchase-field><span>Valor total (R$)</span><input name="valor_total"
                            type="number" min="0" step="0.01" data-document-total></label>
                    <label class="workspace-field workspace-field-wide"><span>Anexo (PDF, JPG ou PNG; até 5 MB)</span><input
                            name="anexo" type="file" accept="application/pdf,image/jpeg,image/png"
                            capture="environment"><small>O arquivo será guardado com acesso restrito. <span
                                data-upload-name></span></small></label>
                    <label class="workspace-check"><input type="checkbox" name="destinatario_conferido"
                            value="1"><span>Destinatário conferido</span></label>
                    <label class="workspace-check" data-pnae-field hidden><input type="checkbox" name="identificada_pnae"
                            value="1"><span>Identificação do PNAE conferida</span></label>
                </div>
                <div class="workspace-form-grid workspace-lines-heading">
                    <div>
                        <h2 class="workspace-subheading">Itens e lotes</h2>
                        <p>Cada item gera um lote separado e uma linha de entrada no histórico.</p>
                    </div>
                </div>
                <script type="application/json" data-entry-initial-products>
                    <?= json_encode(array_map(static fn($p) => [
                        'id' => (int) $p['id'],
                        'nome' => (string) $p['nome'],
                        'unidade_medida' => (string) $p['unidade_medida'],
                        'validade_padrao_dias' => $p['validade_padrao_dias'] !== null ? (int) $p['validade_padrao_dias'] : null,
                        'aceita_fracao' => (int) ($p['aceita_fracao'] ?? 0),
                    ], $data['products'] ?? []), JSON_UNESCAPED_UNICODE) ?>
                </script>
                <div class="entry-lines" data-entry-lines>
                    <?php if (!empty($data['documents'])): ?><label class="workspace-field workspace-field-wide"><span>Substitui
                                documento anterior <small>(opcional)</small></span><select name="substitui_id">
                                <option value="">Não substituir</option><?php foreach ($data['documents'] as $document): ?>
                                    <option value="<?= (int) $document['id'] ?>">#<?= (int) $document['id'] ?> ·
                                        <?= e($document['tipo']) ?>                 <?= e($document['numero'] ?: 'sem número') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select><small>Para manter o estoque correto, estorne antes a operação de entrada ligada ao documento
                                substituído.</small></label><?php endif; ?>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <fieldset class="entry-line" data-entry-line-item>
                            <legend>Item <?= $i + 1 ?></legend>
                            <div class="workspace-field entry-product-field">
                                <span>Produto</span>
                                <div class="entry-product-combobox" data-item-combobox>
                                    <input type="hidden" name="itens[<?= $i ?>][produto_id]" data-item-product value="">
                                    <div class="combobox-input-wrap">
                                        <span class="material-symbols-outlined combobox-icon" aria-hidden="true">search</span>
                                        <input type="text"
                                               class="combobox-search-input"
                                               data-item-search-input
                                               placeholder="Buscar produto..."
                                               autocomplete="off"
                                               aria-expanded="false"
                                               aria-haspopup="listbox"
                                               role="combobox">
                                        <button type="button" class="combobox-clear-btn" data-item-clear-btn aria-label="Limpar produto" hidden title="Limpar seleção">
                                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                        </button>
                                        <span class="combobox-spinner" data-item-spinner hidden aria-hidden="true"></span>
                                    </div>
                                    <div class="combobox-dropdown" data-item-dropdown hidden role="listbox">
                                        <ul class="combobox-results" data-item-results></ul>
                                    </div>
                                </div>
                            </div>
                            <label class="workspace-field"><span>Quantidade</span><input
                                    name="itens[<?= $i ?>][quantidade]" type="number" min="0.001" step="any"
                                    inputmode="decimal"></label><label class="workspace-field"><span>Validade</span><input
                                    name="itens[<?= $i ?>][validade]" type="date" data-item-validity></label><label
                                class="workspace-field"><span>Código do lote</span><input name="itens[<?= $i ?>][codigo_lote]"
                                    maxlength="100"></label><label class="workspace-field" data-purchase-field><span>Valor do lote
                                    (R$)</span><input name="itens[<?= $i ?>][valor_item]" type="number" min="0" step="0.01"
                                    inputmode="decimal"></label><label class="workspace-check"><input
                                    name="itens[<?= $i ?>][validade_estimada]" type="checkbox" value="1"><span>Validade
                                    estimada</span></label><button class="entry-remove" type="button" data-remove-entry-line><span
                                    class="material-symbols-outlined" aria-hidden="true">delete</span>Remover item</button>
                        </fieldset><?php endfor; ?>
                </div>
                <div class="entry-line-actions"><button class="workspace-button workspace-button-secondary" type="button"
                        data-add-entry-line><span class="material-symbols-outlined" aria-hidden="true">add</span>Adicionar
                        item</button></div>
                <div class="entry-summary" aria-live="polite">
                    <div class="entry-summary-metrics">
                        <div class="summary-metric">
                            <span class="summary-metric-label">Soma dos itens</span>
                            <strong class="summary-value" data-entry-items-sum>R$ 0,00</strong>
                        </div>
                        <div class="summary-metric-sep" aria-hidden="true"></div>
                        <div class="summary-metric" data-purchase-field>
                            <span class="summary-metric-label">Total da nota</span>
                            <strong class="summary-value" data-entry-doc-total>R$ 0,00</strong>
                        </div>
                        <div class="summary-metric-sep" data-purchase-field aria-hidden="true"></div>
                        <div class="summary-metric" data-purchase-field>
                            <span class="summary-metric-label">Diferença</span>
                            <strong class="summary-value" data-entry-diff>R$ 0,00</strong>
                        </div>
                    </div>
                    <div class="entry-summary-status">
                        <span class="entry-status-badge is-neutral" data-entry-reconcile>
                            <span class="material-symbols-outlined" aria-hidden="true">hourglass_empty</span>
                            <span data-entry-reconcile-text>Aguardando valores</span>
                        </span>
                    </div>
                </div>
                <?php if (!$data['products']): ?>
                    <p class="workspace-inline-warning">Cadastre produtos antes de registrar a entrada.</p><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=produto-form">Cadastrar
                        produto</a><?php endif; ?>
                <div class="workspace-form-actions entry-form-footer"><a href="index.php?page=visao-geral">Cancelar</a><button
                        class="workspace-button workspace-button-primary" type="submit" <?= !$data['products'] ? 'disabled' : '' ?>>Registrar entrada</button></div>
            </form>

        <?php elseif ($page === 'estoque'): ?>
            <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page" value="estoque"><input
                    type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                    value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                        class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                        value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar produto ou categoria"></label><label
                    class="workspace-field-inline"><span>Unidade</span><select name="unidade_medida">
                        <option value="">Todas</option><?php foreach (['kg', 'L', 'un', 'pct'] as $unit): ?>
                            <option value="<?= e($unit) ?>" <?= ($filters['unidade_medida'] ?? '') === $unit ? 'selected' : '' ?>>
                                <?= e($unit) ?>
                            </option><?php endforeach; ?>
                    </select></label><label class="workspace-field-inline"><span>Situação do saldo</span><select
                        name="saldo_status">
                        <option value="">Todos</option>
                        <option value="baixo" <?= ($filters['saldo_status'] ?? '') === 'baixo' ? 'selected' : '' ?>>No mínimo ou
                            abaixo
                        </option>
                        <option value="zerado" <?= ($filters['saldo_status'] ?? '') === 'zerado' ? 'selected' : '' ?>>Zerado
                        </option>
                        <option value="normal" <?= ($filters['saldo_status'] ?? '') === 'normal' ? 'selected' : '' ?>>Acima do
                            mínimo
                        </option>
                    </select></label><label class="workspace-field-inline"><span>Compra com PNAE</span><select
                        name="vedado_pnae">
                        <option value="">Todas</option>
                        <option value="0" <?= ($filters['vedado_pnae'] ?? '') === '0' ? 'selected' : '' ?>>Permitida</option>
                        <option value="1" <?= ($filters['vedado_pnae'] ?? '') === '1' ? 'selected' : '' ?>>Vedada</option>
                    </select></label><button class="workspace-button workspace-button-secondary"
                    type="submit">Filtrar</button><a class="workspace-button workspace-button-secondary"
                    href="index.php?page=estoque">Limpar</a></form>
            <?php if (!$rows): ?>
                <div class="workspace-card workspace-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">inventory_2</span><strong><?= empty($filters['q']) ? 'Estoque ainda sem produtos' : 'Nenhum produto corresponde à busca' ?></strong>
                    <p>Cadastre produtos e registre entradas para começar a acompanhar os saldos.</p>
                </div><?php else: ?>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Saldo</th>
                                <th>Mínimo</th>
                                <th>Próxima validade</th>
                                <th>Lotes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $row): ?>
                                <tr>
                                    <th scope="row"><a
                                            href="index.php?page=produto&amp;id=<?= (int) $row['id'] ?>"><?= e($row['nome']) ?></a><?php if ($row['vedado_pnae']): ?><span
                                                class="workspace-tag is-danger">Vedado PNAE</span><?php endif; ?></th>
                                    <td><?= e($fmtQty($row['saldo'])) ?>                 <?= e($row['unidade_medida']) ?></td>
                                    <td><?= e($fmtQty($row['estoque_minimo'])) ?>                 <?= e($row['unidade_medida']) ?></td>
                                    <td><?= e($fmtDate($row['proxima_validade'])) ?></td>
                                    <td><?= (int) $row['lotes'] ?></td>
                                    <td><a class="workspace-row-link"
                                            href="index.php?page=produto&amp;id=<?= (int) $row['id'] ?>">Detalhes<span
                                                class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div><?php endif; ?>

            <?php require VIEW_PATH . '/partials/pagination.php'; ?>
        <?php elseif ($page === 'produto'): ?>
            <?php if (empty($data['product'])): ?>
                <div class="workspace-card workspace-empty"><strong>Produto não encontrado nesta unidade.</strong><a
                        href="index.php?page=estoque">Voltar ao estoque</a></div><?php else:
                $product = $data['product']; ?>
                <?php $lowStock = (float) $data['balance'] <= 0 || ((float) $product['estoque_minimo'] > 0 && (float) $data['balance'] < (float) $product['estoque_minimo']); ?>
                <section class="workspace-card product-summary">
                    <div class="product-summary-main"><span class="workspace-eyebrow">PRODUTO</span>
                        <h2><?= e($product['nome']) ?></h2>
                        <div class="product-meta">
                            <span><?= e($product['categoria_nome']) ?></span><span><?= e($product['unidade_medida']) ?></span><span>Mínimo:
                                <?= e($fmtQty($product['estoque_minimo'])) ?>             <?= e($product['unidade_medida']) ?></span>
                        </div>
                        <div class="product-metrics">
                            <article class="product-metric product-metric-stock <?= $lowStock ? 'is-low-stock' : '' ?>"><span>Saldo
                                    atual</span><strong><?= e($fmtQty($data['balance'])) ?>
                                    <small><?= e($product['unidade_medida']) ?></small></strong></article>
                            <article class="product-metric"><span>Consumo
                                    médio</span><strong><?= e($fmtQty($data['average_daily_consumption'])) ?>
                                    <small><?= e($product['unidade_medida']) ?>/dia</small></strong></article>
                            <article class="product-metric"><span>Cobertura
                                    estimada</span><strong><?= $data['coverage_days'] === null ? 'Sem histórico' : e(number_format((float) $data['coverage_days'], 1, ',', '.') . ' dias') ?></strong>
                            </article>
                        </div>
                        <p class="workspace-muted">Estimativa baseada nos últimos <?= (int) $data['window_days'] ?> dias e apenas em
                            saídas com motivo “consumo”.</p>
                    </div>
                    <div class="product-grade"><small>Classificação
                            PNAE</small><strong><?= e($gradeLabel[$product['grau_processamento']] ?? 'Não classificado') ?></strong>
                    </div>
                </section>
                <section class="workspace-card">
                    <h2 class="workspace-subheading">Lotes e saldos</h2><?php if (!$data['lots']): ?>
                        <p class="workspace-muted">Nenhum lote registrado para este produto.</p><?php else: ?>
                        <div class="workspace-table-wrap">
                            <table class="workspace-table">
                                <thead>
                                    <tr>
                                        <th>Lote</th>
                                        <th>Validade</th>
                                        <th>Saldo</th>
                                        <th>Origem</th>
                                        <th>Valor do lote</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($data['lots'] as $lot): ?>
                                        <tr>
                                            <td><?= e($lot['codigo_lote'] ?: 'Sem código') ?><?= $lot['validade_estimada'] ? ' · validade estimada' : '' ?>
                                            </td>
                                            <td><?= e($fmtDate($lot['validade'])) ?></td>
                                            <td><?= e($fmtQty($lot['saldo'])) ?>                     <?= e($product['unidade_medida']) ?></td>
                                            <td><?= e($originLabel[$lot['origem_recurso']] ?? 'Outra origem') ?></td>
                                            <td><?= $lot['valor_item'] !== null ? e('R$ ' . number_format((float) $lot['valor_item'], 2, ',', '.')) : '—' ?>
                                            </td>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
                <section class="workspace-card">
                    <h2 class="workspace-subheading">Histórico recente</h2><?php if (!$data['movements']): ?>
                        <p class="workspace-muted">Nenhuma movimentação registrada.</p><?php else: ?>
                        <div class="workspace-table-wrap">
                            <table class="workspace-table">
                                <thead>
                                    <tr>
                                        <th>Quando</th>
                                        <th>Tipo</th>
                                        <th>Motivo</th>
                                        <th>Quantidade</th>
                                        <th>Responsável</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($data['movements'] as $move): ?>
                                        <tr>
                                            <td><?= e(date('d/m/Y H:i', strtotime($move['criado_em']))) ?></td>
                                            <td><?= e($typeLabel[$move['tipo']] ?? 'Movimentação') ?></td>
                                            <td><?= e($reasonLabel[$move['motivo']] ?? 'Outro motivo') ?></td>
                                            <td><?= e($fmtQty($move['quantidade'])) ?>                     <?= e($product['unidade_medida']) ?></td>
                                            <td><?= e($move['usuario_nome'] ?: 'Usuário desativado') ?></td>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
            <?php endif; ?>

        <?php elseif ($page === 'alertas'): ?>
            <?php $activeTab = $data['activeTab'] ?? 'validade';
            $tabValidityQuery = $_GET;
            $tabValidityQuery['aba'] = 'validade';
            $tabStockQuery = $_GET;
            $tabStockQuery['aba'] = 'estoque'; ?>
            <nav class="alert-tabs" role="tablist" aria-label="Tipos de alerta"><a role="tab"
                    aria-selected="<?= $activeTab === 'validade' ? 'true' : 'false' ?>"
                    class="alert-tab <?= $activeTab === 'validade' ? 'is-active' : '' ?>"
                    href="index.php?<?= e(http_build_query($tabValidityQuery)) ?>"><span>Lotes vencidos ou próximos da
                        validade</span><strong><?= (int) ($data['expiry']['pagination']['total'] ?? 0) ?></strong></a><a
                    role="tab" aria-selected="<?= $activeTab === 'estoque' ? 'true' : 'false' ?>"
                    class="alert-tab <?= $activeTab === 'estoque' ? 'is-active' : '' ?>"
                    href="index.php?<?= e(http_build_query($tabStockQuery)) ?>"><span>Abaixo do estoque
                        mínimo</span><strong><?= (int) ($data['lowStock']['pagination']['total'] ?? 0) ?></strong></a></nav>
            <?php if ($activeTab === 'validade'): ?>
                <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page" value="alertas"><input
                        type="hidden" name="aba" value="validade"><input type="hidden" name="pagina_validade" value="1"><input
                        type="hidden" name="pagina_estoque" value="<?= e($filters['pagina_estoque'] ?? 1) ?>"><input type="hidden"
                        name="por_pagina" value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                            class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                            value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar produto ou lote"></label><label
                        class="workspace-field-inline"><span>Validade</span><select name="tipo_alerta">
                            <option value="">Todos os alertas</option>
                            <option value="vencidos" <?= ($filters['tipo_alerta'] ?? '') === 'vencidos' ? 'selected' : '' ?>>Vencidos
                            </option>
                            <option value="proximos" <?= ($filters['tipo_alerta'] ?? '') === 'proximos' ? 'selected' : '' ?>>A vencer
                            </option>
                        </select></label><button class="workspace-button workspace-button-secondary">Filtrar</button><a
                        class="workspace-button workspace-button-secondary"
                        href="index.php?page=alertas&amp;aba=validade">Limpar</a></form>
                <div class="workspace-card workspace-alert-group">
                    <div class="workspace-section-heading">
                        <h2>Lotes vencidos ou próximos da validade</h2>
                        <span><?= (int) ($data['expiry']['pagination']['total'] ?? 0) ?></span>
                    </div><?php if (empty($data['expiry']['records'])): ?>
                        <div class="workspace-empty compact"><span class="material-symbols-outlined"
                                aria-hidden="true">event_available</span>
                            <p>Nenhum lote corresponde aos filtros de validade.</p>
                        </div><?php else: ?>
                        <div class="workspace-table-wrap">
                            <table class="workspace-table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Lote</th>
                                        <th>Validade</th>
                                        <th>Situação</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($data['expiry']['records'] as $lot): ?>
                                        <tr>
                                            <th><a
                                                    href="index.php?page=produto&amp;id=<?= (int) $lot['produto_id'] ?>"><?= e($lot['nome']) ?></a>
                                            </th>
                                            <td><?= e($lot['codigo_lote'] ?: '—') ?></td>
                                            <td><?= e($fmtDate($lot['validade'])) ?></td>
                                            <td><span
                                                    class="workspace-tag <?= (int) $lot['dias'] < 0 ? 'is-danger' : 'is-warning' ?>"><?= (int) $lot['dias'] < 0 ? 'Vencido' : ((int) $lot['dias'] === 0 ? 'Vence hoje' : 'Em ' . (int) $lot['dias'] . ' dias') ?></span>
                                            </td>
                                            <td><?= e($fmtQty($lot['saldo'])) ?>                     <?= e($lot['unidade_medida']) ?></td>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
                <?php $pagination = $data['expiry']['pagination'];
                $paginationPageParam = 'pagina_validade';
                require VIEW_PATH . '/partials/pagination.php'; ?>
            <?php else: ?>
                <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page" value="alertas"><input
                        type="hidden" name="aba" value="estoque"><input type="hidden" name="pagina_estoque" value="1"><input
                        type="hidden" name="pagina_validade" value="<?= e($filters['pagina_validade'] ?? 1) ?>"><input type="hidden"
                        name="por_pagina" value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                            class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                            value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar produto"></label><label
                        class="workspace-field-inline"><span>Saldo</span><select name="status_estoque">
                            <option value="">No mínimo ou abaixo</option>
                            <option value="zerado" <?= ($filters['status_estoque'] ?? '') === 'zerado' ? 'selected' : '' ?>>Zerado
                            </option>
                            <option value="abaixo" <?= ($filters['status_estoque'] ?? '') === 'abaixo' ? 'selected' : '' ?>>Abaixo do
                                mínimo
                            </option>
                        </select></label><button class="workspace-button workspace-button-secondary">Filtrar</button><a
                        class="workspace-button workspace-button-secondary" href="index.php?page=alertas&amp;aba=estoque">Limpar</a>
                </form>
                <div class="workspace-card workspace-alert-group">
                    <div class="workspace-section-heading">
                        <h2>Abaixo do estoque mínimo</h2><span><?= (int) ($data['lowStock']['pagination']['total'] ?? 0) ?></span>
                    </div><?php if (empty($data['lowStock']['records'])): ?>
                        <div class="workspace-empty compact"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                            <p>Nenhum produto corresponde aos filtros de estoque.</p>
                        </div><?php else: ?>
                        <div class="workspace-table-wrap">
                            <table class="workspace-table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Saldo</th>
                                        <th>Mínimo</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($data['lowStock']['records'] as $row): ?>
                                        <tr>
                                            <th><a href="index.php?page=produto&amp;id=<?= (int) $row['id'] ?>"><?= e($row['nome']) ?></a>
                                            </th>
                                            <td><?= e($fmtQty($row['saldo'])) ?>                     <?= e($row['unidade_medida']) ?></td>
                                            <td><?= e($fmtQty($row['estoque_minimo'])) ?>                     <?= e($row['unidade_medida']) ?></td>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
                <?php $pagination = $data['lowStock']['pagination'];
                $paginationPageParam = 'pagina_estoque';
                require VIEW_PATH . '/partials/pagination.php'; ?>
            <?php endif; ?>

        <?php elseif ($page === 'movimentacoes'): ?>
            <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                    value="movimentacoes"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                    value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                        class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                        value="<?= e($filters['q'] ?? '') ?>" placeholder="Produto, lote ou usuário"></label><label
                    class="workspace-field-inline"><span>Tipo</span><select name="tipo">
                        <option value="">Todos</option><?php foreach (['ENTRADA', 'SAIDA', 'AJUSTE', 'ESTORNO'] as $type): ?>
                            <option value="<?= $type ?>" <?= ($filters['tipo'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?>
                            </option><?php endforeach; ?>
                    </select></label><label class="workspace-field-inline"><span>Motivo</span><select name="motivo">
                        <option value="">Todos</option>
                        <?php foreach (['compra' => 'Compra', 'doacao' => 'Doação', 'consumo' => 'Consumo', 'perda' => 'Perda', 'vencimento' => 'Vencimento', 'descarte' => 'Descarte', 'erro_contagem' => 'Erro de contagem', 'entrada_nao_registrada' => 'Entrada não registrada', 'estorno' => 'Estorno'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($filters['motivo'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option><?php endforeach; ?>
                    </select></label><label class="workspace-field-inline"><span>De</span><input name="de" type="date"
                        value="<?= e($filters['de'] ?? '') ?>"></label><label
                    class="workspace-field-inline"><span>Até</span><input name="ate" type="date"
                        value="<?= e($filters['ate'] ?? '') ?>"></label><button
                    class="workspace-button workspace-button-secondary" type="submit">Filtrar</button><a
                    class="workspace-button workspace-button-secondary" href="index.php?page=movimentacoes">Limpar</a></form>
            <?php if (!$rows): ?>
                <div class="workspace-card workspace-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">history</span><strong>Nenhuma movimentação encontrada</strong>
                    <p>As entradas, saídas, ajustes e estornos aparecerão aqui.</p>
                </div><?php else: ?>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Data e hora</th>
                                <th>Tipo</th>
                                <th>Produto</th>
                                <th>Motivo</th>
                                <th>Quantidade</th>
                                <th>Usuário</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e(date('d/m/Y H:i', strtotime($row['criado_em']))) ?></td>
                                    <td><?= e($typeLabel[$row['tipo']] ?? 'Movimentação') ?></td>
                                    <td><a
                                            href="index.php?page=produto&amp;id=<?= (int) $row['produto_id'] ?>"><?= e($row['produto_nome']) ?></a>
                                    </td>
                                    <td><?= e($reasonLabel[$row['motivo']] ?? 'Outro motivo') ?></td>
                                    <td><?= e($fmtQty($row['quantidade'])) ?>                 <?= e($row['unidade_medida']) ?></td>
                                    <td><?= e($row['usuario_nome'] ?: 'Usuário desativado') ?></td>
                                    <td><a class="workspace-row-link"
                                            href="index.php?page=operacao&amp;id=<?= (int) $row['operacao_id'] ?>">Operação</a></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div><?php endif; ?>

            <?php require VIEW_PATH . '/partials/pagination.php'; ?>
        <?php elseif ($page === 'operacao'): ?>
            <?php if (empty($data['operation'])): ?>
                <div class="workspace-card workspace-empty"><strong>Operação não encontrada nesta unidade.</strong><a
                        href="index.php?page=movimentacoes">Voltar ao histórico</a></div>
            <?php else:
                $operation = $data['operation']; ?>
                <section class="workspace-card operation-summary">
                    <div><span class="workspace-eyebrow">OPERAÇÃO #<?= (int) $operation['id'] ?></span>
                        <h2><?= e($typeLabel[$operation['tipo']] ?? 'Operação') ?> ·
                            <?= e($reasonLabel[$operation['motivo']] ?? 'Outro motivo') ?>
                        </h2>
                        <p>Registrada em <?= e(date('d/m/Y H:i', strtotime($operation['criado_em']))) ?> por
                            <?= e($operation['usuario_nome']) ?>
                            <?= $operation['documento_numero'] ? ' · Documento ' . e($operation['documento_numero']) : '' ?>
                        </p><?php if ($operation['observacao']): ?>
                            <p><?= e($operation['observacao']) ?></p><?php endif; ?>
                    </div>
                    <?php if (empty($operation['estorna_operacao_id']) && $operation['tipo'] !== 'ESTORNO' && $canAdjust): ?>
                        <form method="post" action="index.php?page=operacao&amp;action=reverse"
                            data-confirm="Estornar esta operação? O sistema criará linhas inversas no histórico."><input type="hidden"
                                name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="operacao_id"
                                value="<?= (int) $operation['id'] ?>"><label class="workspace-field"><span>Motivo do
                                    estorno</span><input name="observacao" required maxlength="1000"></label><button
                                class="workspace-button workspace-button-danger" type="submit">Desfazer operação (estorno)</button>
                        </form><?php elseif (!empty($operation['estorna_operacao_id'])): ?><span class="workspace-tag">Operação de
                            estorno</span><?php else: ?><span class="workspace-muted">Estorno não disponível nesta
                            operação</span><?php endif; ?>
                </section>
                <section class="workspace-card">
                    <h2 class="workspace-subheading">Linhas da operação</h2>
                    <div class="workspace-table-wrap">
                        <table class="workspace-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Lote</th>
                                    <th>Validade</th>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>Registrada em</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($data['lines'] as $line): ?>
                                    <tr>
                                        <th><?= e($line['produto_nome']) ?></th>
                                        <td><?= e($line['codigo_lote'] ?: '—') ?></td>
                                        <td><?= e($fmtDate($line['validade'])) ?></td>
                                        <td><?= e($typeLabel[$line['tipo']] ?? 'Movimentação') ?></td>
                                        <td><?= e($fmtQty($line['quantidade'])) ?>                 <?= e($line['unidade_medida'] ?? '') ?></td>
                                        <td><?= e(date('d/m/Y H:i', strtotime($line['criado_em']))) ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

        <?php elseif ($page === 'ajuste-estoque'): ?>
            <form class="workspace-card workspace-form stock-adjust-form" method="post"
                action="index.php?page=ajuste-estoque&amp;action=save"><input type="hidden" name="csrf_token"
                    value="<?= e($csrfToken) ?>">
                <?php if (empty($data['lots'])): ?>
                    <div class="workspace-empty"><span class="material-symbols-outlined"
                            aria-hidden="true">inventory_2</span><strong>Nenhum lote disponível</strong>
                        <p>Registre uma entrada com lote antes de fazer um ajuste de estoque.</p><a
                            class="workspace-button workspace-button-primary" href="index.php?page=entrada-documento">Registrar
                            entrada</a>
                    </div><?php else: ?>
                    <div class="workspace-form-grid">
                        <label class="workspace-field workspace-field-wide"><span>Produto e lote</span>
                            <select name="lote_id" data-adjust-lot required>
                                <option value="">Selecione um lote</option><?php foreach ($data['lots'] as $lot): ?>
                                    <option value="<?= (int) $lot['id'] ?>" data-balance="<?= (float) $lot['saldo'] ?>"
                                        data-unit="<?= e($lot['unidade_medida']) ?>" data-fraction="<?= (int) $lot['aceita_fracao'] ?>"
                                        data-product="<?= e($lot['nome']) ?>" data-lot="<?= e($lot['codigo_lote'] ?: 'sem código') ?>"
                                        data-expiry="<?= e($fmtDate($lot['validade'])) ?>"><?= e($lot['nome']) ?> · lote
                                        <?= e($lot['codigo_lote'] ?: 'sem código') ?> · <?= e($fmtDate($lot['validade'])) ?> · saldo
                                        <?= e($fmtQty($lot['saldo'])) ?>                 <?= e($lot['unidade_medida']) ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="workspace-field"><span>Ação</span><select name="sentido" required>
                                <option value="">Selecione</option>
                                <option value="acrescentar">Acrescentar ao saldo</option>
                                <option value="reduzir">Reduzir do saldo</option>
                            </select></label>
                        <label class="workspace-field"><span>Quantidade da diferença</span>
                            <div class="workspace-input-combo"><input name="quantidade" type="number" min="0.001" step="any"
                                    required inputmode="decimal" placeholder="Ex.: 2,5 kg"></div>
                        </label>
                        <label class="workspace-field"><span>Motivo</span><select name="motivo" required>
                                <option value="erro_contagem">Erro de contagem</option>
                                <option value="entrada_nao_registrada">Entrada não registrada</option>
                                <option value="perda">Perda</option>
                                <option value="descarte">Descarte</option>
                            </select></label>
                        <label class="workspace-field workspace-field-wide"><span>Observação da contagem</span><textarea
                                name="observacao" rows="3" required
                                placeholder="Informe o que foi contado e como a diferença foi identificada."></textarea><small>Essa
                                informação fica registrada no histórico para conferência.</small></label>
                    </div>
                    <div class="workspace-form-actions"><a href="index.php?page=estoque">Cancelar</a><button
                            class="workspace-button workspace-button-primary" type="submit"><span class="material-symbols-outlined"
                                aria-hidden="true">check</span>Registrar ajuste</button></div><?php endif; ?>
            </form>

        <?php elseif ($page === 'pendencias'): ?>
            <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                    value="pendencias"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                    value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                        class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                        value="<?= e($filters['q'] ?? '') ?>" placeholder="Operação, responsável ou observação"></label><label
                    class="workspace-field-inline"><span>Status</span><select name="status">
                        <option value="">Todos</option>
                        <option value="aberta" <?= ($filters['status'] ?? '') === 'aberta' ? 'selected' : '' ?>>Aberta</option>
                        <option value="revisada" <?= ($filters['status'] ?? '') === 'revisada' ? 'selected' : '' ?>>Revisada
                        </option>
                    </select></label><button class="workspace-button workspace-button-secondary">Filtrar</button><a
                    class="workspace-button workspace-button-secondary" href="index.php?page=pendencias">Limpar</a></form>
            <?php if (!$rows): ?>
                <div class="workspace-card workspace-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">task_alt</span><strong>Nenhuma pendência aberta</strong>
                    <p>Divergências que precisem de revisão aparecerão aqui.</p>
                </div><?php else: ?>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Operação</th>
                                <th>Motivo</th>
                                <th>Observação</th>
                                <th>Data</th>
                                <th>Revisão</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><span
                                            class="workspace-tag <?= $row['status'] === 'aberta' ? 'is-warning' : '' ?>"><?= e($row['status'] === 'aberta' ? 'Aberta' : 'Revisada') ?></span>
                                    </td>
                                    <td><a
                                            href="index.php?page=operacao&amp;id=<?= (int) $row['operacao_id'] ?>">#<?= (int) $row['operacao_id'] ?></a>
                                    </td>
                                    <td><?= e($reasonLabel[$row['motivo']] ?? 'Outro motivo') ?></td>
                                    <td><?= e($row['observacao']) ?></td>
                                    <td><?= e(date('d/m/Y H:i', strtotime($row['criado_em']))) ?></td>
                                    <td><?php if ($row['status'] === 'aberta'): ?>
                                            <form class="review-inline" method="post" action="index.php?page=pendencias&amp;action=review">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden"
                                                    name="pendencia_id" value="<?= (int) $row['id'] ?>"><input name="observacao"
                                                    placeholder="Nota da revisão"><button
                                                    class="workspace-button workspace-button-secondary" type="submit">Marcar
                                                    revisada</button>
                                            </form>
                                        <?php else: ?>                     <?= e($fmtDate($row['revisado_em'])) ?>                 <?php endif; ?>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div><?php endif; ?>

            <?php require VIEW_PATH . '/partials/pagination.php'; ?>
        <?php elseif ($page === 'lista-compras'): ?>
            <form class="workspace-toolbar product-filters" method="get"><input type="hidden" name="page"
                    value="lista-compras"><input type="hidden" name="pagina" value="1"><input type="hidden" name="por_pagina"
                    value="<?= e($filters['por_pagina'] ?? 20) ?>"><label class="workspace-search"><span
                        class="material-symbols-outlined" aria-hidden="true">search</span><input name="q"
                        value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar produto"></label><label
                    class="workspace-field-inline"><span>Fornecedor</span><input name="fornecedor"
                        value="<?= e($filters['fornecedor'] ?? '') ?>" placeholder="Nome do fornecedor"></label><button
                    class="workspace-button workspace-button-secondary">Filtrar</button><a
                    class="workspace-button workspace-button-secondary" href="index.php?page=lista-compras">Limpar</a></form>
            <?php if (!$rows): ?>
                <div class="workspace-card workspace-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">shopping_cart</span><strong>Nenhum item abaixo do mínimo</strong>
                    <p>Cadastre estoque mínimo e mantenha os saldos atualizados para calcular reposições.</p>
                </div><?php else: ?>
                <div class="workspace-card workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Fornecedor sugerido</th>
                                <th>Produto</th>
                                <th>Saldo atual</th>
                                <th>Estoque mínimo</th>
                                <th>Quantidade sugerida</th>
                                <th>Reposição</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['fornecedor_nome'] ?: 'Sem fornecedor vinculado') ?></td>
                                    <th><a href="index.php?page=produto&amp;id=<?= (int) $row['id'] ?>"><?= e($row['nome']) ?></a></th>
                                    <td><?= e($fmtQty($row['saldo'])) ?>                 <?= e($row['unidade_medida']) ?></td>
                                    <td><?= e($fmtQty($row['estoque_minimo'])) ?>                 <?= e($row['unidade_medida']) ?></td>
                                    <td><?= e($fmtQty(max(0, (float) $row['quantidade_sugerida']))) ?>                 <?= e($row['unidade_medida']) ?>
                                    </td>
                                    <td><?= $row['prazo_reposicao_dias'] ? (int) $row['prazo_reposicao_dias'] . ' dias' : 'Não informado' ?>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div><?php endif; ?>

            <?php require VIEW_PATH . '/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>