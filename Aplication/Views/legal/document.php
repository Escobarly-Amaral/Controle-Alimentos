<?php $title = $document['title']; ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f9ff">
    <meta name="description" content="<?= e($document['title']) ?> do Sistema de Controle de Alimentos.">
    <title><?= e($title) ?> · Controle de Alimentos</title>
    <link rel="icon" href="Arquivos/Imagens/logo.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Arquivos/CSS/tokens.css">
    <link rel="stylesheet" href="Arquivos/CSS/legal-docs.css">
</head>
<body class="legal-page">
<header class="legal-header">
    <a class="legal-brand" href="index.php?page=login">
        <img src="Arquivos/Imagens/logo.png" alt="" width="32" height="32">
        <span>Controle de Alimentos</span>
    </a>
    <a class="legal-back" href="index.php?page=login"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Voltar ao login</a>
</header>
<main class="legal-main">
    <div class="legal-container">
        <div class="legal-demo-alert" role="note">
            <span class="material-symbols-outlined" aria-hidden="true">info</span>
            <p><strong>Ambiente de demonstração:</strong> textos modelo.</p>
        </div>

        <div class="legal-title-row">
            <h1><?= e($document['title']) ?></h1>
            <span class="legal-version"><span class="material-symbols-outlined" aria-hidden="true">history</span>Versão <?= e($document['version']) ?> · Atualizado em <?= e($document['updatedAt']) ?></span>
        </div>

        <section class="legal-summary" aria-labelledby="summary-title">
            <div class="legal-summary-icon"><span class="material-symbols-outlined" aria-hidden="true">lightbulb</span></div>
            <div>
                <h2 id="summary-title">Resumo</h2>
                <p><?= e($document['summary']) ?></p>
            </div>
        </section>

        <div class="legal-grid">
            <aside class="legal-aside">
                <nav class="legal-index" aria-label="Índice do documento">
                    <h2><span class="material-symbols-outlined" aria-hidden="true">list_alt</span>Índice do Documento</h2>
                    <ol>
                        <?php foreach ($document['sections'] as $index => $section): ?>
                            <?php $sectionId = sprintf('secao-%02d', $index + 1); ?>
                            <li><a href="#<?= e($sectionId) ?>"><span><?= sprintf('%02d', $index + 1) ?></span><?= e($section['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </aside>

            <div class="legal-sections">
                <?php foreach ($document['sections'] as $index => $section): ?>
                    <?php $sectionId = sprintf('secao-%02d', $index + 1); ?>
                    <section class="legal-card" id="<?= e($sectionId) ?>" aria-labelledby="<?= e($sectionId) ?>-title">
                        <div class="legal-card-heading">
                            <span><?= sprintf('%02d', $index + 1) ?></span>
                            <h2 id="<?= e($sectionId) ?>-title"><?= e($section['title']) ?></h2>
                        </div>
                        <?php if (!empty($section['paragraphs'])): ?>
                            <div class="legal-copy">
                                <?php foreach ($section['paragraphs'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($section['items'])): ?>
                            <ul class="legal-list">
                                <?php foreach ($section['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($section['table'])): ?>
                            <div class="legal-table-wrap"><table>
                                <thead><tr><?php foreach ($section['table']['headers'] as $header): ?><th scope="col"><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                                <tbody><?php foreach ($section['table']['rows'] as $row): ?><tr><?php foreach ($row as $cellIndex => $cell): ?><?php if ($cellIndex === 0): ?><th scope="row"><?= e($cell) ?></th><?php else: ?><td><?= e($cell) ?></td><?php endif; ?><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                            </table></div>
                        <?php endif; ?>
                        <?php if (!empty($section['links'])): ?>
                            <div class="legal-links"><?php foreach ($section['links'] as $link): ?><a href="<?= e($link['url']) ?>" rel="noopener noreferrer"><?= e($link['label']) ?><span class="material-symbols-outlined" aria-hidden="true">open_in_new</span></a><?php endforeach; ?></div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<footer class="legal-footer">
    <span>Projeto acadêmico · ADS, IFCE – Campus Boa Viagem</span>
    <nav aria-label="Documentos legais"><a href="index.php?page=termos">Termos de Uso</a><a href="index.php?page=privacidade">Privacidade</a></nav>
</footer>
</body>
</html>
