<?php
declare(strict_types=1);

final class PasswordResetModel extends BaseModel
{
    public function findActiveUserId(string $identifier): ?int
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $find = $this->db()->prepare(
                'SELECT id FROM usuario WHERE ativo = 1 AND LOWER(email) = LOWER(:identifier) LIMIT 1'
            );
            $find->execute(['identifier' => $identifier]);
            $id = $find->fetchColumn();
            return $id === false ? null : (int) $id;
        }

        $find = $this->db()->prepare(
            'SELECT id FROM usuario WHERE ativo = 1 AND LOWER(nome) = LOWER(:identifier) LIMIT 2'
        );
        $find->execute(['identifier' => $identifier]);
        $matches = $find->fetchAll(PDO::FETCH_COLUMN);
        return count($matches) === 1 ? (int) $matches[0] : null;
    }

    public function resetPasswordForUserId(int $userId, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $this->db()->prepare(
            'UPDATE usuario SET senha_hash = :senha_hash WHERE id = :usuario_id AND ativo = 1'
        );
        $statement->execute(['senha_hash' => $hash, 'usuario_id' => $userId]);
        return $statement->rowCount() === 1;
    }

    public function issue(string $identifier): ?array
    {
        $db = $this->db();
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $find = $db->prepare(
                'SELECT id, nome, email FROM usuario
                 WHERE ativo = 1 AND LOWER(email) = LOWER(:identifier)
                 LIMIT 1'
            );
            $find->execute(['identifier' => $identifier]);
            $user = $find->fetch();
        } else {
            $find = $db->prepare(
                'SELECT id, nome, email FROM usuario
                 WHERE ativo = 1 AND LOWER(nome) = LOWER(:identifier)
                 LIMIT 2'
            );
            $find->execute(['identifier' => $identifier]);
            $matches = $find->fetchAll();
            $user = count($matches) === 1 ? $matches[0] : null;
        }
        if (!$user) return null;

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $db->beginTransaction();
        try {
            $invalidate = $db->prepare(
                'UPDATE senha_redefinicao
                 SET invalidado_em = CURRENT_TIMESTAMP
                 WHERE usuario_id = :usuario_id
                   AND usado_em IS NULL
                   AND invalidado_em IS NULL'
            );
            $invalidate->execute(['usuario_id' => $user['id']]);

            $insert = $db->prepare(
                'INSERT INTO senha_redefinicao (usuario_id, token_hash, expira_em)
                 VALUES (:usuario_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
            );
            $insert->execute(['usuario_id' => $user['id'], 'token_hash' => $tokenHash]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }

        return ['nome' => $user['nome'], 'email' => $user['email'], 'token' => $plainToken];
    }

    public function isValid(string $token): bool
    {
        if (!$this->validTokenShape($token)) return false;
        $statement = $this->db()->prepare(
            'SELECT id FROM senha_redefinicao
             WHERE token_hash = :token_hash
               AND usado_em IS NULL
               AND invalidado_em IS NULL
               AND expira_em > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        return (bool) $statement->fetchColumn();
    }

    public function reset(string $token, string $password): bool
    {
        if (!$this->validTokenShape($token)) return false;

        $db = $this->db();
        $db->beginTransaction();
        try {
            $find = $db->prepare(
                'SELECT id, usuario_id FROM senha_redefinicao
                 WHERE token_hash = :token_hash
                   AND usado_em IS NULL
                   AND invalidado_em IS NULL
                   AND expira_em > CURRENT_TIMESTAMP
                 LIMIT 1 FOR UPDATE'
            );
            $find->execute(['token_hash' => hash('sha256', $token)]);
            $record = $find->fetch();
            if (!$record) {
                $db->rollBack();
                return false;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $updateUser = $db->prepare('UPDATE usuario SET senha_hash = :senha_hash WHERE id = :usuario_id AND ativo = 1');
            $updateUser->execute(['senha_hash' => $hash, 'usuario_id' => $record['usuario_id']]);
            if ($updateUser->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            $useToken = $db->prepare('UPDATE senha_redefinicao SET usado_em = CURRENT_TIMESTAMP WHERE id = :id');
            $useToken->execute(['id' => $record['id']]);
            $invalidate = $db->prepare(
                'UPDATE senha_redefinicao
                 SET invalidado_em = CURRENT_TIMESTAMP
                 WHERE usuario_id = :usuario_id AND id <> :id
                   AND usado_em IS NULL AND invalidado_em IS NULL'
            );
            $invalidate->execute(['usuario_id' => $record['usuario_id'], 'id' => $record['id']]);
            $db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    private function validTokenShape(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1;
    }
}
