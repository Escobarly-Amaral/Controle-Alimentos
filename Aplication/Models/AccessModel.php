<?php
declare(strict_types=1);

final class AccessModel extends BaseModel
{
    public function allows(int $userId, string $permission): bool
    {
        $statement = $this->db()->prepare(
            'SELECT 1 FROM usuario_permissao up
             INNER JOIN permissao p ON p.id = up.permissao_id
             INNER JOIN usuario u ON u.id = up.usuario_id
             WHERE up.usuario_id = :usuario_id AND p.id = :permissao AND u.ativo = 1
             LIMIT 1'
        );
        $statement->execute(['usuario_id' => $userId, 'permissao' => $permission]);
        return (bool) $statement->fetchColumn();
    }

    public function permissionsFor(int $userId): array
    {
        $statement = $this->db()->prepare(
            'SELECT p.id, p.nome, p.descricao, p.grupo
             FROM usuario_permissao up
             INNER JOIN permissao p ON p.id = up.permissao_id
             INNER JOIN usuario u ON u.id = up.usuario_id
             WHERE up.usuario_id = :usuario_id AND u.ativo = 1 ORDER BY p.grupo, p.nome'
        );
        $statement->execute(['usuario_id' => $userId]);
        return $statement->fetchAll();
    }
}
