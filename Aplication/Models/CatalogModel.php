<?php
declare(strict_types=1);

final class CatalogModel extends BaseModel
{
    public function load(string $page, int $unitId, array $query = []): array
    {
        $db = $this->db();
        return match ($page) {
            'produtos' => $this->products($unitId,$query),
            'produto-form' => ['product' => $this->productForm($unitId, (int) ($query['id'] ?? 0)), 'categories' => $this->categories()],
            'fornecedores' => $this->suppliers($unitId,$query),
            'fornecedor-form' => ['record' => $this->supplierForm($unitId, (int) ($query['id'] ?? 0))],
            'categorias' => $this->categoryList($query),
            'categoria-form' => ['record' => $this->categoryForm((int)($query['id']??0))],
            'documentos' => $this->documents($unitId,$query),
            'documento' => $this->documentDetail($unitId, (int) ($query['id'] ?? 0)),
            'usuarios' => $this->users($unitId,$query)+['permissions' => $this->permissionCatalog(), 'selectedPermissions' => $this->userPermissions((int) ($query['id'] ?? 0), $unitId)],
            'usuario-form' => ['record' => $this->userForm((int) ($query['id'] ?? 0), $unitId), 'permissions' => $this->permissionCatalog(), 'selectedPermissions' => $this->userPermissions((int) ($query['id'] ?? 0), $unitId)],
            'configuracoes' => ['records' => $this->configuration($unitId)],
            default => [],
        };
    }

    public function saveProduct(int $unitId, array $input): int
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $category = (int) ($input['categoria_id'] ?? 0);
        $measure = (string) ($input['unidade_medida'] ?? '');
        $grade = (string) ($input['grau_processamento'] ?? 'nao_classificado');
        $minimum = (float) ($input['estoque_minimo'] ?? 0);
        if ($name === '' || $category <= 0 || !in_array($measure, ['kg','L','un','pct'], true) || $minimum < 0) throw new DomainException('Confira nome, categoria, unidade de medida e estoque mínimo.');
        if (!in_array($grade, ['in_natura_minimamente_processado','ingrediente_culinario','processado','ultraprocessado','nao_classificado'], true)) throw new DomainException('Selecione uma classificação de processamento válida.');
        $check = $this->db()->prepare('SELECT id FROM categoria WHERE id=:id AND ativo=1');
        $check->execute(['id' => $category]);
        if (!$check->fetchColumn()) throw new DomainException('Selecione uma categoria ativa.');
        $id = (int) ($input['id'] ?? 0);
        $values = [
            'unit' => $unitId, 'category' => $category, 'name' => $name, 'measure' => $measure,
            'fraction' => !empty($input['aceita_fracao']) ? 1 : 0, 'minimum' => $minimum,
            'grade' => $grade, 'vedado' => !empty($input['vedado_pnae']) ? 1 : 0,
            'notes' => trim((string) ($input['observacoes'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            $update = $this->db()->prepare('UPDATE produto SET categoria_id=:category,nome=:name,unidade_medida=:measure,aceita_fracao=:fraction,estoque_minimo=:minimum,grau_processamento=:grade,vedado_pnae=:vedado,observacoes=:notes WHERE id=:id AND unidade_id=:unit AND arquivado_em IS NULL');
            $update->execute($values + ['id' => $id]);
            if ($update->rowCount() === 0) throw new DomainException('Produto não encontrado ou nenhuma informação foi alterada.');
            return $id;
        }
        $insert = $this->db()->prepare('INSERT INTO produto (unidade_id,categoria_id,nome,unidade_medida,aceita_fracao,estoque_minimo,grau_processamento,vedado_pnae,observacoes) VALUES (:unit,:category,:name,:measure,:fraction,:minimum,:grade,:vedado,:notes)');
        $insert->execute($values);
        return (int) $this->db()->lastInsertId();
    }

    public function archiveProduct(int $unitId, int $productId): void
    {
        $statement = $this->db()->prepare('UPDATE produto SET arquivado_em=CURRENT_TIMESTAMP WHERE id=:id AND unidade_id=:unit AND arquivado_em IS NULL');
        $statement->execute(['id' => $productId, 'unit' => $unitId]);
        if (!$statement->rowCount()) throw new DomainException('Produto não encontrado ou já arquivado.');
    }

    public function saveSupplier(int $unitId, array $input): int
    {
        $name = trim((string) ($input['nome'] ?? ''));
        if ($name === '') throw new DomainException('Informe o nome do fornecedor.');
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Informe um e-mail válido para o fornecedor.');
        $id = (int) ($input['id'] ?? 0);
        $values = [
            'unit' => $unitId, 'name' => $name,
            'document' => preg_replace('/\D+/', '', (string) ($input['cpf_cnpj'] ?? '')) ?: null,
            'phone' => trim((string) ($input['telefone'] ?? '')) ?: null,
            'email' => $email ?: null,
            'lead' => ($input['prazo_reposicao_dias'] ?? '') === '' ? null : max(0, (int) $input['prazo_reposicao_dias']),
            'family' => !empty($input['agricultura_familiar']) ? 1 : 0,
        ];
        if ($id > 0) {
            $exists=$this->db()->prepare('SELECT id FROM fornecedor WHERE id=:id AND unidade_id=:unit AND ativo=1');
            $exists->execute(['id'=>$id,'unit'=>$unitId]);
            if(!$exists->fetchColumn())throw new DomainException('Fornecedor não encontrado ou inativo nesta unidade.');
            $query = $this->db()->prepare('UPDATE fornecedor SET nome=:name,cpf_cnpj=:document,telefone=:phone,email=:email,prazo_reposicao_dias=:lead,agricultura_familiar=:family WHERE id=:id AND unidade_id=:unit AND ativo=1');
            $query->execute($values + ['id' => $id]);
            return $id;
        }
        $query = $this->db()->prepare('INSERT INTO fornecedor (unidade_id,nome,cpf_cnpj,telefone,email,prazo_reposicao_dias,agricultura_familiar) VALUES (:unit,:name,:document,:phone,:email,:lead,:family)');
        $query->execute($values);
        return (int) $this->db()->lastInsertId();
    }

    public function deactivateSupplier(int $unitId, int $supplierId): void
    {
        $query = $this->db()->prepare('UPDATE fornecedor SET ativo=0 WHERE id=:id AND unidade_id=:unit AND ativo=1');
        $query->execute(['id' => $supplierId, 'unit' => $unitId]);
        if (!$query->rowCount()) throw new DomainException('Fornecedor não encontrado ou já arquivado.');
    }

    public function saveCategory(array $input): void
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $alert = (int) ($input['janela_alerta_validade_dias'] ?? 0);
        $standard = ($input['validade_padrao_dias'] ?? '') === '' ? null : (int) $input['validade_padrao_dias'];
        if ($name === '' || $alert < 0 || ($standard !== null && $standard < 1)) throw new DomainException('Informe nome e valores válidos para os dias de validade.');
        $id = (int) ($input['id'] ?? 0);
        if ($id > 0) {
            $query = $this->db()->prepare('UPDATE categoria SET nome=:name,janela_alerta_validade_dias=:alert,validade_padrao_dias=:standard,ativo=:active WHERE id=:id');
            $query->execute(['name' => $name,'alert' => $alert,'standard' => $standard,'active' => isset($input['ativo']) ? 1 : 0,'id' => $id]);
        } else {
            $query = $this->db()->prepare('INSERT INTO categoria (nome,janela_alerta_validade_dias,validade_padrao_dias) VALUES (:name,:alert,:standard)');
            $query->execute(['name' => $name,'alert' => $alert,'standard' => $standard]);
        }
    }

    public function saveUser(int $unitId, int $actorId, array $input): int
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['senha'] ?? '');
        $id = (int) ($input['id'] ?? 0);
        $permissions = array_values(array_unique(array_map('strval', $input['permissoes'] ?? [])));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($id === 0 && strlen($password) < 12)) throw new DomainException('Informe nome, e-mail e senha de pelo menos 12 caracteres para novo usuário.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $validPermissions = $db->prepare('SELECT id FROM permissao WHERE id IN (' . implode(',', array_fill(0, count($permissions) ?: 1, '?')) . ')');
            $validPermissions->execute($permissions ?: ['']);
            if (count($validPermissions->fetchAll(PDO::FETCH_COLUMN)) !== count($permissions)) throw new DomainException('Uma das permissões selecionadas não existe no catálogo.');
            if ($id > 0) {
                $exists = $db->prepare('SELECT id,ativo FROM usuario WHERE id=:id AND unidade_id=:unit FOR UPDATE');
                $exists->execute(['id' => $id,'unit' => $unitId]);
                $current = $exists->fetch();
                if (!$current) throw new DomainException('Usuário não encontrado nesta unidade.');
                $managerId = $db->query("SELECT id FROM permissao WHERE id='gerir_usuarios'")->fetchColumn();
                $hasManager = $db->prepare('SELECT 1 FROM usuario_permissao WHERE usuario_id=:user AND permissao_id=:permission');
                $hasManager->execute(['user'=>$id,'permission'=>$managerId]);
                $losesManager = $hasManager->fetchColumn() && (!in_array('gerir_usuarios',$permissions,true) || empty($input['ativo']));
                if ($losesManager) {
                    $count = $db->prepare('SELECT COUNT(DISTINCT u.id) FROM usuario u JOIN usuario_permissao up ON up.usuario_id=u.id JOIN permissao p ON p.id=up.permissao_id WHERE u.unidade_id=:unit AND u.ativo=1 AND p.id=\'gerir_usuarios\'');
                    $count->execute(['unit'=>$unitId]);
                    if ((int)$count->fetchColumn() <= 1) throw new DomainException('A unidade precisa manter ao menos uma pessoa ativa com acesso à gestão de usuários.');
                }
                $sql = 'UPDATE usuario SET nome=:name,email=:email,ativo=:active';
                $params = ['name' => $name,'email' => $email,'active' => !empty($input['ativo']) ? 1 : 0,'id' => $id,'unit' => $unitId];
                if ($password !== '') { if (strlen($password) < 12) throw new DomainException('A nova senha precisa ter pelo menos 12 caracteres.'); $sql .= ',senha_hash=:hash'; $params['hash'] = password_hash($password, PASSWORD_DEFAULT); }
                $sql .= ' WHERE id=:id AND unidade_id=:unit';
                $update = $db->prepare($sql); $update->execute($params);
                $userId = $id;
            } else {
                $insert = $db->prepare('INSERT INTO usuario (unidade_id,nome,email,senha_hash,ativo) VALUES (:unit,:name,:email,:hash,1)');
                $insert->execute(['unit' => $unitId,'name' => $name,'email' => $email,'hash' => password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $db->lastInsertId();
            }
            $existingQuery = $db->prepare('SELECT permissao_id FROM usuario_permissao WHERE usuario_id=:id');
            $existingQuery->execute(['id' => $userId]);
            $existing = $existingQuery->fetchAll(PDO::FETCH_COLUMN);
            $removed = array_diff($existing, $permissions);
            $added = array_diff($permissions, $existing);
            if ($removed) {
                $delete = $db->prepare('DELETE FROM usuario_permissao WHERE usuario_id=:user AND permissao_id=:permission');
                $history = $db->prepare("INSERT INTO usuario_permissao_historico (usuario_id,permissao_id,acao,realizado_por) VALUES (:user,:permission,'revogada',:actor)");
                foreach ($removed as $permission) { $delete->execute(['user' => $userId,'permission' => $permission]); $history->execute(['user' => $userId,'permission' => $permission,'actor' => $actorId]); }
            }
            $grant = $db->prepare('INSERT INTO usuario_permissao (usuario_id,permissao_id,concedido_por) VALUES (:user,:permission,:actor)');
            $history = $db->prepare("INSERT INTO usuario_permissao_historico (usuario_id,permissao_id,acao,realizado_por) VALUES (:user,:permission,'concedida',:actor)");
            foreach ($added as $permission) { $grant->execute(['user' => $userId,'permission' => $permission,'actor' => $actorId]); $history->execute(['user' => $userId,'permission' => $permission,'actor' => $actorId]); }
            $db->commit();
            return $userId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function setUserActive(int $unitId, int $actorId, int $targetId, bool $active): void
    {
        $db=$this->db();$db->beginTransaction();
        try {
            $target=$db->prepare('SELECT id,ativo FROM usuario WHERE id=:id AND unidade_id=:unit FOR UPDATE');$target->execute(['id'=>$targetId,'unit'=>$unitId]);$row=$target->fetch();
            if(!$row)throw new DomainException('Usuário não encontrado nesta unidade.');
            if(!$active){
                $permissionId=$db->query("SELECT id FROM permissao WHERE id='gerir_usuarios'")->fetchColumn();
                $check=$db->prepare('SELECT 1 FROM usuario_permissao WHERE usuario_id=:user AND permissao_id=:permission');$check->execute(['user'=>$targetId,'permission'=>$permissionId]);
                if($check->fetchColumn()){$count=$db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuario u JOIN usuario_permissao up ON up.usuario_id=u.id WHERE u.unidade_id=:unit AND u.ativo=1 AND up.permissao_id='gerir_usuarios'");$count->execute(['unit'=>$unitId]);if((int)$count->fetchColumn()<=1)throw new DomainException('A unidade precisa manter ao menos uma pessoa ativa com acesso à gestão de usuários.');}
            }
            $db->prepare('UPDATE usuario SET ativo=:active WHERE id=:id AND unidade_id=:unit')->execute(['active'=>$active?1:0,'id'=>$targetId,'unit'=>$unitId]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public function saveConfig(int $unitId, int $userId, array $values): void
    {
        $db = $this->db();
        $catalog = $db->query('SELECT chave,tipo_valor,valor_padrao,valores_permitidos,editavel_pela_unidade FROM configuracao_catalogo')->fetchAll();
        $byKey = [];
        foreach ($catalog as $item) $byKey[$item['chave']] = $item;
        $db->beginTransaction();
        try {
            $upsert = $db->prepare('INSERT INTO configuracao_unidade (unidade_id,chave,valor,atualizado_por) VALUES (:unit,:key,:value,:user) ON DUPLICATE KEY UPDATE valor=VALUES(valor),atualizado_por=VALUES(atualizado_por),atualizado_em=CURRENT_TIMESTAMP');
            foreach ($values as $key => $value) {
                if (!isset($byKey[$key]) || !$byKey[$key]['editavel_pela_unidade']) throw new DomainException('Esta configuração não pode ser alterada pela unidade.');
                $spec = $byKey[$key];
                $value = trim((string) $value);
                if ($value === '' && $spec['valor_padrao'] === null) {
                    $db->prepare('DELETE FROM configuracao_unidade WHERE unidade_id=:unit AND chave=:key')->execute(['unit'=>$unitId,'key'=>$key]);
                    continue;
                }
                $allowed = $spec['valores_permitidos'] ? json_decode($spec['valores_permitidos'], true) : null;
                if ($spec['tipo_valor'] === 'opcao' && !in_array($value, $allowed ?: [], true)) throw new DomainException('Valor não permitido para ' . $spec['nome'] . '.');
                if (in_array($spec['tipo_valor'], ['inteiro','decimal'], true) && !is_numeric($value)) throw new DomainException('Informe um número válido para ' . $spec['nome'] . '.');
                if ($spec['tipo_valor'] === 'inteiro' && floor((float) $value) !== (float) $value) throw new DomainException('Use um número inteiro para ' . $spec['nome'] . '.');
                if ($spec['tipo_valor'] === 'booleano' && !in_array($value, ['0','1'], true)) throw new DomainException('Selecione um valor booleano válido para ' . $spec['nome'] . '.');
                if (is_array($allowed) && isset($allowed['min'],$allowed['max']) && ((float) $value < (float) $allowed['min'] || (float) $value > (float) $allowed['max'])) throw new DomainException('O valor de ' . $spec['nome'] . ' está fora do intervalo permitido.');
                if ($key === 'meta_interna_in_natura_pct') {
                    $minimum = $db->query('SELECT min_in_natura FROM regra_pnae WHERE vigente_desde<=CURDATE() AND (vigente_ate IS NULL OR vigente_ate>=CURDATE()) ORDER BY vigente_desde DESC LIMIT 1')->fetchColumn();
                    if ($minimum !== false && (float)$value < (float)$minimum) throw new DomainException('A meta interna não pode ficar abaixo do mínimo PNAE vigente (' . $minimum . '%).');
                }
                $upsert->execute(['unit' => $unitId,'key' => $key,'value' => $value,'user' => $userId]);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function cancelDocument(int $unitId, int $userId, int $documentId, string $reason): void
    {
        if (trim($reason) === '') throw new DomainException('Informe o motivo do cancelamento.');
        $db=$this->db();$db->beginTransaction();
        try {
            $document=$db->prepare('SELECT id FROM documento_fiscal WHERE id=:id AND unidade_id=:unit AND status=\'ativo\' FOR UPDATE');
            $document->execute(['id'=>$documentId,'unit'=>$unitId]);
            if(!$document->fetchColumn())throw new DomainException('Documento não encontrado ou já cancelado.');
            $linked=$db->prepare('SELECT id FROM operacao WHERE documento_fiscal_id=:doc AND tipo<>\'ESTORNO\'');$linked->execute(['doc'=>$documentId]);
            foreach($linked->fetchAll(PDO::FETCH_COLUMN) as $operationId){$reversed=$db->prepare('SELECT id FROM operacao WHERE estorna_operacao_id=:id LIMIT 1');$reversed->execute(['id'=>$operationId]);if(!$reversed->fetchColumn())throw new DomainException('Estorne primeiro todas as operações vinculadas ao documento. Assim os saldos e o histórico permanecem consistentes.');}
            $statement=$db->prepare('UPDATE documento_fiscal SET status=\'cancelado\',cancelado_por=:user,cancelado_em=CURRENT_TIMESTAMP,motivo_cancelamento=:reason WHERE id=:id AND unidade_id=:unit AND status=\'ativo\'');
            $statement->execute(['user'=>$userId,'reason'=>trim($reason),'id'=>$documentId,'unit'=>$unitId]);$db->commit();
        }catch(Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    }

    private function products(int $unitId,array $filters): array
    {
        $where='p.unidade_id=:unit AND p.arquivado_em IS NULL';$params=['unit'=>$unitId];
        if(!empty($filters['q'])){$where.=' AND (p.nome LIKE :q OR c.nome LIKE :q)';$params['q']='%'.trim((string)$filters['q']).'%';}
        if(isset($filters['categoria_id'])&&ctype_digit((string)$filters['categoria_id'])&&(int)$filters['categoria_id']>0){$where.=' AND c.id=:category';$params['category']=(int)$filters['categoria_id'];}
        if(in_array($filters['unidade_medida']??'', ['kg','L','un','pct'], true)){$where.=' AND p.unidade_medida=:measure';$params['measure']=$filters['unidade_medida'];}
        if(in_array((string)($filters['fracao']??''), ['0','1'], true)){$where.=' AND p.aceita_fracao=:fraction';$params['fraction']=(int)$filters['fracao'];}
        if(in_array((string)($filters['vedado_pnae']??''), ['0','1'], true)){$where.=' AND p.vedado_pnae=:blocked';$params['blocked']=(int)$filters['vedado_pnae'];}
        $from=' FROM produto p INNER JOIN categoria c ON c.id=p.categoria_id WHERE '.$where;
        return $this->pagedRows('SELECT p.id,p.nome,p.unidade_medida,p.estoque_minimo,p.grau_processamento,p.vedado_pnae,p.aceita_fracao,c.nome AS categoria_nome'.$from.' ORDER BY p.nome,p.id','SELECT COUNT(*)'.$from,$params,$filters)+['categories'=>$this->categories()];
    }

    private function productForm(int $unitId, int $id): ?array
    {
        if (!$id) return null;
        $query = $this->db()->prepare('SELECT * FROM produto WHERE id=:id AND unidade_id=:unit AND arquivado_em IS NULL');
        $query->execute(['id' => $id,'unit' => $unitId]); return $query->fetch() ?: null;
    }

    private function suppliers(int $unitId,array $filters): array
    {
        $where='f.unidade_id=:unit AND f.ativo=1';$params=['unit'=>$unitId];if(!empty($filters['q'])){$where.=' AND (f.nome LIKE :qname OR f.cpf_cnpj LIKE :qdoc OR f.email LIKE :qmail)';$term='%'.trim((string)$filters['q']).'%';$params['qname']=$term;$params['qdoc']=$term;$params['qmail']=$term;}
        $from=' FROM fornecedor f LEFT JOIN lote l ON l.fornecedor_id=f.id WHERE '.$where;
        return $this->pagedRows('SELECT f.id,f.nome,f.cpf_cnpj,f.email,f.telefone,f.prazo_reposicao_dias,f.agricultura_familiar,COUNT(DISTINCT l.id) AS lotes'.$from.' GROUP BY f.id,f.nome,f.cpf_cnpj,f.email,f.telefone,f.prazo_reposicao_dias,f.agricultura_familiar ORDER BY f.nome,f.id','SELECT COUNT(*) FROM fornecedor f WHERE '.$where,$params,$filters);
    }

    private function supplierForm(int $unitId, int $id): ?array
    {
        if (!$id) return null;
        $query = $this->db()->prepare('SELECT * FROM fornecedor WHERE id=:id AND unidade_id=:unit AND ativo=1');
        $query->execute(['id' => $id,'unit' => $unitId]); return $query->fetch() ?: null;
    }

    private function categories(bool $includeInactive = false): array
    {
        $query = $this->db()->query('SELECT id,nome,janela_alerta_validade_dias,validade_padrao_dias,ativo FROM categoria' . ($includeInactive ? '' : ' WHERE ativo=1') . ' ORDER BY nome');
        return $query->fetchAll();
    }

    private function categoryForm(int $id): ?array
    {
        if($id<=0)return null;$query=$this->db()->prepare('SELECT id,nome,janela_alerta_validade_dias,validade_padrao_dias,ativo FROM categoria WHERE id=:id');$query->execute(['id'=>$id]);return $query->fetch()?:null;
    }

    private function categoryList(array $filters): array
    {
        $where='1=1';$params=[];if(!empty($filters['q'])){$where='nome LIKE :q';$params['q']='%'.trim((string)$filters['q']).'%';}if(in_array((string)($filters['status']??''),['0','1'],true)){$where.=empty($params)?'ativo=:active':' AND ativo=:active';$params['active']=(int)$filters['status'];}
        return $this->pagedRows('SELECT id,nome,janela_alerta_validade_dias,validade_padrao_dias,ativo FROM categoria WHERE '.$where.' ORDER BY nome,id','SELECT COUNT(*) FROM categoria WHERE '.$where,$params,$filters);
    }

    private function documents(int $unitId,array $filters): array
    {
        $where='d.unidade_id=:unit';$params=['unit'=>$unitId];if(!empty($filters['q'])){$where.=' AND (d.numero LIKE :qnum OR f.nome LIKE :qsupplier)';$term='%'.trim((string)$filters['q']).'%';$params['qnum']=$term;$params['qsupplier']=$term;}
        if(in_array($filters['status']??'', ['ativo','cancelado'], true)){$where.=' AND d.status=:status';$params['status']=$filters['status'];}
        $from=' FROM documento_fiscal d LEFT JOIN fornecedor f ON f.id=d.fornecedor_id INNER JOIN usuario u ON u.id=d.registrado_por WHERE '.$where;
        return $this->pagedRows('SELECT d.id,d.tipo,d.numero,d.serie,d.emitido_em,d.valor_total,d.status,d.anexo,d.criado_em,d.substitui_id,d.motivo_cancelamento,f.nome AS fornecedor_nome,u.nome AS registrado_por'.$from.' ORDER BY d.criado_em DESC,d.id DESC','SELECT COUNT(*)'.$from,$params,$filters);
    }

    public function documentDetail(int $unitId, int $documentId): array
    {
        if ($documentId <= 0) return ['document' => null, 'items' => [], 'operationId' => null];
        $stmt = $this->db()->prepare('SELECT d.*, f.nome AS fornecedor_nome, f.cpf_cnpj AS fornecedor_cnpj, f.agricultura_familiar, u.nome AS registrado_por, uc.nome AS cancelado_por_nome FROM documento_fiscal d LEFT JOIN fornecedor f ON f.id=d.fornecedor_id INNER JOIN usuario u ON u.id=d.registrado_por LEFT JOIN usuario uc ON uc.id=d.cancelado_por WHERE d.id=:id AND d.unidade_id=:unit');
        $stmt->execute(['id' => $documentId, 'unit' => $unitId]);
        $document = $stmt->fetch();
        if (!$document) return ['document' => null, 'items' => [], 'operationId' => null];

        $itemsStmt = $this->db()->prepare('SELECT l.id AS lote_id, l.codigo_lote, l.validade, l.validade_estimada, l.valor_item, l.origem_recurso, p.id AS produto_id, p.nome AS produto_nome, p.unidade_medida, p.grau_processamento, COALESCE(m.quantidade, 0) AS quantidade FROM lote l INNER JOIN produto p ON p.id=l.produto_id LEFT JOIN movimentacao m ON m.lote_id=l.id AND m.tipo=\'ENTRADA\' WHERE l.documento_fiscal_id=:doc ORDER BY l.id');
        $itemsStmt->execute(['doc' => $documentId]);
        $items = $itemsStmt->fetchAll();

        $opStmt = $this->db()->prepare('SELECT id FROM operacao WHERE documento_fiscal_id=:doc AND tipo=\'ENTRADA\' LIMIT 1');
        $opStmt->execute(['doc' => $documentId]);
        $operationId = $opStmt->fetchColumn() ?: null;

        return [
            'document' => $document,
            'items' => $items,
            'operationId' => $operationId,
        ];
    }

    private function users(int $unitId,array $filters): array
    {
        $where='u.unidade_id=:unit';$params=['unit'=>$unitId];if(!empty($filters['q'])){$where.=' AND (u.nome LIKE :qname OR u.email LIKE :qemail)';$params['qname']='%'.trim((string)$filters['q']).'%';$params['qemail']='%'.trim((string)$filters['q']).'%';}
        $from=' FROM usuario u LEFT JOIN usuario_permissao up ON up.usuario_id=u.id LEFT JOIN permissao p ON p.id=up.permissao_id WHERE '.$where;
        return $this->pagedRows('SELECT u.id,u.nome,u.email,u.ativo,u.criado_em,COUNT(up.permissao_id) AS total_permissoes,GROUP_CONCAT(up.permissao_id) AS permission_ids'.$from.' GROUP BY u.id,u.nome,u.email,u.ativo,u.criado_em ORDER BY u.nome,u.id','SELECT COUNT(*) FROM usuario u WHERE '.$where,$params,$filters);
    }

    private function permissionCatalog(): array
    {
        return $this->db()->query('SELECT id,nome,descricao,grupo FROM permissao ORDER BY grupo,nome')->fetchAll();
    }

    private function userPermissions(int $userId, int $unitId): array
    {
        if (!$userId) return [];
        $query = $this->db()->prepare('SELECT up.permissao_id FROM usuario_permissao up INNER JOIN usuario u ON u.id=up.usuario_id WHERE u.id=:id AND u.unidade_id=:unit');
        $query->execute(['id' => $userId,'unit' => $unitId]); return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    private function userForm(int $userId, int $unitId): ?array
    {
        if (!$userId) return null;
        $query = $this->db()->prepare('SELECT id,nome,email,ativo FROM usuario WHERE id=:id AND unidade_id=:unit');
        $query->execute(['id' => $userId,'unit' => $unitId]); return $query->fetch() ?: null;
    }

    private function configuration(int $unitId): array
    {
        $query = $this->db()->prepare('SELECT cc.chave,cc.nome,cc.descricao,cc.tipo_valor,cc.valor_padrao,cc.valores_permitidos,cc.editavel_pela_unidade,cu.valor AS valor_unidade FROM configuracao_catalogo cc LEFT JOIN configuracao_unidade cu ON cu.chave=cc.chave AND cu.unidade_id=:unit ORDER BY cc.nome');
        $query->execute(['unit' => $unitId]); return $query->fetchAll();
    }
}
