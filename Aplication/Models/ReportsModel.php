<?php
declare(strict_types=1);
final class ReportsModel extends BaseModel
{
    public function load(int $unitId,int $year): array
    {
        $db=$this->db();$start=sprintf('%04d-01-01',$year);$end=sprintf('%04d-01-01',$year+1);
        $rule=$db->prepare('SELECT * FROM regra_pnae WHERE vigente_desde<:end AND (vigente_ate IS NULL OR vigente_ate>=:start) ORDER BY vigente_desde DESC LIMIT 1');$rule->execute(['start'=>$start,'end'=>$end]);
        $purchase=$db->prepare("SELECT p.grau_processamento, f.agricultura_familiar, SUM(l.valor_item) total FROM lote l JOIN produto p ON p.id=l.produto_id LEFT JOIN fornecedor f ON f.id=l.fornecedor_id LEFT JOIN documento_fiscal d ON d.id=l.documento_fiscal_id WHERE p.unidade_id=:unit AND l.origem_recurso='pnae_federal' AND l.recebido_em>=:start AND l.recebido_em<:end AND (d.id IS NULL OR d.status='ativo') GROUP BY p.grau_processamento,f.agricultura_familiar");$purchase->execute(['unit'=>$unitId,'start'=>$start,'end'=>$end]);$rows=$purchase->fetchAll();
        $byGrade=[];$total=0.0;$family=0.0;$unclassified=0.0;
        foreach($rows as $row){$amount=(float)$row['total'];$total+=$amount;$byGrade[$row['grau_processamento']]=($byGrade[$row['grau_processamento']]??0)+$amount;if($row['agricultura_familiar'])$family+=$amount;if($row['grau_processamento']==='nao_classificado')$unclassified+=$amount;}
        $waste=$db->prepare("SELECT p.nome,p.unidade_medida,ABS(SUM(m.quantidade)) quantidade,MAX(m.criado_em) ultima FROM movimentacao m JOIN lote l ON l.id=m.lote_id JOIN produto p ON p.id=l.produto_id JOIN operacao o ON o.id=m.operacao_id WHERE o.unidade_id=:unit AND m.tipo='SAIDA' AND m.motivo IN ('perda','vencimento','descarte') AND m.criado_em>=:start AND m.criado_em<:end AND NOT EXISTS (SELECT 1 FROM operacao est WHERE est.estorna_operacao_id=o.id) GROUP BY p.id,p.nome,p.unidade_medida ORDER BY quantidade DESC LIMIT 20");$waste->execute(['unit'=>$unitId,'start'=>$start,'end'=>$end]);
        return ['year'=>$year,'rule'=>$rule->fetch()?:null,'total'=>$total,'family'=>$family,'unclassified'=>$unclassified,'byGrade'=>$byGrade,'waste'=>$waste->fetchAll()];
    }
}
