<?php
declare(strict_types=1);

final class LegalController extends BaseController
{
    public function index(): void
    {
        if (($_GET['page'] ?? '') === 'privacidade') {
            $this->privacy();
            return;
        }
        $this->terms();
    }

    public function terms(): void
    {
        $this->showDocument([
            'slug' => 'termos',
            'title' => 'Termos de Uso',
            'summary' => 'O Controle de Alimentos ajuda a equipe da unidade a registrar e acompanhar o estoque. Use sua conta com responsabilidade e registre informações verdadeiras. Os indicadores do PNAE servem para acompanhamento e não substituem a apuração nem a prestação de contas oficial.',
            'sections' => [
                ['title' => 'O que é o sistema', 'paragraphs' => ['Ferramenta de apoio ao controle de estoque de alimentos em unidades escolares, com registros de entradas, saídas, lotes e validades, além de alertas de reposição e vencimento.']],
                ['title' => 'O que o sistema não é', 'items' => ['Não substitui a conferência física do estoque nem as decisões da nutricionista responsável.', 'Os indicadores do PNAE são de acompanhamento. Não substituem a apuração oficial, os sistemas do FNDE nem a prestação de contas do programa.']],
                ['title' => 'Quem pode usar', 'paragraphs' => ['Pessoas autorizadas pela unidade. Cada permissão define quais funções e informações estão disponíveis para cada conta.']],
                ['title' => 'Sua conta', 'items' => ['A conta é pessoal. Não compartilhe sua senha.', 'Você é responsável pelos registros feitos com sua conta.', 'Em aparelho compartilhado, saia ao terminar e não use a opção “Manter conectado”.', 'Se suspeitar de acesso indevido, avise o responsável pela unidade.']],
                ['title' => 'Uso adequado', 'items' => ['Registre informações verdadeiras e completas.', 'Não tente contornar permissões, acessar dados de outra unidade ou prejudicar o funcionamento do sistema.', 'Não anexe documentos ou dados pessoais que não sejam necessários ao controle do estoque.']],
                ['title' => 'Registros e auditoria', 'paragraphs' => ['Entradas, saídas, ajustes e documentos confirmados mantêm histórico. Quando houver erro, use os procedimentos disponíveis para estorno, ajuste ou cancelamento. As ações registradas identificam autor, data e hora.']],
                ['title' => 'Dados da unidade', 'paragraphs' => ['A unidade é responsável pela exatidão e pelo uso adequado das informações que registra, inclusive pela gestão dos acessos de sua equipe.']],
                ['title' => 'Disponibilidade', 'paragraphs' => ['O sistema pode ficar temporariamente indisponível. Nesse caso, mantenha um registro provisório conforme o procedimento da unidade e transfira as informações ao sistema quando o acesso voltar.']],
                ['title' => 'Suspensão de acesso', 'paragraphs' => ['O acesso pode ser revogado quando a pessoa deixar de estar autorizada pela unidade ou houver uso incompatível com estes termos.']],
                ['title' => 'Alterações', 'paragraphs' => ['Estes termos podem ser atualizados. A versão e a data exibidas no início indicam a revisão publicada. Mudanças relevantes devem ser comunicadas aos usuários pela instituição responsável.']],
                ['title' => 'Contato', 'paragraphs' => ['Para dúvidas sobre acesso ou suporte ao sistema, procure o responsável pelo SCA na sua unidade. O canal institucional de contato deve ser informado pela instituição antes do uso oficial.']],
            ],
        ]);
    }

    public function privacy(): void
    {
        $this->showDocument([
            'slug' => 'privacidade',
            'title' => 'Política de Privacidade',
            'summary' => 'O Controle de Alimentos trata dados necessários para autenticar usuários e manter registros de estoque rastreáveis. Esta política é um modelo para demonstração.',
            'sections' => [
                ['title' => 'Quem é responsável', 'paragraphs' => ['A instituição que define as finalidades e os meios do tratamento deve ser identificada como controladora. O papel da equipe que desenvolve ou hospeda o sistema depende do arranjo institucional e contratual. Preencha o nome da instituição, o encarregado e o canal de privacidade antes de publicar esta política.']],
                ['title' => 'Quais dados tratamos', 'table' => [
                    'headers' => ['Origem', 'Dados tratados'],
                    'rows' => [
                        ['Usuários', 'Nome, e-mail, permissões e hash da senha (a senha não é armazenada em texto).'],
                        ['Uso do sistema', 'Movimentações com autor, data e hora, ajustes, pendências e registros de acesso necessários à operação.'],
                        ['Fornecedores', 'Nome e dados de contato profissional, quando cadastrados pela unidade.'],
                        ['Documentos', 'Referências ou anexos de notas fiscais e termos de doação, que podem conter dados pessoais.'],
                        ['Sessão', 'Identificadores técnicos necessários para manter a sessão e, se selecionado, a opção “Manter conectado”.'],
                    ],
                ]],
                ['title' => 'Para que usamos', 'paragraphs' => ['Autenticar usuários, controlar o estoque, preservar o histórico de operações, apoiar alertas e indicadores, administrar permissões e proteger o funcionamento do sistema.']],
                ['title' => 'Base legal', 'paragraphs' => ['A instituição responsável deve identificar e documentar a hipótese legal aplicável a cada finalidade, considerando o contexto e suas atribuições. Esta página não presume consentimento nem define uma base legal única para todas as unidades. Para órgãos públicos, a LGPD também contém regras específicas para tratamento de dados no exercício de competências e atribuições públicas.'], 'links' => [['label' => 'Consultar a Lei nº 13.709/2018 (LGPD)', 'url' => 'https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm']]],
                ['title' => 'Com quem compartilhamos', 'paragraphs' => ['O sistema não se destina à venda de dados nem à publicidade. Conforme a finalidade e as regras da instituição, dados podem ser acessados pela própria unidade, por órgãos de controle ou conselhos quando necessário, e por provedores que hospedem ou mantenham o sistema, sob as condições definidas pela instituição.']],
                ['title' => 'Por quanto tempo guardamos', 'paragraphs' => ['Os prazos dependem das obrigações legais, das regras de arquivo e da tabela de temporalidade aplicável à instituição. A instituição deve definir e documentar esses prazos antes do uso oficial. O sistema preserva o autor nos registros históricos quando uma conta é desativada.']],
                ['title' => 'Segurança', 'paragraphs' => ['O sistema guarda senhas usando hash e prevê controle de acesso por conta e permissões. A instituição deve configurar a hospedagem, a conexão segura, os acessos administrativos, as cópias de segurança e os procedimentos de resposta a incidentes de acordo com sua política de segurança.']],
                ['title' => 'Seus direitos', 'paragraphs' => ['O titular pode exercer os direitos previstos na LGPD, como confirmação da existência de tratamento, acesso, correção e informação sobre compartilhamento. A eliminação pode ter limites quando houver obrigação de conservação ou outra hipótese legal. Encaminhe solicitações ao canal de privacidade informado pela instituição.'], 'links' => [['label' => 'Consultar os direitos dos titulares na ANPD', 'url' => '#']]],
                ['title' => 'Cookies e armazenamento local', 'paragraphs' => ['O SCA usa cookie de sessão para manter o acesso durante a navegação. Se a pessoa selecionar “Manter conectado”, a sessão pode persistir por mais tempo no navegador. Não há finalidade publicitária prevista neste sistema.']],
                ['title' => 'Alterações', 'paragraphs' => ['Esta política deve ser revista quando mudarem as finalidades, os dados tratados, os responsáveis ou os procedimentos. A versão e a data no início identificam a revisão publicada.']],
                ['title' => 'Contato', 'paragraphs' => ['Encarregado e canal para solicitações de titulares: preencher pela instituição controladora antes da publicação oficial.']],
            ],
        ]);
    }

    private function showDocument(array $document): void
    {
        $document['version'] = '1.0';
        $document['updatedAt'] = '08/11/2026';
        $this->render('legal/document', ['document' => $document]);
    }
}
