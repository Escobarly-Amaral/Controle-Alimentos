<?php
declare(strict_types=1);

final class DashboardModel extends BaseModel
{
    public function load(int $unitId): array
    {
        $today = new DateTimeImmutable('today');
        $exercise = (int) $today->format('Y');
        $db = $this->db();
        $result = [
            'alerts' => ['expired' => [], 'expiring' => [], 'low_stock' => []],
            'activities' => [],
            'pnae' => null,
            'errors' => [],
            'exercise' => $exercise,
        ];

        try {
            $statement = $db->prepare(
                'SELECT l.id, p.nome AS produto_nome, p.unidade_medida, l.validade,
                        COALESCE(c.janela_alerta_validade_dias, 0) AS janela_alerta,
                        COALESCE(SUM(m.quantidade), 0) AS saldo
                 FROM lote l
                 INNER JOIN produto p ON p.id = l.produto_id
                 LEFT JOIN categoria c ON c.id = p.categoria_id
                 LEFT JOIN movimentacao m ON m.lote_id = l.id
                 WHERE p.unidade_id = :unidade_id AND p.arquivado_em IS NULL
                 GROUP BY l.id, p.nome, p.unidade_medida, l.validade, c.janela_alerta_validade_dias
                 HAVING saldo > 0 AND l.validade <= DATE_ADD(CURDATE(), INTERVAL COALESCE(c.janela_alerta_validade_dias, 0) DAY)
                 ORDER BY l.validade ASC'
            );
            $statement->execute(['unidade_id' => $unitId]);
            foreach ($statement->fetchAll() as $lot) {
                $days = (int) $today->diff(new DateTimeImmutable($lot['validade']))->format('%r%a');
                $lot['dias_restantes'] = $days;
                $result['alerts'][$days < 0 ? 'expired' : 'expiring'][] = $lot;
            }
        } catch (Throwable $exception) {
            $result['errors'][] = 'alerts';
            error_log('SCA dashboard expiry alert query failed: ' . $exception->getMessage());
        }

        try {
            $statement = $db->prepare(
                'SELECT p.id, p.nome, p.unidade_medida, p.estoque_minimo,
                        COALESCE(SUM(m.quantidade), 0) AS saldo
                 FROM produto p
                 LEFT JOIN lote l ON l.produto_id = p.id
                 LEFT JOIN movimentacao m ON m.lote_id = l.id
                 WHERE p.unidade_id = :unidade_id AND p.arquivado_em IS NULL
                 GROUP BY p.id, p.nome, p.unidade_medida, p.estoque_minimo
                 HAVING saldo <= 0 OR (p.estoque_minimo > 0 AND saldo <= p.estoque_minimo)
                 ORDER BY saldo ASC'
            );
            $statement->execute(['unidade_id' => $unitId]);
            $result['alerts']['low_stock'] = $statement->fetchAll();
        } catch (Throwable $exception) {
            $result['errors'][] = 'alerts';
            error_log('SCA dashboard stock alert query failed: ' . $exception->getMessage());
        }

        try {
            $statement = $db->prepare(
                'SELECT m.id, m.tipo, m.motivo, m.quantidade, m.criado_em,
                        o.criado_em AS operacao_criada_em, o.id AS operacao_id, m.observacao,
                        p.nome AS produto_nome, p.unidade_medida, l.codigo_lote, u.nome AS usuario_nome
                 FROM movimentacao m
                 INNER JOIN operacao o ON o.id = m.operacao_id
                 INNER JOIN lote l ON l.id = m.lote_id
                 INNER JOIN produto p ON p.id = l.produto_id
                 LEFT JOIN usuario u ON u.id = m.usuario_id
                 WHERE p.unidade_id = :unidade_id
                 ORDER BY o.criado_em DESC, o.id DESC, m.id DESC LIMIT 8'
            );
            $statement->execute(['unidade_id' => $unitId]);
            $result['activities'] = $statement->fetchAll();
        } catch (Throwable $exception) {
            $result['errors'][] = 'activities';
            error_log('SCA dashboard activity query failed: ' . $exception->getMessage());
        }

        try {
            $ruleStatement = $db->query(
                'SELECT min_in_natura, max_proc_ultra, max_ingr_culinario, min_agric_familiar,
                        vigente_desde, vigente_ate, referencia_normativa
                 FROM regra_pnae
                 WHERE vigente_desde <= CURDATE() AND (vigente_ate IS NULL OR vigente_ate >= CURDATE())
                 ORDER BY vigente_desde DESC LIMIT 1'
            );
            $rule = $ruleStatement->fetch();
            if ($rule) {
                $purchases = $db->prepare(
                    'SELECT
                        COALESCE(SUM(CASE WHEN p.grau_processamento = \'in_natura_minimamente_processado\' THEN l.valor_item ELSE 0 END), 0) AS in_natura,
                        COALESCE(SUM(CASE WHEN p.grau_processamento IN (\'processado\', \'ultraprocessado\') THEN l.valor_item ELSE 0 END), 0) AS proc_ultra,
                        COALESCE(SUM(CASE WHEN p.grau_processamento = \'ingrediente_culinario\' THEN l.valor_item ELSE 0 END), 0) AS ingr_culinario,
                        COALESCE(SUM(CASE WHEN p.grau_processamento <> \'nao_classificado\' THEN l.valor_item ELSE 0 END), 0) AS total_classificado,
                        COALESCE(SUM(CASE WHEN p.grau_processamento = \'nao_classificado\' THEN l.valor_item ELSE 0 END), 0) AS nao_classificado,
                        COUNT(DISTINCT CASE WHEN p.grau_processamento = \'nao_classificado\' THEN l.id END) AS itens_sem_classificacao,
                        COALESCE(SUM(l.valor_item), 0) AS total_federal,
                        COALESCE(SUM(CASE WHEN f.agricultura_familiar = 1 THEN l.valor_item ELSE 0 END), 0) AS agricultura_familiar
                     FROM lote l
                     INNER JOIN produto p ON p.id = l.produto_id
                     LEFT JOIN fornecedor f ON f.id = l.fornecedor_id
                     LEFT JOIN documento_fiscal d ON d.id = l.documento_fiscal_id
                     WHERE p.unidade_id = :unidade_id
                       AND l.origem_recurso = \'pnae_federal\'
                       AND l.recebido_em >= :inicio_exercicio AND l.recebido_em < :inicio_proximo_exercicio
                       AND (d.id IS NULL OR d.status = \'ativo\')'
                );
                $purchases->execute([
                    'unidade_id' => $unitId,
                    'inicio_exercicio' => $exercise . '-01-01',
                    'inicio_proximo_exercicio' => ($exercise + 1) . '-01-01',
                ]);
                $amounts = $purchases->fetch();
                $unitGoal = $db->prepare(
                    'SELECT valor FROM configuracao_unidade
                     WHERE unidade_id = :unidade_id AND chave = \'meta_interna_in_natura_pct\' LIMIT 1'
                );
                $unitGoal->execute(['unidade_id' => $unitId]);
                $goal = $unitGoal->fetchColumn();
                $result['pnae'] = [
                    'rule' => $rule,
                    'amounts' => $amounts,
                    'meta_interna_in_natura_pct' => $goal === false || $goal === '' ? null : (float) $goal,
                    'percentuais' => [
                        'in_natura' => $this->percent($amounts['in_natura'], $amounts['total_classificado']),
                        'proc_ultra' => $this->percent($amounts['proc_ultra'], $amounts['total_classificado']),
                        'ingr_culinario' => $this->percent($amounts['ingr_culinario'], $amounts['total_classificado']),
                        'agricultura_familiar' => $this->percent($amounts['agricultura_familiar'], $amounts['total_federal']),
                    ],
                ];
            } else {
                $result['errors'][] = 'pnae';
            }
        } catch (Throwable $exception) {
            $result['errors'][] = 'pnae';
            error_log('SCA dashboard PNAE query failed: ' . $exception->getMessage());
        }

        $result['errors'] = array_values(array_unique($result['errors']));
        return $result;
    }

    private function percent(string|int|float|null $part, string|int|float|null $total): ?float
    {
        $total = (float) $total;
        return $total > 0 ? round(((float) $part / $total) * 100, 1) : null;
    }
}
