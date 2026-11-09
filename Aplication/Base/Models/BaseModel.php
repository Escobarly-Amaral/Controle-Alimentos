<?php
declare(strict_types=1);

abstract class BaseModel
{
    protected function db(): PDO
    {
        return Database::getConnection();
    }

    protected function pagedRows(string $sql, string $countSql, array $params, array $filters): array
    {
        $db=$this->db();$size=(int)($filters['por_pagina']??20);if(!in_array($size,[10,20,50],true))$size=20;
        $requested=max(1,(int)($filters['pagina']??1));
        $count=$db->prepare($countSql);foreach($params as $key=>$value)$count->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);$count->execute();$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$size));$page=min($requested,$pages);$offset=($page-1)*$size;
        $statement=$db->prepare($sql.' LIMIT :sca_limit OFFSET :sca_offset');
        foreach($params as $key=>$value)$statement->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $statement->bindValue(':sca_limit',$size,PDO::PARAM_INT);$statement->bindValue(':sca_offset',$offset,PDO::PARAM_INT);$statement->execute();
        return ['records'=>$statement->fetchAll(),'pagination'=>['page'=>$page,'requested_page'=>$requested,'per_page'=>$size,'total'=>$total,'pages'=>$pages,'from'=>$total?($offset+1):0,'to'=>min($offset+$size,$total)]];
    }
}
