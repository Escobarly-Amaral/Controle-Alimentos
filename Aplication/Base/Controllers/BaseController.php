<?php
declare(strict_types=1);

abstract class BaseController
{
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require VIEW_PATH . '/' . $view . '.php';
    }

    protected function renderAppPage(string $view, array $data = []): void
    {
        if (!isset($data['permissionIds']) && !empty($data['user']['id'])) {
            try {
                $data['permissionIds'] = array_column((new AccessModel())->permissionsFor((int) $data['user']['id']), 'id');
            } catch (Throwable $exception) {
                error_log('SCA navigation permissions unavailable: ' . $exception->getMessage());
                $data['permissionIds'] = [];
            }
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require VIEW_PATH . '/' . $view . '.php';
        $pageContent = ob_get_clean();
        require VIEW_PATH . '/layouts/app-shell.php';
    }

    protected function redirect(string $page = 'login'): never
    {
        header('Location: index.php?page=' . rawurlencode($page));
        exit;
    }

    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
            || !isset($_SESSION['csrf_token'], $_POST['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
            http_response_code(400);
            exit('Sessão expirada. Atualize a página e tente novamente.');
        }
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

