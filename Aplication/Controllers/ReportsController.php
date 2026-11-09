<?php
declare(strict_types=1);
final class ReportsController extends BaseController
{
    public function index(): void
    {
        $user=$_SESSION['sca_user']??null;if(!$user||empty($user['unidade_id']))$this->redirect('login');
        if(!(new AccessModel())->allows((int)$user['id'],'ver_relatorios')){http_response_code(403);$this->renderAppPage('workspace/forbidden',['user'=>$user,'currentPage'=>'','pageTitle'=>'Acesso não autorizado','pageStyles'=>['workspace'],'csrfToken'=>$this->csrfToken()]);return;}
        $year=max(2000,min(2100,(int)($_GET['ano']??date('Y'))));$message=null;
        try{$report=(new ReportsModel())->load((int)$user['unidade_id'],$year);}catch(Throwable $e){error_log('SCA reports query failed: '.$e->getMessage());$report=[];$message='Não foi possível carregar os relatórios. Tente novamente.';}
        $this->renderAppPage('workspace/reports-page',['user'=>$user,'currentPage'=>'relatorios','pageTitle'=>'Relatórios e PNAE','pageStyles'=>['workspace'],'csrfToken'=>$this->csrfToken(),'report'=>$report,'message'=>$message]);
    }
}
