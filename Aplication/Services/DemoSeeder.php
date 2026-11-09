<?php
declare(strict_types=1);

final class DemoSeeder
{
    public static function restore(PDO $db): array
    {
        if (getenv('APP_ENV') !== 'demo') throw new RuntimeException('A restauração de demonstração só pode ser executada em ambiente demo.');
        $db->beginTransaction();
        try {
            $unitName = 'IFCE – Boa Viagem (Demonstração)';
            $unitQuery=$db->prepare('SELECT id FROM unidade WHERE nome=:name');$unitQuery->execute(['name'=>$unitName]);$unitId=(int)$unitQuery->fetchColumn();
            if(!$unitId){$insert=$db->prepare('INSERT INTO unidade (nome) VALUES (:name)');$insert->execute(['name'=>$unitName]);$unitId=(int)$db->lastInsertId();}
            $existingUsers=$db->prepare('SELECT id FROM usuario WHERE unidade_id=:unit');$existingUsers->execute(['unit'=>$unitId]);$userIds=$existingUsers->fetchAll(PDO::FETCH_COLUMN);
            if($userIds){$marks=implode(',',array_fill(0,count($userIds),'?'));
                $db->prepare("DELETE FROM usuario_permissao_historico WHERE usuario_id IN ($marks)")->execute($userIds);
                $db->prepare("DELETE FROM usuario_permissao WHERE usuario_id IN ($marks)")->execute($userIds);
            }
            foreach ([
                'DELETE pr FROM pendencia_revisao pr JOIN operacao o ON o.id=pr.operacao_id WHERE o.unidade_id=?',
                'DELETE m FROM movimentacao m JOIN operacao o ON o.id=m.operacao_id WHERE o.unidade_id=? AND m.tipo=\'ESTORNO\'',
                'DELETE m FROM movimentacao m JOIN operacao o ON o.id=m.operacao_id WHERE o.unidade_id=?',
                'DELETE FROM operacao WHERE unidade_id=? AND tipo=\'ESTORNO\'',
                'DELETE FROM operacao WHERE unidade_id=?',
                'DELETE l FROM lote l JOIN produto p ON p.id=l.produto_id WHERE p.unidade_id=?',
                'DELETE FROM documento_fiscal WHERE unidade_id=?',
                'DELETE FROM produto WHERE unidade_id=?',
                'DELETE FROM fornecedor WHERE unidade_id=?',
                'DELETE FROM configuracao_unidade WHERE unidade_id=?',
                'DELETE FROM usuario WHERE unidade_id=?',
            ] as $sql) $db->prepare($sql)->execute([$unitId]);

            $categories=[];
            foreach ([
                'Cereais e grãos'=>[0,null], 'Leguminosas'=>[0,null], 'Hortaliças'=>[8,10], 'Frutas'=>[8,7],
                'Carnes e ovos'=>[3,7], 'Leite e derivados'=>[5,7], 'Bebidas'=>[3,5], 'Panificados'=>[5,30], 'Óleos e condimentos'=>[0,null],
            ] as $name=>$limits) {
                $q=$db->prepare('SELECT id FROM categoria WHERE nome=:name');$q->execute(['name'=>$name]);$id=(int)$q->fetchColumn();
                if(!$id){$q=$db->prepare('INSERT INTO categoria (nome,janela_alerta_validade_dias,validade_padrao_dias,ativo) VALUES (:name,:window,:days,1)');$q->execute(['name'=>$name,'window'=>$limits[0],'days'=>$limits[1]]);$id=(int)$db->lastInsertId();}
                else $db->prepare('UPDATE categoria SET ativo=1 WHERE id=:id')->execute(['id'=>$id]);
                $categories[$name]=$id;
            }
            $supplierInsert=$db->prepare('INSERT INTO fornecedor (unidade_id,nome,cpf_cnpj,telefone,email,prazo_reposicao_dias,agricultura_familiar,ativo) VALUES (:unit,:name,:doc,:phone,:email,:lead,:family,1)');
            $suppliers=[];
            foreach ([['Cooperativa Boa Colheita','11.111.111/0001-11',1],['Mercantil Escolar Fictício','22.222.222/0001-22',0],['Distribuidora Vale Verde','33.333.333/0001-33',0]] as $i=>$row) {
                $supplierInsert->execute(['unit'=>$unitId,'name'=>$row[0],'doc'=>$row[1],'phone'=>'(88) 3000-000'.($i+1),'email'=>'fornecedor'.($i+1).'@example.com','lead'=>[5,7,10][$i],'family'=>$row[2]]);$suppliers[]=(int)$db->lastInsertId();
            }
            $products=[];
            $definitions=[
                ['Arroz parboilizado','Cereais e grãos','kg',1,40,'in_natura_minimamente_processado',0],
                ['Feijão carioca','Leguminosas','kg',1,20,'in_natura_minimamente_processado',0],
                ['Óleo de soja','Óleos e condimentos','L',1,10,'ingrediente_culinario',0],
                ['Carne moída','Carnes e ovos','kg',1,15,'in_natura_minimamente_processado',0],
                ['Leite integral','Leite e derivados','L',1,20,'in_natura_minimamente_processado',0],
                ['Cenoura','Hortaliças','kg',1,12,'in_natura_minimamente_processado',0],
                ['Bebida láctea','Bebidas','L',1,8,'ultraprocessado',0],
                ['Biscoito recheado','Panificados','pct',0,5,'ultraprocessado',1],
                ['Achocolatado em pó','Bebidas','kg',1,8,'ultraprocessado',0],
                ['Banana','Frutas','kg',1,20,'in_natura_minimamente_processado',0],
                ['Batata inglesa','Hortaliças','kg',1,15,'in_natura_minimamente_processado',0],
                ['Mandioca','Hortaliças','kg',1,12,'in_natura_minimamente_processado',0],
                ['Frango','Carnes e ovos','kg',1,15,'in_natura_minimamente_processado',0],
                ['Tomate','Hortaliças','kg',1,12,'in_natura_minimamente_processado',0],
                ['Cebola','Hortaliças','kg',1,8,'in_natura_minimamente_processado',0],
            ];
            $prod=$db->prepare('INSERT INTO produto (unidade_id,categoria_id,nome,unidade_medida,aceita_fracao,estoque_minimo,grau_processamento,vedado_pnae) VALUES (:unit,:category,:name,:measure,:fraction,:minimum,:grade,:blocked)');
            foreach($definitions as $d){$prod->execute(['unit'=>$unitId,'category'=>$categories[$d[1]],'name'=>$d[0],'measure'=>$d[2],'fraction'=>$d[3],'minimum'=>$d[4],'grade'=>$d[5],'blocked'=>$d[6]]);$products[$d[0]]=(int)$db->lastInsertId();}

            $userSpecs=[
                ['Administração Demonstração','admin@example.com',array_column($db->query('SELECT id FROM permissao')->fetchAll(),'id')],
                ['Equipe da Cozinha','cozinha@example.com',['registrar_saida']],
                ['Equipe de Recebimento','recebimento@example.com',['registrar_entrada']],
            ];
            $userInsert=$db->prepare('INSERT INTO usuario (unidade_id,nome,email,senha_hash,ativo) VALUES (:unit,:name,:email,:hash,1)');
            $grant=$db->prepare('INSERT INTO usuario_permissao (usuario_id,permissao_id,concedido_por) VALUES (:user,:permission,:actor)');$history=$db->prepare("INSERT INTO usuario_permissao_historico (usuario_id,permissao_id,acao,realizado_por) VALUES (:user,:permission,'concedida',:actor)");
            $demoUsers=[];
            foreach($userSpecs as [$name,$email,$permissions]){$userInsert->execute(['unit'=>$unitId,'name'=>$name,'email'=>$email,'hash'=>password_hash('DemoSCA#2026!',PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();$demoUsers[]=$id;foreach($permissions as $permission){$grant->execute(['user'=>$id,'permission'=>$permission,'actor'=>$id]);$history->execute(['user'=>$id,'permission'=>$permission,'actor'=>$id]);}}
            $adminId=$demoUsers[0];

            $docInsert=$db->prepare('INSERT INTO documento_fiscal (unidade_id,fornecedor_id,tipo,numero,serie,emitido_em,valor_total,doador_nome,destinatario_conferido,identificada_pnae,registrado_por) VALUES (:unit,:supplier,:type,:number,:series,:issued,:total,:donor,1,:pnae,:user)');
            $operationInsert=$db->prepare('INSERT INTO operacao (unidade_id,tipo,motivo,documento_fiscal_id,usuario_id,criado_em) VALUES (:unit,\'ENTRADA\',:reason,:doc,:user,:created)');
            $lotInsert=$db->prepare('INSERT INTO lote (produto_id,documento_fiscal_id,fornecedor_id,origem_recurso,codigo_lote,validade,validade_estimada,valor_item,recebido_em) VALUES (:product,:doc,:supplier,:origin,:code,:expiry,:estimated,:value,:received)');
            $moveInsert=$db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,criado_em) VALUES (:op,:lot,\'ENTRADA\',:reason,:qty,:user,:created)');
            $today=new DateTimeImmutable('today');$received=$today->format('Y-01-02');
            $createDocument=function(int $supplier,string $origin,string $number,int $total,array $items)use($db,$unitId,$adminId,$docInsert,$operationInsert,$lotInsert,$moveInsert,$received,$today,$products):array{
                $date=$received;$type=$origin==='doacao'?'termo_doacao':'nota_fiscal_eletronica';
                $docInsert->execute(['unit'=>$unitId,'supplier'=>$supplier,'type'=>$type,'number'=>$number,'series'=>'1','issued'=>$date,'total'=>$total,'donor'=>null,'pnae'=>$origin==='pnae_federal'?1:0,'user'=>$adminId]);$doc=(int)$db->lastInsertId();
                $operationInsert->execute(['unit'=>$unitId,'reason'=>$origin==='doacao'?'doacao':'compra','doc'=>$doc,'user'=>$adminId,'created'=>$date.' 08:00:00']);$op=(int)$db->lastInsertId();$lots=[];
                foreach($items as $i=>$item){[$name,$quantity,$value,$offset,$code]=$item;$expiry=$today->modify(($offset>=0?'+':'').$offset.' days')->format('Y-m-d');$lotInsert->execute(['product'=>$products[$name],'doc'=>$doc,'supplier'=>$supplier,'origin'=>$origin,'code'=>$code?:'DEMO-'.$number.'-'.$i,'expiry'=>$expiry,'estimated'=>0,'value'=>$origin==='doacao'?null:(string)$value,'received'=>$date]);$lot=(int)$db->lastInsertId();$moveInsert->execute(['op'=>$op,'lot'=>$lot,'reason'=>$origin==='doacao'?'doacao':'compra','qty'=>(string)$quantity,'user'=>$adminId,'created'=>$date.' 08:01:00']);$lots[$name][]=['id'=>$lot,'quantity'=>(string)$quantity];}
                return ['doc'=>$doc,'operation'=>$op,'lots'=>$lots];
            };
            $first=$createDocument($suppliers[0],'pnae_federal','DEMO-001',3800,[
                ['Arroz parboilizado',20,120,10,'AR-A'],['Arroz parboilizado',50,300,120,'AR-B'],['Feijão carioca',6,80,45,'FE-1'],['Cenoura',22,180,8,'CE-1'],['Leite integral',36,220,3,'LE-1'],['Carne moída',20,400,45,'CM-1'],['Batata inglesa',25,500,8,'BA-1'],['Banana',200,600,8,'BN-1'],['Mandioca',20,500,10,'MA-1'],['Tomate',20,400,5,'TO-1'],['Cebola',20,300,8,'CB-1'],['Frango',20,200,6,'FR-1'],
            ]);
            $second=$createDocument($suppliers[1],'pnae_federal','DEMO-002',6200,[
                ['Banana',100,1000,8,'BN-2'],['Batata inglesa',30,800,8,'BA-2'],['Tomate',20,800,5,'TO-2'],['Mandioca',20,1000,10,'MA-2'],['Frango',40,1000,6,'FR-2'],['Cebola',25,400,8,'CB-2'],['Achocolatado em pó',40,800,180,'AC-1'],['Óleo de soja',40,400,180,'OL-1'],
            ]);
            $third=$createDocument($suppliers[2],'recurso_proprio','DEMO-003',100,[['Biscoito recheado',10,70,180,'BI-1'],['Bebida láctea',4,30,-1,'BL-VENCIDO']]);

            $exitOp=$db->prepare('INSERT INTO operacao (unidade_id,tipo,motivo,usuario_id,criado_em) VALUES (:unit,\'SAIDA\',:reason,:user,:date)');
            $exitMove=$db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,criado_em) VALUES (:op,:lot,\'SAIDA\',:reason,:qty,:user,:date)');
            $bananaLots=$first['lots']['Banana']+$second['lots']['Banana'];$bananaLot=(int)$bananaLots[0]['id'];
            for($i=44;$i>=0;$i--){$date=$today->modify('-'.$i.' days')->format('Y-m-d');$exitOp->execute(['unit'=>$unitId,'reason'=>'consumo','user'=>$demoUsers[1],'date'=>$date.' 11:00:00']);$op=(int)$db->lastInsertId();$exitMove->execute(['op'=>$op,'lot'=>$bananaLot,'reason'=>'consumo','qty'=>'-1.000','user'=>$demoUsers[1],'date'=>$date.' 11:01:00']);}
            $potatoLot=(int)$first['lots']['Batata inglesa'][0]['id'];
            for($i=14;$i>=0;$i--){$date=$today->modify('-'.$i.' days')->format('Y-m-d');$exitOp->execute(['unit'=>$unitId,'reason'=>'consumo','user'=>$demoUsers[1],'date'=>$date.' 12:00:00']);$op=(int)$db->lastInsertId();$exitMove->execute(['op'=>$op,'lot'=>$potatoLot,'reason'=>'consumo','qty'=>'-1.000','user'=>$demoUsers[1],'date'=>$date.' 12:01:00']);}
            foreach([[$bananaLot,'-0.500'],[$potatoLot,'-0.500']] as $i=>$loss){$date=$today->modify('-'.(3-$i).' days')->format('Y-m-d');$exitOp->execute(['unit'=>$unitId,'reason'=>'perda','user'=>$demoUsers[1],'date'=>$date.' 13:00:00']);$op=(int)$db->lastInsertId();$exitMove->execute(['op'=>$op,'lot'=>$loss[0],'reason'=>'perda','qty'=>$loss[1],'user'=>$demoUsers[1],'date'=>$date.' 13:01:00']);}
            foreach ([['Óleo de soja',$second],['Carne moída',$first]] as [$name,$source]){$lot=(int)$source['lots'][$name][0]['id'];$qty=$name==='Óleo de soja'?-40:-20;$exitOp->execute(['unit'=>$unitId,'reason'=>'consumo','user'=>$demoUsers[1],'date'=>$today->format('Y-m-d').' 14:00:00']);$op=(int)$db->lastInsertId();$exitMove->execute(['op'=>$op,'lot'=>$lot,'reason'=>'consumo','qty'=>(string)$qty,'user'=>$demoUsers[1],'date'=>$today->format('Y-m-d').' 14:01:00']);}
            $adjustOp=$db->prepare('INSERT INTO operacao (unidade_id,tipo,motivo,usuario_id,observacao,criado_em) VALUES (:unit,\'AJUSTE\',\'entrada_nao_registrada\',:user,:note,:date)');$adjustOp->execute(['unit'=>$unitId,'user'=>$adminId,'note'=>'Ajuste de demonstração aguardando conferência.','date'=>$today->modify('-1 day')->format('Y-m-d').' 15:00:00']);$pendingOp=(int)$db->lastInsertId();$cassavaLot=(int)$first['lots']['Mandioca'][0]['id'];$pendingMove=$db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,criado_em) VALUES (:op,:lot,\'AJUSTE\',\'entrada_nao_registrada\',\'1.000\',:user,:date)');$pendingMove->execute(['op'=>$pendingOp,'lot'=>$cassavaLot,'user'=>$adminId,'date'=>$today->modify('-1 day')->format('Y-m-d').' 15:01:00']);$pendingMoveId=(int)$db->lastInsertId();$db->prepare("INSERT INTO pendencia_revisao (operacao_id,movimentacao_id,observacao,status) VALUES (:op,:move,'Conferir a entrada registrada fora do documento.', 'aberta')")->execute(['op'=>$pendingOp,'move'=>$pendingMoveId]);
            $undoDate=$today->modify('-2 days')->format('Y-m-d');$adjustOp->execute(['unit'=>$unitId,'user'=>$adminId,'note'=>'Movimento de demonstração estornado.','date'=>$undoDate.' 16:00:00']);$originalOp=(int)$db->lastInsertId();$originalMove=$db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,criado_em) VALUES (:op,:lot,\'AJUSTE\',\'erro_contagem\',\'1.000\',:user,:date)');$originalMove->execute(['op'=>$originalOp,'lot'=>$potatoLot,'user'=>$adminId,'date'=>$undoDate.' 16:01:00']);$originalMoveId=(int)$db->lastInsertId();
            $reversal=$db->prepare('INSERT INTO operacao (unidade_id,tipo,motivo,estorna_operacao_id,usuario_id,criado_em) VALUES (:unit,\'ESTORNO\',\'estorno\',:original,:user,:date)');$reversal->execute(['unit'=>$unitId,'original'=>$originalOp,'user'=>$adminId,'date'=>$today->format('Y-m-d').' 16:00:00']);$reversalId=(int)$db->lastInsertId();$db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,estorno_id,criado_em) VALUES (:op,:lot,\'ESTORNO\',\'estorno\',\'-1.000\',:user,:move,:date)')->execute(['op'=>$reversalId,'lot'=>$potatoLot,'user'=>$adminId,'move'=>$originalMoveId,'date'=>$today->format('Y-m-d').' 16:01:00']);
            $db->commit();
            return ['unit_id'=>$unitId,'products'=>count($products),'documents'=>3,'movements'=>(int)$db->query('SELECT COUNT(*) FROM movimentacao')->fetchColumn(),'accounts'=>['admin@example.com','cozinha@example.com','recebimento@example.com'],'password'=>'DemoSCA#2026!'];
        } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/../Config/Database.php';
    try { $result=DemoSeeder::restore(Database::getConnection()); echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL; }
    catch(Throwable $e) { fwrite(STDERR,'Falha ao restaurar demonstração: '.$e->getMessage().PHP_EOL); exit(1); }
}
