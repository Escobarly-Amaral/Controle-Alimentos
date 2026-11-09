<?php
declare(strict_types=1);

final class LoginController extends BaseController
{
    public function index(): void
    {
        if (!empty($_SESSION['sca_user']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('visao-geral');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->signIn();
            return;
        }

        $this->show();
    }

    public function logout(): never
    {
        $this->requirePost();
        $_SESSION = [];
        session_regenerate_id(true);
        $this->redirect('login');
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->show('A página solicitada não foi encontrada.');
    }

    private function signIn(): void
    {
        $this->requirePost();

        if (!empty($_SESSION['sca_user'])) {
            $this->redirect('visao-geral');
        }

        $blockedUntil = (int) ($_SESSION['login_blocked_until'] ?? 0);
        if ($blockedUntil > time()) {
            $this->show('Muitas tentativas. Aguarde um minuto e tente novamente.');
            return;
        }
        if ($blockedUntil !== 0) {
            unset($_SESSION['login_blocked_until'], $_SESSION['login_attempts']);
        }
        $attemptWindow = (int) ($_SESSION['login_attempt_window'] ?? 0);
        if ($attemptWindow === 0 || $attemptWindow < time() - 600) {
            $_SESSION['login_attempt_window'] = time();
            $_SESSION['login_attempts'] = 0;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->show('Informe um e-mail e uma senha válidos.');
            return;
        }

        try {
            $user = (new UserModel())->authenticate($email, $password);
        } catch (Throwable $exception) {
            error_log('SCA sign-in failed: ' . $exception->getMessage());
            $this->show('Não foi possível acessar o sistema agora. Tente novamente mais tarde.');
            return;
        }

        if ($user === null) {
            $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['login_attempts'] = $attempts;
            if ($attempts >= 5) {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_blocked_until'] = time() + 60;
                $_SESSION['login_attempt_window'] = time();
            }
            $this->show('E-mail ou senha incorretos. Confira os dados e tente novamente.');
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
        $_SESSION['sca_user'] = [
            'id' => (int) $user['id'],
            'unidade_id' => (int) $user['unidade_id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'unidade_nome' => $user['unidade_nome'],
        ];
        if (isset($_POST['remember_me'])) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        $this->redirect('visao-geral');
    }

    private function show(?string $error = null): void
    {
        $this->render('auth/login', [
            'csrfToken' => $this->csrfToken(),
            'error' => $error,
            'user' => $_SESSION['sca_user'] ?? null,
            'oldEmail' => trim((string) ($_POST['email'] ?? '')),
        ]);
    }
}
