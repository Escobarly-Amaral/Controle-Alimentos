<?php
declare(strict_types=1);

final class AdminController extends BaseController
{
    private const PAGES = [
        'produtos' => ['title'=>'Produtos','permission'=>'cadastrar_produto'],
        'produto-form' => ['title'=>'Cadastro de produto','permission'=>'cadastrar_produto'],
        'fornecedores' => ['title'=>'Fornecedores','permission'=>'cadastrar_produto'],
        'fornecedor-form' => ['title'=>'Cadastro de fornecedor','permission'=>'cadastrar_produto'],
        'categorias' => ['title'=>'Categorias','permission'=>'gerir_usuarios'],
        'categoria-form' => ['title'=>'Categoria','permission'=>'gerir_usuarios'],
        'documentos' => ['title'=>'Documentos fiscais','permission'=>'consultar'],
        'documento' => ['title'=>'Detalhe do documento fiscal','permission'=>'consultar'],
        'usuarios' => ['title'=>'Usuários e permissões','permission'=>'gerir_usuarios'],
        'usuario-form' => ['title'=>'Usuário e permissões','permission'=>'gerir_usuarios'],
        'configuracoes' => ['title'=>'Configurações da unidade','permission'=>'gerir_usuarios'],
    ];
    public function index(): void { $this->show(); }
    public function restoreDemo(): void
    {
        $this->requirePost();$user=$_SESSION['sca_user']??null;if(!$user)$this->redirect('login');
        if(getenv('APP_ENV')!=='demo'){$this->redirect('configuracoes');}
        if(!$this->allowed($user,'gerir_usuarios'))return;
        try{DemoSeeder::restore(Database::getConnection());$this->go('configuracoes',['demo_restaurada'=>1]);}
        catch(Throwable $e){error_log('SCA demo restore failed: '.$e->getMessage());$this->show('Não foi possível restaurar os dados de demonstração. Confira o log do servidor.');}
    }
    public function save(): void
    {
        $this->requirePost(); $page=(string)($_GET['page']??''); $user=$_SESSION['sca_user']??null;
        if (!$user) $this->redirect('login');
        $requiredPermission = $page === 'documentos' ? 'registrar_entrada' : (self::PAGES[$page]['permission']??null);
        if (!$this->allowed($user, $requiredPermission)) return;
        try {
            $model=new CatalogModel(); $unit=(int)$user['unidade_id'];
            switch($page) {
                case 'produtos': case 'produto-form': $id=$model->saveProduct($unit,$_POST); $this->go('produto',['id'=>$id,'saved'=>1]);
                case 'fornecedores': case 'fornecedor-form': $model->saveSupplier($unit,$_POST); $this->go('fornecedores',['saved'=>1]);
                case 'categorias': case 'categoria-form': $model->saveCategory($_POST); $this->go('categorias',['saved'=>1]);
                case 'usuarios': case 'usuario-form': $payload=$_POST; $payload['nome']=$payload['person_label']??''; $payload['email']=$payload['account_address']??''; $payload['senha']=$payload['initial_secret']??''; $model->saveUser($unit,(int)$user['id'],$payload); $this->go('usuarios',['saved'=>1]);
                case 'configuracoes': $model->saveConfig($unit,(int)$user['id'],$_POST['config']??[]); $this->go('configuracoes',['saved'=>1]);
                case 'documentos': $model->cancelDocument($unit,(int)$user['id'],(int)($_POST['documento_id']??0),(string)($_POST['motivo']??'')); $this->go('documentos',['saved'=>1]);
            }
        } catch(Throwable $e) { error_log('SCA admin save failed ('.$page.'): '.$e->getMessage()); $this->show($e instanceof DomainException?$e->getMessage():'Não foi possível salvar. Verifique os dados e tente novamente.',$_POST); }
    }
    public function archive(): void
    {
        $this->requirePost(); $user=$_SESSION['sca_user']??null; if(!$user)$this->redirect('login');
        if(!$this->allowed($user,'cadastrar_produto'))return;
        try {(new CatalogModel())->archiveProduct((int)$user['unidade_id'],(int)($_POST['id']??0));$this->go('produtos',['saved'=>1]);}
        catch(Throwable $e){$this->show($e instanceof DomainException?$e->getMessage():'Não foi possível arquivar o produto.');}
    }
    public function deactivate(): void
    {
        $this->requirePost(); $user=$_SESSION['sca_user']??null; if(!$user)$this->redirect('login');
        if(!$this->allowed($user,'cadastrar_produto'))return;
        try {(new CatalogModel())->deactivateSupplier((int)$user['unidade_id'],(int)($_POST['id']??0));$this->go('fornecedores',['saved'=>1]);}
        catch(Throwable $e){$this->show($e instanceof DomainException?$e->getMessage():'Não foi possível desativar o fornecedor.');}
    }
    public function toggleUser(): void
    {
        $this->requirePost();$user=$_SESSION['sca_user']??null;if(!$user)$this->redirect('login');
        if(!$this->allowed($user,'gerir_usuarios'))return;
        try{(new CatalogModel())->setUserActive((int)$user['unidade_id'],(int)$user['id'],(int)($_POST['id']??0),!empty($_POST['active']));$this->go('usuarios',['saved'=>1]);}
        catch(Throwable $e){$this->show($e instanceof DomainException?$e->getMessage():'Não foi possível atualizar o acesso.');}
    }
    private function show(?string $message=null,array $formValues=[]): void
    {
        $page=(string)($_GET['page']??'produtos');$user=$_SESSION['sca_user']??null;if(!$user||empty($user['unidade_id']))$this->redirect('login');
        $meta=self::PAGES[$page]??self::PAGES['produtos'];if(!$this->allowed($user,$meta['permission']))return;
        $loadError=false;
        try{$workspace=(new CatalogModel())->load($page,(int)$user['unidade_id'],$_GET);$pagination=$workspace['pagination']??null;if($pagination&&$pagination['requested_page']!==$pagination['page']){$query=$_GET;$query['pagina']=$pagination['page'];header('Location: index.php?'.http_build_query($query));exit;}}catch(Throwable $e){error_log('SCA admin query failed: '.$e->getMessage());$workspace=[];$loadError=true;$message??='Não foi possível carregar esta tela. Tente novamente.';}
        http_response_code($message?400:200);
        $canCancelDocuments = false;
        try { $canCancelDocuments = (new AccessModel())->allows((int)$user['id'],'registrar_entrada'); } catch(Throwable $e) {}
        $this->renderAppPage('workspace/admin-page',['user'=>$user,'currentPage'=>$page,'pageTitle'=>$meta['title'],'pageStyles'=>['workspace'],'csrfToken'=>$this->csrfToken(),'message'=>$message,'workspace'=>$workspace,'query'=>$_GET,'canCancelDocuments'=>$canCancelDocuments,'formValues'=>$formValues,'loadError'=>$loadError]);
    }
    private function allowed(array $user,?string $permission): bool
    {
        if(!$permission||(new AccessModel())->allows((int)$user['id'],$permission))return true;
        http_response_code(403);$this->renderAppPage('workspace/forbidden',['user'=>$user,'currentPage'=>'','pageTitle'=>'Acesso não autorizado','pageStyles'=>['workspace'],'csrfToken'=>$this->csrfToken()]);return false;
    }
    private function go(string $page,array $query=[]): never {header('Location: index.php?'.http_build_query(['page'=>$page]+$query));exit;}
}
