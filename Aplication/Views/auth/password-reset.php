<?php
$config = require APP_PATH . '/Config/app.php';
$titleByStep = [
    'solicitar' => 'Recuperar senha',
    'verifique' => 'Verifique seu e-mail',
    'nova-senha' => 'Nova senha',
    'sucesso' => 'Senha alterada',
    'link-invalido' => 'Link inválido ou expirado',
];
$title = $titleByStep[$step] ?? $titleByStep['solicitar'];
$resendSeconds = max(0, (int) $resendAfter - time());
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f9ff">
    <meta name="referrer" content="no-referrer">
    <meta name="description" content="Recupere o acesso ao Sistema de Controle de Alimentos.">
    <title><?= e($title) ?> · SCA</title>
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

        <section class="form-panel" aria-labelledby="reset-title">
            <div class="form-content reset-content">
                <div class="form-main">
                <header class="form-header">
                    <div class="brand-mark"><img src="Arquivos/Imagens/logo.png" alt="" width="54" height="54"></div>
                    <?php if ($step === 'sucesso'): ?><span class="signed-in-icon material-symbols-outlined" aria-hidden="true">check_circle</span><?php endif; ?>
                    <h1 id="reset-title"><?= e($title) ?></h1>
                    <?php if ($step === 'solicitar'): ?>
                        <p>Informe o e-mail associado à sua conta. Enviaremos instruções para criar uma nova senha.</p>
                    <?php elseif ($step === 'verifique'): ?>
                    <?php elseif ($step === 'nova-senha'): ?>
                        <p>Escolha uma senha nova para acessar o sistema.</p>
                    <?php elseif ($step === 'sucesso'): ?>
                        <p>Sua senha foi alterada. Use a nova senha para entrar no sistema.</p>
                    <?php else: ?>
                        <p>Este link não está mais disponível. Solicite um novo para continuar.</p>
                    <?php endif; ?>
                </header>

                <?php if (!empty($error)): ?>
                    <div class="form-error" id="reset-error" role="alert">
                        <span class="material-symbols-outlined" aria-hidden="true">error</span>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'solicitar'): ?>
                    <form class="login-form" method="post" action="index.php?page=recuperar&amp;action=solicitar">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="field-group">
                            <label for="reset-email">E-mail ou usuário</label>
                            <div class="input-shell <?= !empty($error) ? 'has-error' : '' ?>">
                                <span class="material-symbols-outlined field-icon" aria-hidden="true">mail</span>
                                <input id="reset-email" name="identifier" type="text" value="<?= e($identifier) ?>" placeholder="nome@escola.gov.br ou usuário" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="254" required<?= !empty($error) ? ' aria-describedby="reset-error"' : '' ?>>
                            </div>
                        </div>
                        <button class="primary-button" type="submit">
                            <span class="material-symbols-outlined" aria-hidden="true">send</span>
                            <span>Enviar instruções</span>
                        </button>
                    </form>
                    <p class="reset-help-line">Sem e-mail cadastrado? Procure o responsável pela sua unidade.</p>

                <?php elseif ($step === 'verifique'): ?>
                    <div class="reset-message-icon" aria-hidden="true"><span class="material-symbols-outlined">mark_email_read</span></div>
                    <p class="reset-expiry-note">Se o e-mail estiver cadastrado, o link recebido será válido por 30 minutos e poderá ser usado uma única vez.</p>
                    <form class="resend-form" method="post" action="index.php?page=recuperar&amp;action=reenviar" data-resend-form>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <span>Não chegou?</span>
                        <button class="text-button" type="submit" data-resend-button>Reenviar</button>
                        <span class="resend-countdown" data-countdown data-seconds="<?= $resendSeconds ?>" aria-live="polite"></span>
                    </form>
                    <br>
                    <a class="text-button" href="index.php?page=recuperar&amp;action=testNext">Continuar para a próxima etapa</a>

                <?php elseif ($step === 'nova-senha'): ?>
                    <form class="login-form" method="post" action="index.php?page=recuperar&amp;action=salvar" data-password-reset-form>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="field-group">
                            <label for="new-password">Nova senha</label>
                            <div class="input-shell<?= !empty($error) ? ' has-error' : '' ?>">
                                <span class="material-symbols-outlined field-icon" aria-hidden="true">lock</span>
                                <input id="new-password" name="password" type="password" maxlength="1024" autocomplete="new-password" required aria-describedby="password-rules<?= !empty($error) ? ' reset-error' : '' ?>">
                                <button class="password-toggle" type="button" aria-label="Mostrar senha" aria-pressed="false" data-toggle-password>
                                    <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                        </div>
                        <ul class="password-rules" id="password-rules" aria-label="Requisitos da senha">
                            <li data-rule="length" class="<?= !empty($rules['length']) ? 'is-met' : '' ?>"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>Pelo menos 12 caracteres</span></li>
                            <li data-rule="case" class="<?= !empty($rules['case']) ? 'is-met' : '' ?>"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>Uma letra maiúscula e uma minúscula</span></li>
                            <li data-rule="number" class="<?= !empty($rules['number']) ? 'is-met' : '' ?>"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>Pelo menos um número</span></li>
                        </ul>
                        <button class="primary-button" type="submit">
                            <span class="material-symbols-outlined" aria-hidden="true">key</span>
                            <span>Salvar nova senha</span>
                        </button>
                    </form>

                <?php elseif ($step === 'sucesso'): ?>
                    <a class="primary-button button-link" href="index.php?page=login">
                        <span class="material-symbols-outlined" aria-hidden="true">login</span>
                        <span>Entrar</span>
                    </a>

                <?php else: ?>
                    <a class="primary-button button-link" href="index.php?page=recuperar">
                        <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                        <span>Solicitar novo link</span>
                    </a>
                <?php endif; ?>
                </div>

                <div class="help-row reset-footer">
                    <a href="index.php?page=login"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Voltar ao login</a>
                    <?php if ($step !== 'sucesso' && $step !== 'link-invalido'): ?>
                        <span class="help-separator" aria-hidden="true"></span>
                        <span>Versão <?= e($config['version']) ?></span>
                    <?php endif; ?>
                </div>
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
<script src="Arquivos/JS/password-reset.js" defer></script>
</body>
</html>
