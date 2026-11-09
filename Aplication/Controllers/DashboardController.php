<?php
declare(strict_types=1);

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $_SESSION['sca_user'] ?? null;
        if (!$user || empty($user['unidade_id'])) $this->redirect('login');

        $data = [
            'user' => $user,
            'pageTitle' => 'Visão geral',
            'pageStyles' => ['overview', 'workspace'],
            'currentPage' => 'visao-geral',
            'csrfToken' => $this->csrfToken(),
            'dashboard' => [
                'alerts' => ['expired' => [], 'expiring' => [], 'low_stock' => []],
                'activities' => [], 'pnae' => null, 'errors' => ['connection'],
                'exercise' => (int) date('Y'),
            ],
        ];
        try {
            $data['dashboard'] = (new DashboardModel())->load((int) $user['unidade_id']);
        } catch (Throwable $exception) {
            error_log('SCA dashboard load failed: ' . $exception->getMessage());
        }

        $this->renderAppPage('dashboard/overview', $data);
    }
}
