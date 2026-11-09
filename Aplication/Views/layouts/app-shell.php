<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f9ff">
    <title><?= e($pageTitle ?? 'SCA') ?> · Controle de Alimentos</title>
    <link rel="icon" href="Arquivos/Imagens/logo.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Arquivos/CSS/tokens.css">
    <link rel="stylesheet" href="Arquivos/CSS/app-shell.css">
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?><link rel="stylesheet" href="Arquivos/CSS/<?= e($pageStyle) ?>.css"><?php endforeach; ?>
</head>
<body class="app-shell">
<?php $initialMatch = []; preg_match('/^./u', (string) ($user['nome'] ?? 'U'), $initialMatch); $userInitial = strtoupper($initialMatch[0] ?? 'U'); ?>
<a class="skip-link" href="#main-content">Pular para o conteúdo</a>
<div class="app-layout">
    <?php require VIEW_PATH . '/partials/app-sidebar.php'; ?>
    <button class="sidebar-backdrop" type="button" aria-label="Fechar menu" data-sidebar-close></button>
    <div class="app-main">
        <header class="app-topbar">
            <button class="sidebar-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="app-sidebar" data-sidebar-toggle><span class="material-symbols-outlined" aria-hidden="true">menu</span></button>
            <div class="mobile-brand"><img src="Arquivos/Imagens/logo.png" alt="" width="30" height="30"><span>Controle de Alimentos</span></div>
            <div class="topbar-unit"><span class="material-symbols-outlined" aria-hidden="true">domain</span><span><?= e($user['unidade_nome'] ?? 'Unidade') ?></span></div>
            <div class="font-scale-controls" role="group" aria-label="Tamanho do texto">
                <button type="button" class="font-scale-btn" data-font-scale-step="-1" aria-label="Diminuir fonte" title="Diminuir fonte">A−</button>
                <span class="font-scale-current" data-font-scale-current aria-live="polite">A</span>
                <button type="button" class="font-scale-btn" data-font-scale-step="1" aria-label="Aumentar fonte" title="Aumentar fonte">A+</button>
            </div>
            <div class="topbar-user"><span class="user-avatar" aria-hidden="true"><?= e($userInitial) ?></span><span><?= e($user['nome'] ?? 'Usuário') ?></span></div>
        </header>
        <main id="main-content" class="app-content">
            <?= $pageContent ?>
        </main>
        <footer class="app-footer"><span>Projeto acadêmico · ADS, IFCE – Campus Boa Viagem</span><nav aria-label="Documentos"><a href="index.php?page=termos">Termos de Uso</a><a href="index.php?page=privacidade">Privacidade</a></nav></footer>
    </div>
</div>
<script src="Arquivos/JS/app-shell.js" defer></script>
<?php foreach (($pageScripts ?? []) as $pageScript): ?><script src="Arquivos/JS/<?= e($pageScript) ?>.js" defer></script><?php endforeach; ?>
</body>
</html>
