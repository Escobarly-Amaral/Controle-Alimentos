<?php
$config = require APP_PATH . '/Config/app.php';
$emailValue = $user['email'] ?? ($oldEmail ?? '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f9ff">
    <meta name="referrer" content="no-referrer">
    <meta name="description" content="Acesse o Sistema de Controle de Alimentos para acompanhar o estoque da alimentação escolar.">
    <title><?= e($config['name']) ?> - Entrar</title>
    <link rel="icon" href="Arquivos/Imagens/logo.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Arquivos/CSS/tokens.css">
    <link rel="stylesheet" href="Arquivos/CSS/intro-panel.css">
    <link rel="stylesheet" href="Arquivos/CSS/login.css">
</head>
<body class="login-page">
<main class="login-main">
    <div class="login-frame">
        <?php require VIEW_PATH . '/partials/intro-panel.php'; ?>

        <section class="form-panel" aria-labelledby="login-title">
            <div class="form-content">
                <div class="form-main">
                <?php if ($user): ?>
                    <header class="form-header">
                        <div class="brand-mark"><img src="Arquivos/Imagens/logo.png" alt="" width="54" height="54"></div>
                        <span class="signed-in-icon material-symbols-outlined" aria-hidden="true">check_circle</span>
                        <h1 id="login-title">Acesso confirmado</h1>
                        <p>Olá, <?= e($user['nome']) ?>. Acesso iniciado para <strong><?= e($user['unidade_nome']) ?></strong>.</p>
                    </header>
                    <form class="logout-form" method="post" action="index.php?page=login&amp;action=logout">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button class="secondary-button" type="submit">Sair da conta</button>
                    </form>
                <?php else: ?>
                    <header class="form-header">
                        <div class="brand-mark"><img src="Arquivos/Imagens/logo.png" alt="" width="54" height="54"></div>
                        <h1 id="login-title">Controle de Alimentos</h1>
                        <p>Registre entradas e saídas, acompanhe validades e saiba o que repor antes que falte, no ritmo da cozinha escolar.</p>
                    </header>
                    <?php if (!empty($error)): ?>
                        <div class="form-error" id="login-error" role="alert">
                            <span class="material-symbols-outlined" aria-hidden="true">error</span>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="login-form" id="login-form" method="post" action="index.php?page=login">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="field-group">
                            <label for="email">E-mail</label>
                            <div class="input-shell <?= !empty($error) ? 'has-error' : '' ?>">
                                <span class="material-symbols-outlined field-icon" aria-hidden="true">badge</span>
                                <input id="email" name="email" type="email" value="<?= e($emailValue) ?>" placeholder="Ex: nutricionista@escola.gov.br" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="254" required<?= !empty($error) ? ' aria-describedby="login-error"' : '' ?>>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="password">Senha</label>
                            <div class="input-shell <?= !empty($error) ? 'has-error' : '' ?>">
                                <span class="material-symbols-outlined field-icon" aria-hidden="true">lock</span>
                                <input id="password" name="password" type="password" maxlength="1024" placeholder="Digite sua senha de acesso" autocomplete="current-password" required<?= !empty($error) ? ' aria-describedby="login-error"' : '' ?>>
                                <button class="password-toggle" id="toggle-password" type="button" aria-label="Mostrar senha" aria-pressed="false">
                                    <span class="material-symbols-outlined" id="eye-icon" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="remember-option" for="remember-me">
                                <input id="remember-me" name="remember_me" type="checkbox" value="1">
                                <span class="checkbox-mark"><span class="material-symbols-outlined" aria-hidden="true">check</span></span>
                                <span>Manter conectado</span>
                            </label>
                            <a href="index.php?page=recuperar">Esqueci minha senha</a>
                        </div>

                        <button class="primary-button" id="submit-button" type="submit">
                            <span class="material-symbols-outlined" aria-hidden="true">login</span>
                            <span>Entrar no Sistema</span>
                        </button>
                    </form>

                <?php endif; ?>
                </div>

                <?php if ($user): ?>
                    <div class="help-row"><span>Projeto acadêmico · ADS, IFCE – Campus Boa Viagem</span><span class="help-separator" aria-hidden="true"></span><span>Versão <?= e($config['version']) ?></span></div>
                <?php else: ?>
                    <div class="help-row" id="ajuda">
                        <a href="#ajuda"><span class="material-symbols-outlined" aria-hidden="true">contact_support</span>Ajuda e Suporte</a>
                        <span class="help-separator" aria-hidden="true"></span>
                        <span>Versão <?= e($config['version']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<footer class="page-footer">
    <span>Projeto acadêmico · ADS, IFCE – Campus Boa Viagem</span>
    <span class="footer-separator" aria-hidden="true"></span>
    <a href="index.php?page=termos">Termos de Uso</a>
    <span class="footer-separator" aria-hidden="true"></span>
    <a href="index.php?page=privacidade">Privacidade</a>
</footer>
<script src="Arquivos/JS/login.js" defer></script>
</body>
</html>
