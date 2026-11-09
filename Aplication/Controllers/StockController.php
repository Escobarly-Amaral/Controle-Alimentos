<?php
declare(strict_types=1);

final class StockController extends BaseController
{
    private const PAGES = [
        'saida-rapida' => ['title' => 'Saída rápida', 'permission' => 'registrar_saida'],
        'entrada-documento' => ['title' => 'Entrada por documento', 'permission' => 'registrar_entrada'],
        'movimentacoes' => ['title' => 'Movimentações', 'permission' => 'consultar'],
        'operacao' => ['title' => 'Detalhe da operação', 'permission' => 'consultar'],
        'estoque' => ['title' => 'Estoque', 'permission' => 'consultar'],
        'produto' => ['title' => 'Detalhe do produto', 'permission' => 'consultar'],
        'ajuste-estoque' => ['title' => 'Ajustar estoque', 'permission' => 'ajustar_estoque'],
        'alertas' => ['title' => 'Alertas e validade', 'permission' => null],
        'pendencias' => ['title' => 'Pendências de revisão', 'permission' => 'revisar_pendencias'],
        'lista-compras' => ['title' => 'Lista de compras', 'permission' => 'registrar_entrada'],
    ];

    public function index(): void
    {
        $this->showPage();
    }

    public function searchProducts(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = $_SESSION['sca_user'] ?? null;
        if (!$user || empty($user['unidade_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $access = new AccessModel();
            $canExit = $access->allows((int) $user['id'], 'registrar_saida');
            $canEntry = $access->allows((int) $user['id'], 'registrar_entrada');
            if (!$canExit && !$canEntry) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso não autorizado'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $term = trim((string) ($_GET['q'] ?? ''));
            $products = (new StockModel())->searchProductsWithBalance((int) $user['unidade_id'], $term);
            $formatted = array_map(static function (array $p): array {
                return [
                    'id' => (int) $p['id'],
                    'nome' => (string) $p['nome'],
                    'unidade_medida' => (string) $p['unidade_medida'],
                    'aceita_fracao' => (int) $p['aceita_fracao'],
                    'saldo' => round((float) $p['saldo'], 3),
                    'validade_padrao_dias' => $p['validade_padrao_dias'] !== null ? (int) $p['validade_padrao_dias'] : null,
                ];
            }, $products);

            echo json_encode(['products' => $formatted], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('SCA searchProducts error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao buscar produtos'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function save(): void
    {
        $this->requirePost();
        $page = $this->pageName();
        $context = $this->context($page);
        if (!$this->authorized($context['user'], $context['meta']['permission'])) return;
        try {
            $model = new StockModel();
            $operationId = match ($page) {
                'saida-rapida' => $model->createExit(
                    (int) $context['user']['unidade_id'], (int) $context['user']['id'],
                    (int) ($_POST['produto_id'] ?? 0), (float) ($_POST['quantidade'] ?? 0),
                    (string) ($_POST['motivo'] ?? ''), trim((string) ($_POST['observacao'] ?? '')) ?: null,
                    (string) ($_POST['motivo_excedente'] ?? '')
                ),
                'entrada-documento' => $model->createEntry((int) $context['user']['unidade_id'], (int) $context['user']['id'], $_POST),
                'ajuste-estoque' => $model->adjust(
                    (int) $context['user']['unidade_id'], (int) $context['user']['id'], (int) ($_POST['lote_id'] ?? 0),
                    (($_POST['sentido'] ?? '') === 'reduzir' ? -1 : 1) * abs((float) ($_POST['quantidade'] ?? 0)),
                    (string) ($_POST['motivo'] ?? ''), trim((string) ($_POST['observacao'] ?? '')) ?: null
                ),
                default => throw new DomainException('Esta página não aceita este tipo de envio.'),
            };
            // Return to the screen the user is authorized to access. The operation detail
            // requires separate read permission, which a registrar-only account may not have.
            $this->redirectTo($page, ['saved' => 1]);
        } catch (Throwable $exception) {
            $this->showPage($exception instanceof DomainException ? $exception->getMessage() : 'Não foi possível registrar a operação. Confira os dados e tente novamente.');
        }
    }

    public function reverse(): void
    {
        $this->requirePost();
        $context = $this->context('operacao');
        if (!$this->authorized($context['user'], 'ajustar_estoque')) return;
        try {
            (new StockModel())->reverse(
                (int) $context['user']['unidade_id'], (int) $context['user']['id'],
                (int) ($_POST['operacao_id'] ?? 0), trim((string) ($_POST['observacao'] ?? '')) ?: null
            );
            $this->redirectTo('operacao', ['id' => (int) $_POST['operacao_id'], 'reversed' => 1]);
        } catch (Throwable $exception) {
            $this->showPage($exception instanceof DomainException ? $exception->getMessage() : 'Não foi possível estornar esta operação.');
        }
    }

    public function review(): void
    {
        $this->requirePost();
        $context = $this->context('pendencias');
        if (!$this->authorized($context['user'], 'revisar_pendencias')) return;
        try {
            (new StockModel())->review(
                (int) $context['user']['unidade_id'], (int) $context['user']['id'],
                (int) ($_POST['pendencia_id'] ?? 0), (string) ($_POST['observacao'] ?? '')
            );
            $this->redirectTo('pendencias', ['revisada' => 1]);
        } catch (Throwable $exception) {
            $this->showPage($exception instanceof DomainException ? $exception->getMessage() : 'Não foi possível atualizar a pendência.');
        }
    }

    private function showPage(?string $message = null): void
    {
        $page = $this->pageName();
        $context = $this->context($page);
        if (!$this->authorized($context['user'], $context['meta']['permission'])) return;
        $canAdjust = false;
        try {
            $canAdjust = (new AccessModel())->allows((int) $context['user']['id'], 'ajustar_estoque');
        } catch (Throwable $exception) {
            error_log('SCA estorno permission unavailable: ' . $exception->getMessage());
        }
        $data = [
            'user' => $context['user'], 'currentPage' => $page, 'pageTitle' => $context['meta']['title'],
            'pageStyles' => ['workspace'], 'pageScripts' => ['workspace'], 'csrfToken' => $this->csrfToken(), 'message' => $message,
            'filters' => $_GET,
            'canAdjust' => $canAdjust,
            'loadError' => false,
        ];
        try {
            $data['workspace'] = (new StockModel())->loadPage($page, (int) $context['user']['unidade_id'], $_GET);
            $pagination=$data['workspace']['pagination']??null;
            if($pagination && $pagination['requested_page']!==$pagination['page']){$query=$_GET;$pageKey=$page==='alertas'?(($data['workspace']['activeTab']??'validade')==='estoque'?'pagina_estoque':'pagina_validade'):'pagina';$query[$pageKey]=$pagination['page'];header('Location: index.php?'.http_build_query($query));exit;}
        } catch (Throwable $exception) {
            error_log('SCA stock page query failed (' . $page . '): ' . $exception->getMessage());
            $data['workspace'] = [];
            $data['loadError'] = true;
            $data['message'] ??= $page === 'movimentacoes' ? 'Não foi possível carregar as movimentações. Tente novamente.' : 'Não foi possível carregar esta tela. Tente novamente.';
        }
        http_response_code($data['loadError'] ? 500 : (!empty($data['message']) ? 400 : 200));
        $this->renderAppPage('workspace/stock-page', $data);
    }

    private function context(string $page): array
    {
        $user = $_SESSION['sca_user'] ?? null;
        if (!$user || empty($user['unidade_id'])) $this->redirect('login');
        $meta = self::PAGES[$page] ?? null;
        if ($meta === null) $this->redirect('visao-geral');
        return ['user' => $user, 'meta' => $meta];
    }

    private function authorized(array $user, ?string $permission): bool
    {
        if ($permission === null) return true;
        try {
            if ((new AccessModel())->allows((int) $user['id'], $permission)) return true;
        } catch (Throwable $exception) {
            error_log('SCA access check failed: ' . $exception->getMessage());
        }
        http_response_code(403);
        $this->renderAppPage('workspace/forbidden', [
            'user' => $user, 'currentPage' => '', 'pageTitle' => 'Acesso não autorizado',
            'pageStyles' => ['workspace'], 'csrfToken' => $this->csrfToken(),
        ]);
        return false;
    }

    private function pageName(): string
    {
        return (string) ($_GET['page'] ?? 'estoque');
    }

    private function redirectTo(string $page, array $query = []): never
    {
        $query = ['page' => $page] + $query;
        header('Location: index.php?' . http_build_query($query));
        exit;
    }
}
