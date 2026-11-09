<?php
declare(strict_types=1);

final class PasswordResetController extends BaseController
{
    private const TOKEN_TTL_MINUTES = 30;
    private const RESEND_WAIT_SECONDS = 60;

    public function index(): void
    {
        $step = (string) ($_GET['step'] ?? 'solicitar');
        switch ($step) {
            case 'verifique':
                if (empty($_SESSION['password_reset_flow'])) $this->redirect('recuperar');
                $this->renderStep('verifique', ['resendAfter' => (int) $_SESSION['password_reset_flow']['resend_after']]);
                return;
            case 'nova-senha':
                $token = (string) ($_GET['token'] ?? '');
                $this->showPasswordForm($token);
                return;
            case 'sucesso':
                if (empty($_SESSION['password_reset_success'])) $this->redirect('login');
                unset($_SESSION['password_reset_success']);
                $this->renderStep('sucesso');
                return;
            case 'link-invalido':
                $this->renderStep('link-invalido');
                return;
            case 'solicitar':
            default:
                $this->renderStep('solicitar', [
                    'identifier' => (string) ($_SESSION['password_reset_flow']['identifier'] ?? ''),
                    'error' => null,
                ]);
        }
    }

    public function testNext(): void
    {
        $flow = $_SESSION['password_reset_flow'] ?? null;
        if (empty($flow['identifier'])) {
            $this->redirect('recuperar');
        }
        try {
            $userId = (new PasswordResetModel())->findActiveUserId((string) $flow['identifier']);
            if ($userId === null) {
                $this->renderStep('verifique', [
                    'resendAfter' => (int) ($flow['resend_after'] ?? 0),
                    'error' => 'Nenhuma conta ativa corresponde ao e-mail ou nome informado. Volte e confira o cadastro do usuário.',
                ]);
                return;
            }
            $_SESSION['password_reset_test_user_id'] = $userId;
            header('Location: index.php?page=recuperar&step=nova-senha&token=TEST-RESET-TOKEN');
            exit;
        } catch (Throwable $exception) {
            error_log('SCA password reset test link creation failed: ' . $exception->getMessage());
            $this->renderStep('verifique', [
                'resendAfter' => (int) ($flow['resend_after'] ?? 0),
                'error' => 'Não foi possível preparar a redefinição agora. Tente novamente.',
            ]);
        }
    }

    public function solicitar(): void
    {
        $this->requirePost();
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '' || strlen($identifier) > 254) {
            $this->renderStep('solicitar', ['identifier' => $identifier, 'error' => 'Informe o e-mail ou usuário cadastrado.']);
            return;
        }

        $_SESSION['password_reset_flow'] = [
            'identifier' => $identifier,
            'resend_after' => time() + self::RESEND_WAIT_SECONDS,
        ];
        $this->redirectToStep('verifique');
    }

    public function reenviar(): void
    {
        $this->requirePost();
        $flow = $_SESSION['password_reset_flow'] ?? null;
        if (!$flow || empty($flow['identifier'])) $this->redirect('recuperar');

        $wait = max(0, (int) ($flow['resend_after'] ?? 0) - time());
        if ($wait > 0) {
            $this->renderStep('verifique', ['resendAfter' => (int) $flow['resend_after']]);
            return;
        }

        $_SESSION['password_reset_flow']['resend_after'] = time() + self::RESEND_WAIT_SECONDS;
        $this->redirectToStep('verifique');
    }

    public function salvar(): void
    {
        $this->requirePost();
        $token = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $rules = $this->passwordRules($password);

        if (!empty($_SESSION['password_reset_test_user_id']) && hash_equals('TEST-RESET-TOKEN', $token)) {
            if (in_array(false, $rules, true)) {
                $this->renderStep('nova-senha', [
                    'token' => $token,
                    'error' => 'A senha ainda não atende a todos os requisitos.',
                    'rules' => $rules,
                    'testMode' => true,
                ]);
                return;
            }
            try {
                $saved = (new PasswordResetModel())->resetPasswordForUserId((int) $_SESSION['password_reset_test_user_id'], $password);
                if (!$saved) throw new RuntimeException('A conta não está ativa ou não pôde ser atualizada.');
            } catch (Throwable $exception) {
                error_log('SCA password reset test save failed: ' . $exception->getMessage());
                $this->renderStep('nova-senha', [
                    'token' => $token,
                    'error' => 'Não foi possível salvar a senha agora. Tente novamente.',
                    'rules' => $rules,
                    'testMode' => true,
                ]);
                return;
            }
            unset($_SESSION['password_reset_test_user_id'], $_SESSION['password_reset_flow']);
            $_SESSION['password_reset_success'] = true;
            $this->redirectToStep('sucesso');
        }

        try {
            if (!(new PasswordResetModel())->isValid($token)) {
                $this->redirectToStep('link-invalido');
            }
            if (in_array(false, $rules, true)) {
                $this->renderStep('nova-senha', [
                    'token' => $token,
                    'error' => 'A senha ainda não atende a todos os requisitos.',
                    'rules' => $rules,
                ]);
                return;
            }
            if (!(new PasswordResetModel())->reset($token, $password)) {
                $this->redirectToStep('link-invalido');
            }
        } catch (Throwable $exception) {
            error_log('SCA password reset failed: ' . $exception->getMessage());
            $this->renderStep('nova-senha', [
                'token' => $token,
                'error' => 'Não foi possível salvar agora. Tente novamente.',
                'rules' => $rules,
            ]);
            return;
        }

        $_SESSION['password_reset_success'] = true;
        unset($_SESSION['password_reset_flow']);
        $this->redirectToStep('sucesso');
    }

    private function showPasswordForm(string $token): void
    {
        if (!empty($_SESSION['password_reset_test_user_id']) && hash_equals('TEST-RESET-TOKEN', $token)) {
            $this->renderStep('nova-senha', [
                'token' => $token,
                'error' => null,
                'rules' => $this->passwordRules(''),
                'testMode' => true,
            ]);
            return;
        }
        try {
            if (!(new PasswordResetModel())->isValid($token)) {
                $this->redirectToStep('link-invalido');
            }
        } catch (Throwable $exception) {
            error_log('SCA password reset token check failed: ' . $exception->getMessage());
            $this->renderStep('link-invalido', ['error' => 'Não foi possível validar este link agora.']);
            return;
        }

        $this->renderStep('nova-senha', [
            'token' => $token,
            'error' => null,
            'rules' => $this->passwordRules(''),
        ]);
    }

    private function passwordRules(string $password): array
    {
        $length = preg_match_all('/./us', $password);
        if ($length === false) $length = strlen($password);
        return [
            'length' => $length >= 12,
            'case' => preg_match('/[a-z]/', $password) === 1 && preg_match('/[A-Z]/', $password) === 1,
            'number' => preg_match('/[0-9]/', $password) === 1,
        ];
    }

    private function renderStep(string $step, array $data = []): void
    {
        $this->render('auth/password-reset', array_merge([
            'step' => $step,
            'csrfToken' => $this->csrfToken(),
            'error' => null,
            'resendAfter' => 0,
            'identifier' => '',
            'token' => '',
            'rules' => ['length' => false, 'case' => false, 'number' => false],
        ], $data));
    }

    private function redirectToStep(string $step): never
    {
        header('Location: index.php?page=recuperar&step=' . rawurlencode($step));
        exit;
    }
}
