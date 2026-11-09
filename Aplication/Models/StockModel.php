<?php
declare(strict_types=1);

final class StockModel extends BaseModel
{
    public function loadPage(string $page, int $unitId, array $filters = []): array
    {
        return match ($page) {
            'saida-rapida' => ['products' => $this->productsWithBalance($unitId), 'mostUsed' => $this->mostUsedProducts($unitId)],
            'entrada-documento' => ['products' => $this->activeProducts($unitId), 'suppliers' => $this->suppliers($unitId), 'documents' => $this->activeDocuments($unitId)],
            'estoque' => $this->stockList($unitId, $filters),
            'produto' => $this->productDetail($unitId, (int) ($filters['id'] ?? 0)),
            'alertas' => $this->alerts($unitId, $filters),
            'movimentacoes' => $this->movements($unitId, $filters),
            'operacao' => $this->operationDetail($unitId, (int) ($filters['id'] ?? 0)),
            'ajuste-estoque' => ['lots' => $this->lotsWithBalance($unitId)],
            'pendencias' => $this->pendingReviews($unitId, $filters),
            'lista-compras' => $this->shoppingList($unitId, $filters),
            default => [],
        };
    }

    public function createExit(int $unitId, int $userId, int $productId, float $quantity, string $reason, ?string $notes, string $excessReason = ''): int
    {
        if ($quantity <= 0) throw new DomainException('Informe uma quantidade maior que zero.');
        if (!in_array($reason, ['consumo','perda','vencimento','descarte'], true)) throw new DomainException('Selecione um motivo válido para a saída.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $product = $db->prepare('SELECT id, unidade_medida, aceita_fracao FROM produto WHERE id = :id AND unidade_id = :unit AND arquivado_em IS NULL FOR UPDATE');
            $product->execute(['id' => $productId, 'unit' => $unitId]);
            $productRow = $product->fetch();
            if (!$productRow) throw new DomainException('Produto não encontrado nesta unidade.');
            if (!$productRow['aceita_fracao'] && floor($quantity) !== $quantity) throw new DomainException('Este produto não aceita quantidade fracionária.');

            $lots = $db->prepare('SELECT id, validade FROM lote WHERE produto_id = :product ORDER BY validade ASC, id ASC FOR UPDATE');
            $lots->execute(['product' => $productId]);
            $balanceQuery = $db->prepare('SELECT COALESCE(SUM(quantidade),0) FROM movimentacao WHERE lote_id=:lot');
            $available = [];
            $allLots=$lots->fetchAll();
            foreach ($allLots as $lot) {
                $balanceQuery->execute(['lot' => $lot['id']]);
                $lot['saldo'] = (float) $balanceQuery->fetchColumn();
                if ($lot['saldo'] > 0) $available[] = $lot;
            }
            $total = array_sum(array_map(static fn ($lot) => (float) $lot['saldo'], $available));
            $excess = max(0, $quantity - $total);
            if ($excess > 0 && !in_array($excessReason, ['entrada_nao_registrada','erro_contagem'], true)) throw new DomainException('Informe se a diferença ocorreu por entrada não registrada ou erro de contagem. Saldo registrado: ' . number_format($total, 3, ',', '.') . ' ' . $productRow['unidade_medida'] . '.');
            if ($excess > 0 && !$allLots) throw new DomainException('Registre um lote do produto antes de informar uma retirada acima do saldo.');

            $operationId = $this->insertOperation($unitId, $userId, 'SAIDA', $reason, $notes);
            if ($excess > 0) {
                $targetLot = $available ? end($available) : (function () use ($productId, $db) { $q=$db->prepare('SELECT id FROM lote WHERE produto_id=:product ORDER BY validade,id LIMIT 1');$q->execute(['product'=>$productId]);return ['id'=>$q->fetchColumn()]; })();
                $adjustment=$db->prepare("INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,observacao) VALUES (:op,:lot,'AJUSTE',:reason,:qty,:user,:notes)");
                $adjustment->execute(['op'=>$operationId,'lot'=>$targetLot['id'],'reason'=>$excessReason,'qty'=>$excess,'user'=>$userId,'notes'=>'Diferença informada na saída rápida. '.($notes??'')]);
                $pending=$db->prepare("INSERT INTO pendencia_revisao (operacao_id,movimentacao_id,observacao,status) VALUES (:op,:move,'Revisar a diferença entre o saldo registrado e a quantidade retirada.','aberta')");
                $pending->execute(['op'=>$operationId,'move'=>$db->lastInsertId()]);
                if (!$available) $available[]=['id'=>$targetLot['id'],'saldo'=>0];
                $available[count($available)-1]['saldo'] += $excess;
                $total += $excess;
            }
            $remaining = $quantity;
            $insert = $db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,observacao) VALUES (:op,:lot,\'SAIDA\',:reason,:qty,:user,:notes)');
            foreach ($available as $lot) {
                if ($remaining <= 0) break;
                $take = min($remaining, (float) $lot['saldo']);
                $insert->execute(['op' => $operationId, 'lot' => $lot['id'], 'reason' => $reason, 'qty' => -$take, 'user' => $userId, 'notes' => $notes]);
                $remaining = round($remaining - $take, 3);
            }
            $db->commit();
            return $operationId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function createEntry(int $unitId, int $userId, array $input): int
    {
        $type = (string) ($input['tipo_documento'] ?? '');
        $origin = (string) ($input['origem_recurso'] ?? '');
        if (!in_array($type, ['nota_fiscal_eletronica','nota_produtor_rural','termo_doacao','outro'], true)) throw new DomainException('Selecione o tipo de documento.');
        if (!in_array($origin, ['pnae_federal','recurso_proprio','doacao'], true)) throw new DomainException('Selecione a origem do recurso.');
        $rawItems = $input['itens'] ?? [];
        foreach ($rawItems as $item) {
            $partial = !empty($item['produto_id']) || (float) ($item['quantidade'] ?? 0) > 0 || !empty($item['validade']) || !empty($item['codigo_lote']);
            if ($partial && (empty($item['produto_id']) || (float) ($item['quantidade'] ?? 0) <= 0 || empty($item['validade']))) throw new DomainException('Complete produto, quantidade e validade para cada item iniciado.');
        }
        $items = array_values(array_filter($rawItems, static fn ($item) => !empty($item['produto_id']) && (float) ($item['quantidade'] ?? 0) > 0));
        if (!$items) throw new DomainException('Adicione ao menos um item válido ao documento.');
        if ($origin !== 'doacao' && (float) ($input['valor_total'] ?? 0) <= 0) throw new DomainException('Informe o valor total do documento de compra.');
        if ($origin !== 'doacao') foreach ($items as $item) {
            if (!isset($item['valor_item']) || $item['valor_item'] === '' || !is_numeric($item['valor_item']) || (float)$item['valor_item'] < 0) throw new DomainException('Informe o valor de cada item da compra.');
        }
        foreach ($items as $item) {
            $expiry = (string)($item['validade'] ?? '');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);
            if (!$date || $date->format('Y-m-d') !== $expiry) throw new DomainException('Informe uma data de validade válida para cada lote.');
        }
        $receivedInput=(string)($input['recebido_em']??date('Y-m-d'));
        $receivedDate=DateTimeImmutable::createFromFormat('!Y-m-d',$receivedInput);
        if(!$receivedDate||$receivedDate->format('Y-m-d')!==$receivedInput)throw new DomainException('Informe uma data de recebimento válida.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $supplierId = ($input['fornecedor_id'] ?? '') !== '' ? (int) $input['fornecedor_id'] : null;
            if ($supplierId !== null) $this->assertSupplierInUnit($supplierId, $unitId);
            if ($origin === 'doacao' && $type !== 'termo_doacao') throw new DomainException('Entradas por doação devem usar termo de doação.');
            $replaceId = !empty($input['substitui_id']) ? (int) $input['substitui_id'] : null;
            if ($replaceId !== null) {
                $old = $db->prepare('SELECT id,status FROM documento_fiscal WHERE id=:id AND unidade_id=:unit FOR UPDATE');
                $old->execute(['id' => $replaceId, 'unit' => $unitId]);
                if (($old->fetch()['status'] ?? null) !== 'ativo') throw new DomainException('O documento escolhido não existe ou já foi cancelado.');
                $linked = $db->prepare('SELECT id FROM operacao WHERE documento_fiscal_id=:doc AND tipo<>\'ESTORNO\'');
                $linked->execute(['doc' => $replaceId]);
                foreach ($linked->fetchAll(PDO::FETCH_COLUMN) as $linkedOperation) {
                    $reversed = $db->prepare('SELECT id FROM operacao WHERE estorna_operacao_id=:id LIMIT 1');
                    $reversed->execute(['id' => $linkedOperation]);
                    if (!$reversed->fetchColumn()) throw new DomainException('Antes de substituir o documento, estorne a operação de entrada vinculada para preservar o saldo e o histórico.');
                }
            }
            $doc = $db->prepare('INSERT INTO documento_fiscal (unidade_id,fornecedor_id,tipo,numero,serie,chave_acesso,emitido_em,valor_total,doador_nome,destinatario_conferido,identificada_pnae,anexo,substitui_id,registrado_por) VALUES (:unit,:supplier,:type,:number,:series,:key,:issued,:total,:donor,:recipient,:pnae,:attachment,:replacement,:user)');
            $doc->execute([
                'unit' => $unitId, 'supplier' => $supplierId, 'type' => $type,
                'number' => trim((string) ($input['numero'] ?? '')) ?: null,
                'series' => trim((string) ($input['serie'] ?? '')) ?: null,
                'key' => trim((string) ($input['chave_acesso'] ?? '')) ?: null,
                'issued' => ($input['emitido_em'] ?? '') ?: null,
                'total' => $origin === 'doacao' ? null : (float) ($input['valor_total'] ?? 0),
                'donor' => trim((string) ($input['doador_nome'] ?? '')) ?: null,
                'recipient' => !empty($input['destinatario_conferido']) ? 1 : 0,
                'pnae' => !empty($input['identificada_pnae']) ? 1 : 0,
                'attachment' => trim((string) ($input['anexo'] ?? '')) ?: null, 'replacement' => $replaceId, 'user' => $userId,
            ]);
            $documentId = (int) $db->lastInsertId();
            if ($replaceId !== null) {
                $cancel = $db->prepare('UPDATE documento_fiscal SET status=\'cancelado\',cancelado_por=:user,cancelado_em=CURRENT_TIMESTAMP,motivo_cancelamento=\'Substituído pelo documento #\' WHERE id=:id AND unidade_id=:unit AND status=\'ativo\'');
                $cancel->execute(['user' => $userId, 'id' => $replaceId, 'unit' => $unitId]);
                $setReason = $db->prepare('UPDATE documento_fiscal SET motivo_cancelamento=CONCAT(\'Substituído pelo documento #\',:new_id) WHERE id=:id');
                $setReason->execute(['new_id' => $documentId, 'id' => $replaceId]);
            }
            $operationId = $this->insertOperation($unitId, $userId, 'ENTRADA', $origin === 'doacao' ? 'doacao' : 'compra', trim((string) ($input['observacao'] ?? '')) ?: null, $documentId);
            $productQuery = $db->prepare('SELECT id, unidade_medida, aceita_fracao FROM produto WHERE id = :id AND unidade_id = :unit AND arquivado_em IS NULL');
            $lotInsert = $db->prepare('INSERT INTO lote (produto_id,documento_fiscal_id,fornecedor_id,origem_recurso,codigo_lote,validade,validade_estimada,valor_item,recebido_em) VALUES (:product,:doc,:supplier,:origin,:code,:expiry,:estimated,:value,:received)');
            $moveInsert = $db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id) VALUES (:op,:lot,\'ENTRADA\',:reason,:qty,:user)');
            $sum = 0.0;
            foreach ($items as $item) {
                $productQuery->execute(['id' => (int) $item['produto_id'], 'unit' => $unitId]);
                $product = $productQuery->fetch();
                if (!$product) throw new DomainException('Um dos produtos não pertence à unidade.');
                $qty = (float) $item['quantidade'];
                if (!$product['aceita_fracao'] && floor($qty) !== $qty) throw new DomainException('Um dos produtos não aceita quantidade fracionária.');
                $expiry = (string) ($item['validade'] ?? '');
                $received = (string) ($input['recebido_em'] ?? date('Y-m-d'));
                $value = $origin === 'doacao' ? null : (float) ($item['valor_item'] ?? 0);
                $lotInsert->execute([
                    'product' => $product['id'], 'doc' => $documentId, 'supplier' => $supplierId, 'origin' => $origin,
                    'code' => trim((string) ($item['codigo_lote'] ?? '')) ?: null, 'expiry' => $expiry,
                    'estimated' => !empty($item['validade_estimada']) ? 1 : 0, 'value' => $value, 'received' => $received,
                ]);
                $lotId = (int) $db->lastInsertId();
                $moveInsert->execute(['op' => $operationId, 'lot' => $lotId, 'reason' => $origin === 'doacao' ? 'doacao' : 'compra', 'qty' => $qty, 'user' => $userId]);
                $sum += (float) ($value ?? 0);
            }
            if ($origin !== 'doacao' && (float) ($input['valor_total'] ?? 0) > 0 && abs($sum - (float) $input['valor_total']) > 0.01) {
                $pending = $db->prepare('INSERT INTO pendencia_revisao (operacao_id,observacao) VALUES (:op,:notes)');
                $pending->execute(['op' => $operationId, 'notes' => 'A soma dos itens diverge do valor total do documento. Conferir frete e desconto.']);
            }
            $db->commit();
            return $operationId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function adjust(int $unitId, int $userId, int $lotId, float $delta, string $reason, ?string $notes): int
    {
        if ($delta == 0 || !in_array($reason, ['erro_contagem','entrada_nao_registrada','perda','descarte'], true)) throw new DomainException('Informe um ajuste diferente de zero e um motivo válido.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $lot = $db->prepare('SELECT l.id,p.aceita_fracao FROM lote l INNER JOIN produto p ON p.id = l.produto_id WHERE l.id = :lot AND p.unidade_id = :unit FOR UPDATE');
            $lot->execute(['lot' => $lotId, 'unit' => $unitId]);
            $lotRow=$lot->fetch();
            if (!$lotRow) throw new DomainException('Lote não encontrado nesta unidade.');
            if (!$lotRow['aceita_fracao'] && floor(abs($delta)) !== abs($delta)) throw new DomainException('Este produto não aceita quantidade fracionária.');
            $balance = $db->prepare('SELECT COALESCE(SUM(quantidade),0) FROM movimentacao WHERE lote_id=:lot');
            $balance->execute(['lot' => $lotId]);
            if ((float) $balance->fetchColumn() + $delta < 0) throw new DomainException('O ajuste não pode deixar o lote com saldo negativo.');
            $op = $this->insertOperation($unitId, $userId, 'AJUSTE', $reason, $notes);
            $move = $db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,observacao) VALUES (:op,:lot,\'AJUSTE\',:reason,:qty,:user,:notes)');
            $move->execute(['op' => $op, 'lot' => $lotId, 'reason' => $reason, 'qty' => $delta, 'user' => $userId, 'notes' => $notes]);
            $db->commit();
            return $op;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function reverse(int $unitId, int $userId, int $operationId, ?string $notes): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $opQuery = $db->prepare('SELECT id,tipo,estorna_operacao_id FROM operacao WHERE id = :id AND unidade_id = :unit FOR UPDATE');
            $opQuery->execute(['id' => $operationId, 'unit' => $unitId]);
            $original = $opQuery->fetch();
            if (!$original || $original['tipo'] === 'ESTORNO' || $original['estorna_operacao_id'] !== null) throw new DomainException('Esta operação não pode ser estornada.');
            $alreadyReversed = $db->prepare('SELECT id FROM operacao WHERE estorna_operacao_id=:id LIMIT 1');
            $alreadyReversed->execute(['id' => $operationId]);
            if ($alreadyReversed->fetchColumn()) throw new DomainException('Esta operação já foi estornada.');
            $lockProducts = $db->prepare('SELECT p.id FROM movimentacao m INNER JOIN lote l ON l.id=m.lote_id INNER JOIN produto p ON p.id=l.produto_id WHERE m.operacao_id=:op ORDER BY p.id FOR UPDATE');
            $lockProducts->execute(['op' => $operationId]);
            $lockProducts->fetchAll();
            $lines = $db->prepare('SELECT m.id,m.lote_id,m.quantidade,COALESCE((SELECT SUM(mx.quantidade) FROM movimentacao mx WHERE mx.lote_id=m.lote_id),0) AS saldo FROM movimentacao m WHERE m.operacao_id = :op FOR UPDATE');
            $lines->execute(['op' => $operationId]);
            $rows = $lines->fetchAll();
            $byLot=[];foreach($rows as $line){$id=(int)$line['lote_id'];$byLot[$id]['saldo']=(float)$line['saldo'];$byLot[$id]['quantidade']=($byLot[$id]['quantidade']??0)+(float)$line['quantidade'];}
            foreach ($byLot as $lotState) if ($lotState['saldo'] - $lotState['quantidade'] < -0.0001) throw new DomainException('O estorno deixaria um lote com saldo negativo. Faça um ajuste de estoque antes.');
            $reverseOp = $this->insertOperation($unitId, $userId, 'ESTORNO', 'estorno', $notes, null, $operationId);
            $insert = $db->prepare('INSERT INTO movimentacao (operacao_id,lote_id,tipo,motivo,quantidade,usuario_id,estorno_id,observacao) VALUES (:op,:lot,\'ESTORNO\',\'estorno\',:qty,:user,:original,:notes)');
            foreach ($rows as $line) $insert->execute(['op' => $reverseOp, 'lot' => $line['lote_id'], 'qty' => -(float) $line['quantidade'], 'user' => $userId, 'original' => $line['id'], 'notes' => $notes]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function review(int $unitId, int $userId, int $pendingId, string $notes): void
    {
        $statement = $this->db()->prepare('UPDATE pendencia_revisao pr INNER JOIN operacao o ON o.id=pr.operacao_id SET pr.status=\'revisada\',pr.observacao=CONCAT(pr.observacao,\'\\nRevisão: \',:notes),pr.revisado_por=:user,pr.revisado_em=CURRENT_TIMESTAMP WHERE pr.id=:id AND o.unidade_id=:unit AND pr.status=\'aberta\'');
        $statement->execute(['notes' => trim($notes) ?: 'Revisada sem observação adicional.', 'user' => $userId, 'id' => $pendingId, 'unit' => $unitId]);
        if ($statement->rowCount() !== 1) throw new DomainException('Pendência não encontrada ou já revisada.');
    }

    private function insertOperation(int $unitId, int $userId, string $type, string $reason, ?string $notes, ?int $documentId = null, ?int $reverses = null): int
    {
        $statement = $this->db()->prepare('INSERT INTO operacao (unidade_id,tipo,motivo,documento_fiscal_id,estorna_operacao_id,usuario_id,observacao) VALUES (:unit,:type,:reason,:doc,:reverses,:user,:notes)');
        $statement->execute(['unit' => $unitId, 'type' => $type, 'reason' => $reason, 'doc' => $documentId, 'reverses' => $reverses, 'user' => $userId, 'notes' => $notes]);
        return (int) $this->db()->lastInsertId();
    }

    private function assertSupplierInUnit(int $supplierId, int $unitId): void
    {
        $check = $this->db()->prepare('SELECT id FROM fornecedor WHERE id=:id AND unidade_id=:unit AND ativo=1');
        $check->execute(['id' => $supplierId, 'unit' => $unitId]);
        if (!$check->fetchColumn()) throw new DomainException('Fornecedor não encontrado nesta unidade.');
    }

    private function activeProducts(int $unitId): array
    {
        $query = $this->db()->prepare('SELECT p.id,p.nome,p.unidade_medida,p.aceita_fracao,c.validade_padrao_dias FROM produto p JOIN categoria c ON c.id=p.categoria_id WHERE p.unidade_id=:unit AND p.arquivado_em IS NULL ORDER BY p.nome,p.id');
        $query->execute(['unit' => $unitId]);
        return $query->fetchAll();
    }

    private function suppliers(int $unitId): array
    {
        $query = $this->db()->prepare('SELECT id,nome,agricultura_familiar FROM fornecedor WHERE unidade_id=:unit AND ativo=1 ORDER BY nome');
        $query->execute(['unit' => $unitId]);
        return $query->fetchAll();
    }

    private function activeDocuments(int $unitId): array
    {
        $query=$this->db()->prepare("SELECT id,tipo,numero,serie,emitido_em FROM documento_fiscal WHERE unidade_id=:unit AND status='ativo' ORDER BY criado_em DESC LIMIT 100");
        $query->execute(['unit'=>$unitId]); return $query->fetchAll();
    }

    public function searchProductsWithBalance(int $unitId, string $term = ''): array
    {
        $sql = 'SELECT p.id,p.nome,p.unidade_medida,p.aceita_fracao,p.estoque_minimo,c.validade_padrao_dias,COALESCE(SUM(b.saldo),0) AS saldo FROM produto p LEFT JOIN categoria c ON c.id=p.categoria_id LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN (SELECT lote_id,SUM(quantidade) AS saldo FROM movimentacao GROUP BY lote_id) b ON b.lote_id=l.id WHERE p.unidade_id=:unit AND p.arquivado_em IS NULL';
        $params = ['unit' => $unitId];
        if ($term !== '') {
            $sql .= ' AND p.nome LIKE :term';
            $params['term'] = '%' . $term . '%';
        }
        $sql .= ' GROUP BY p.id,p.nome,p.unidade_medida,p.aceita_fracao,p.estoque_minimo,c.validade_padrao_dias ORDER BY p.nome LIMIT 30';
        $query = $this->db()->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    }

    private function productsWithBalance(int $unitId): array
    {
        $query = $this->db()->prepare('SELECT p.id,p.nome,p.unidade_medida,p.aceita_fracao,p.estoque_minimo,COALESCE(SUM(b.saldo),0) AS saldo FROM produto p LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN (SELECT lote_id,SUM(quantidade) AS saldo FROM movimentacao GROUP BY lote_id) b ON b.lote_id=l.id WHERE p.unidade_id=:unit AND p.arquivado_em IS NULL GROUP BY p.id,p.nome,p.unidade_medida,p.aceita_fracao,p.estoque_minimo ORDER BY p.nome');
        $query->execute(['unit' => $unitId]);
        return $query->fetchAll();
    }

    private function mostUsedProducts(int $unitId): array
    {
        $sql='SELECT p.id,p.nome FROM movimentacao m JOIN lote l ON l.id=m.lote_id JOIN produto p ON p.id=l.produto_id JOIN operacao o ON o.id=m.operacao_id WHERE p.unidade_id=:unit AND m.tipo=\'SAIDA\' AND m.motivo=\'consumo\' AND m.criado_em>=DATE_SUB(CURRENT_DATE,INTERVAL 30 DAY) GROUP BY p.id,p.nome ORDER BY SUM(ABS(m.quantidade)) DESC,p.nome LIMIT 8';
        $q=$this->db()->prepare($sql);$q->execute(['unit'=>$unitId]);return $q->fetchAll();
    }

    private function stockList(int $unitId, array $filters): array
    {
        $where='p.unidade_id=:unit AND p.arquivado_em IS NULL';$params=['unit'=>$unitId];
        if (!empty($filters['q'])) {$where.=' AND (p.nome LIKE :q OR c.nome LIKE :qcat)';$term='%'.trim((string)$filters['q']).'%';$params['q']=$term;$params['qcat']=$term;}
        if(in_array($filters['unidade_medida']??'', ['kg','L','un','pct'], true)){$where.=' AND p.unidade_medida=:measure';$params['measure']=$filters['unidade_medida'];}
        if(in_array((string)($filters['vedado_pnae']??''), ['0','1'], true)){$where.=' AND p.vedado_pnae=:blocked';$params['blocked']=(int)$filters['vedado_pnae'];}
        $having=[];if(($filters['saldo_status']??'')==='baixo')$having[]='(COALESCE(SUM(b.saldo),0)<=0 OR (p.estoque_minimo>0 AND COALESCE(SUM(b.saldo),0)<=p.estoque_minimo))';elseif(($filters['saldo_status']??'')==='zerado')$having[]='COALESCE(SUM(b.saldo),0)<=0';elseif(($filters['saldo_status']??'')==='normal')$having[]='(p.estoque_minimo<=0 OR COALESCE(SUM(b.saldo),0)>p.estoque_minimo) AND COALESCE(SUM(b.saldo),0)>0';
        $group=' GROUP BY p.id,p.nome,p.unidade_medida,p.estoque_minimo,p.grau_processamento,p.vedado_pnae';$havingSql=$having?' HAVING '.implode(' AND ',$having):'';
        $sql='SELECT p.id,p.nome,p.unidade_medida,p.estoque_minimo,p.grau_processamento,p.vedado_pnae,COALESCE(SUM(b.saldo),0) AS saldo,MIN(CASE WHEN b.saldo>0 THEN l.validade END) AS proxima_validade,COUNT(CASE WHEN b.saldo>0 THEN l.id END) AS lotes FROM produto p INNER JOIN categoria c ON c.id=p.categoria_id LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN (SELECT lote_id,SUM(quantidade) AS saldo FROM movimentacao GROUP BY lote_id) b ON b.lote_id=l.id WHERE '.$where.$group.$havingSql.' ORDER BY p.nome,p.id';
        $count='SELECT COUNT(*) FROM (SELECT p.id FROM produto p INNER JOIN categoria c ON c.id=p.categoria_id LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN (SELECT lote_id,SUM(quantidade) AS saldo FROM movimentacao GROUP BY lote_id) b ON b.lote_id=l.id WHERE '.$where.$group.$havingSql.') AS stock_count';
        return $this->pagedRows($sql,$count,$params,$filters);
    }

    private function lotsWithBalance(int $unitId): array
    {
        $query = $this->db()->prepare('SELECT l.id,p.nome,p.unidade_medida,p.aceita_fracao,l.codigo_lote,l.validade,COALESCE(SUM(m.quantidade),0) AS saldo FROM lote l INNER JOIN produto p ON p.id=l.produto_id LEFT JOIN movimentacao m ON m.lote_id=l.id WHERE p.unidade_id=:unit AND p.arquivado_em IS NULL GROUP BY l.id,p.nome,p.unidade_medida,p.aceita_fracao,l.codigo_lote,l.validade ORDER BY p.nome,l.validade,l.id');
        $query->execute(['unit' => $unitId]);
        return $query->fetchAll();
    }

    private function productDetail(int $unitId, int $productId): array
    {
        $query = $this->db()->prepare('SELECT p.*,c.nome AS categoria_nome FROM produto p INNER JOIN categoria c ON c.id=p.categoria_id WHERE p.id=:id AND p.unidade_id=:unit');
        $query->execute(['id' => $productId, 'unit' => $unitId]);
        $product = $query->fetch();
        if (!$product) return ['product' => null, 'lots' => [], 'movements' => []];
        $lots = $this->db()->prepare('SELECT l.id,l.codigo_lote,l.validade,l.validade_estimada,l.origem_recurso,l.valor_item,l.recebido_em,COALESCE(SUM(m.quantidade),0) AS saldo FROM lote l LEFT JOIN movimentacao m ON m.lote_id=l.id WHERE l.produto_id=:id GROUP BY l.id,l.codigo_lote,l.validade,l.validade_estimada,l.origem_recurso,l.valor_item,l.recebido_em ORDER BY l.validade');
        $lots->execute(['id' => $productId]);
        $moves = $this->db()->prepare('SELECT m.id,m.operacao_id,m.tipo,m.motivo,m.quantidade,m.criado_em,p.unidade_medida,u.nome AS usuario_nome FROM movimentacao m INNER JOIN lote l ON l.id=m.lote_id INNER JOIN produto p ON p.id=l.produto_id LEFT JOIN usuario u ON u.id=m.usuario_id WHERE l.produto_id=:id ORDER BY m.criado_em DESC,m.id DESC LIMIT 12');
        $moves->execute(['id' => $productId]);
        $lotRows=$lots->fetchAll();$balance=array_sum(array_map(static fn($lot)=>(float)$lot['saldo'],$lotRows));
        $windowQuery=$this->db()->prepare("SELECT COALESCE(cu.valor,cc.valor_padrao,30) FROM configuracao_catalogo cc LEFT JOIN configuracao_unidade cu ON cu.chave=cc.chave AND cu.unidade_id=:unit WHERE cc.chave='janela_consumo_cobertura_dias'");
        $windowQuery->execute(['unit'=>$unitId]);$window=max(1,(int)$windowQuery->fetchColumn());
        $consumption=$this->db()->prepare("SELECT COALESCE(SUM(-m.quantidade),0) FROM movimentacao m JOIN lote l ON l.id=m.lote_id JOIN operacao o ON o.id=m.operacao_id WHERE l.produto_id=:product AND m.tipo='SAIDA' AND m.motivo='consumo' AND m.criado_em>=DATE_SUB(CURDATE(),INTERVAL :days DAY) AND NOT EXISTS (SELECT 1 FROM operacao est WHERE est.estorna_operacao_id=o.id)");
        $consumption->execute(['product'=>$productId,'days'=>$window]);$consumed=(float)$consumption->fetchColumn();$average=$consumed/$window;
        return ['product' => $product, 'lots' => $lotRows, 'movements' => $moves->fetchAll(),'balance'=>$balance,'coverage_days'=>$average>0?round($balance/$average,1):null,'window_days'=>$window,'average_daily_consumption'=>$average];
    }

    private function alerts(int $unitId, array $filters): array
    {
        $where='p.unidade_id=:unit AND p.arquivado_em IS NULL';$params=['unit'=>$unitId];
        if(!empty($filters['q'])){$where.=' AND (p.nome LIKE :q OR l.codigo_lote LIKE :code)';$term='%'.trim((string)$filters['q']).'%';$params['q']=$term;$params['code']=$term;}
        $status=$filters['tipo_alerta']??'';$having=' HAVING saldo>0 AND dias<=COALESCE(c.janela_alerta_validade_dias,0)';
        if($status==='vencidos')$having.=' AND dias<0';elseif($status==='proximos')$having.=' AND dias>=0';
        $expiryFrom=' FROM lote l INNER JOIN produto p ON p.id=l.produto_id LEFT JOIN categoria c ON c.id=p.categoria_id LEFT JOIN movimentacao m ON m.lote_id=l.id WHERE '.$where.' GROUP BY l.id,p.id,p.nome,p.unidade_medida,l.codigo_lote,l.validade,c.janela_alerta_validade_dias';
        $expiryCount='SELECT COUNT(*) FROM (SELECT l.id,DATEDIFF(l.validade,CURDATE()) AS dias,COALESCE(SUM(m.quantidade),0) AS saldo'.$expiryFrom.$having.') alert_count';
        $expirySql='SELECT l.id,p.id AS produto_id,p.nome,p.unidade_medida,l.codigo_lote,l.validade,c.janela_alerta_validade_dias,DATEDIFF(l.validade,CURDATE()) AS dias,COALESCE(SUM(m.quantidade),0) AS saldo'.$expiryFrom.$having.' ORDER BY l.validade,l.id';
        $expiryFilters=$filters;$expiryFilters['pagina']=$filters['pagina_validade']??1;$expiry=$this->pagedRows($expirySql,$expiryCount,$params,$expiryFilters);

        $stockWhere='p.unidade_id=:unit AND p.arquivado_em IS NULL';$stockParams=['unit'=>$unitId];
        if(!empty($filters['q'])){$stockWhere.=' AND (p.nome LIKE :q OR c.nome LIKE :category)';$term='%'.trim((string)$filters['q']).'%';$stockParams['q']=$term;$stockParams['category']=$term;}
        $stockHaving=' HAVING saldo<=0 OR (p.estoque_minimo>0 AND saldo<=p.estoque_minimo)';
        if(($filters['status_estoque']??'')==='zerado')$stockHaving=' HAVING saldo<=0';elseif(($filters['status_estoque']??'')==='abaixo')$stockHaving=' HAVING saldo>0 AND p.estoque_minimo>0 AND saldo<=p.estoque_minimo';
        $stockFrom=' FROM produto p INNER JOIN categoria c ON c.id=p.categoria_id LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN movimentacao m ON m.lote_id=l.id WHERE '.$stockWhere.' GROUP BY p.id,p.nome,p.unidade_medida,p.estoque_minimo';
        $stockSelect='SELECT p.id,p.nome,p.unidade_medida,p.estoque_minimo,COALESCE(SUM(m.quantidade),0) AS saldo';
        $stockCount='SELECT COUNT(*) FROM (SELECT p.id,COALESCE(SUM(m.quantidade),0) AS saldo,p.estoque_minimo'.$stockFrom.$stockHaving.') low_count';
        $stockFilters=$filters;$stockFilters['pagina']=$filters['pagina_estoque']??1;$low=$this->pagedRows($stockSelect.$stockFrom.$stockHaving.' ORDER BY saldo,p.nome,p.id',$stockCount,$stockParams,$stockFilters);
        $active=($filters['aba']??'validade')==='estoque'?'estoque':'validade';$selected=$active==='estoque'?$low:$expiry;
        return ['activeTab'=>$active,'records'=>$selected['records'],'pagination'=>$selected['pagination'],'expiry'=>$expiry,'lowStock'=>$low];
    }

    private function movements(int $unitId, array $filters): array
    {
        $where = 'o.unidade_id=:unit';
        $params = ['unit' => $unitId];
        $filters['motivo'] = $filters['motivo'] ?? '';
        foreach (['tipo' => 'm.tipo', 'produto_id' => 'p.id'] as $key => $column) {
            if (!empty($filters[$key])) { $where .= ' AND ' . $column . ' = :' . $key; $params[$key] = $filters[$key]; }
        }
        if(!empty($filters['q'])){$where.=' AND (p.nome LIKE :q OR l.codigo_lote LIKE :code OR u.nome LIKE :person)';$term='%'.trim((string)$filters['q']).'%';$params['q']=$term;$params['code']=$term;$params['person']=$term;}
        if(in_array($filters['motivo']??'', ['compra','doacao','consumo','perda','vencimento','descarte','erro_contagem','entrada_nao_registrada','estorno'], true)){$where.=' AND m.motivo=:reason';$params['reason']=$filters['motivo'];}
        if (!empty($filters['de'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$filters['de'])) { $where .= ' AND m.criado_em >= :date_from'; $params['date_from'] = $filters['de'] . ' 00:00:00'; }
        if (!empty($filters['ate'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$filters['ate'])) { $where .= ' AND m.criado_em < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $filters['ate']; }
        $from=' FROM movimentacao m INNER JOIN operacao o ON o.id=m.operacao_id INNER JOIN lote l ON l.id=m.lote_id INNER JOIN produto p ON p.id=l.produto_id LEFT JOIN usuario u ON u.id=m.usuario_id WHERE '.$where;
        return $this->pagedRows('SELECT m.id,m.operacao_id,m.tipo,m.motivo,m.quantidade,m.criado_em,p.id AS produto_id,p.nome AS produto_nome,p.unidade_medida,u.nome AS usuario_nome'.$from.' ORDER BY m.criado_em DESC,m.id DESC','SELECT COUNT(*)'.$from,$params,$filters);
    }

    private function operationDetail(int $unitId, int $operationId): array
    {
        $query = $this->db()->prepare('SELECT o.*,u.nome AS usuario_nome,d.numero AS documento_numero,d.tipo AS documento_tipo FROM operacao o INNER JOIN usuario u ON u.id=o.usuario_id LEFT JOIN documento_fiscal d ON d.id=o.documento_fiscal_id WHERE o.id=:id AND o.unidade_id=:unit');
        $query->execute(['id' => $operationId, 'unit' => $unitId]);
        $operation = $query->fetch();
        if (!$operation) return ['operation' => null, 'lines' => []];
        $lines = $this->db()->prepare('SELECT m.id,m.tipo,m.motivo,m.quantidade,m.criado_em,m.estorno_id,p.nome AS produto_nome,p.unidade_medida,l.codigo_lote,l.validade FROM movimentacao m INNER JOIN lote l ON l.id=m.lote_id INNER JOIN produto p ON p.id=l.produto_id WHERE m.operacao_id=:id ORDER BY m.id');
        $lines->execute(['id' => $operationId]);
        return ['operation' => $operation, 'lines' => $lines->fetchAll()];
    }

    private function pendingReviews(int $unitId,array $filters): array
    {
        $where='o.unidade_id=:unit';$params=['unit'=>$unitId];if(in_array($filters['status']??'', ['aberta','revisada'], true)){$where.=' AND pr.status=:status';$params['status']=$filters['status'];}if(!empty($filters['q'])){$where.=' AND (pr.observacao LIKE :note OR u.nome LIKE :person OR CAST(o.id AS CHAR) LIKE :operation)';$term='%'.trim((string)$filters['q']).'%';$params['note']=$term;$params['person']=$term;$params['operation']=$term;}
        $from=' FROM pendencia_revisao pr INNER JOIN operacao o ON o.id=pr.operacao_id LEFT JOIN usuario u ON u.id=o.usuario_id WHERE '.$where;
        return $this->pagedRows('SELECT pr.id,pr.status,pr.observacao,pr.criado_em,pr.revisado_em,o.id AS operacao_id,o.motivo,o.usuario_id,u.nome AS autor'.$from.' ORDER BY (pr.status=\'aberta\') DESC,pr.criado_em DESC,pr.id DESC','SELECT COUNT(*)'.$from,$params,$filters);
    }

    private function shoppingList(int $unitId,array $filters): array
    {
        $where='p.unidade_id=:unit AND p.arquivado_em IS NULL';$params=['unit'=>$unitId];if(!empty($filters['q'])){$where.=' AND p.nome LIKE :q';$params['q']='%'.trim((string)$filters['q']).'%';}if(!empty($filters['fornecedor'])){$where.=' AND EXISTS (SELECT 1 FROM lote lf JOIN fornecedor ff ON ff.id=lf.fornecedor_id WHERE lf.produto_id=p.id AND ff.nome LIKE :supplier)';$params['supplier']='%'.trim((string)$filters['fornecedor']).'%';}
        $base=' FROM produto p LEFT JOIN lote l ON l.produto_id=p.id LEFT JOIN (SELECT lote_id,SUM(quantidade) AS saldo FROM movimentacao GROUP BY lote_id) b ON b.lote_id=l.id WHERE '.$where.' GROUP BY p.id,p.nome,p.unidade_medida,p.estoque_minimo HAVING COALESCE(SUM(b.saldo),0)<=0 OR (p.estoque_minimo>0 AND COALESCE(SUM(b.saldo),0)<=p.estoque_minimo)';
        $select='SELECT p.id,p.nome,p.unidade_medida,p.estoque_minimo,COALESCE(SUM(b.saldo),0) AS saldo,(p.estoque_minimo-COALESCE(SUM(b.saldo),0)) AS quantidade_sugerida,(SELECT f.nome FROM lote ll INNER JOIN fornecedor f ON f.id=ll.fornecedor_id WHERE ll.produto_id=p.id ORDER BY ll.recebido_em DESC,ll.id DESC LIMIT 1) AS fornecedor_nome,(SELECT f.prazo_reposicao_dias FROM lote ll INNER JOIN fornecedor f ON f.id=ll.fornecedor_id WHERE ll.produto_id=p.id ORDER BY ll.recebido_em DESC,ll.id DESC LIMIT 1) AS prazo_reposicao_dias';
        $count='SELECT COUNT(*) FROM (SELECT p.id'.$base.') AS shopping_count';
        return $this->pagedRows($select.$base.' ORDER BY fornecedor_nome,p.nome,p.id',$count,$params,$filters);
    }
}
