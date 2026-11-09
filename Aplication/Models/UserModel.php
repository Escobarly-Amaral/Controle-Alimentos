<?php
declare(strict_types=1);

final class UserModel extends BaseModel
{
    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->db()->prepare(
            'SELECT u.id, u.unidade_id, u.nome, u.email, u.senha_hash, un.nome AS unidade_nome
             FROM usuario AS u
             INNER JOIN unidade AS un ON un.id = u.unidade_id
             WHERE LOWER(u.email) = LOWER(:email) AND u.ativo = 1
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['senha_hash'])) {
            return null;
        }

        if (password_needs_rehash($user['senha_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $this->db()->prepare('UPDATE usuario SET senha_hash = :hash WHERE id = :id');
            $update->execute(['hash' => $newHash, 'id' => $user['id']]);
        }

        unset($user['senha_hash']);
        return $user;
    }
}
