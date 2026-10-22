<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven;

use PDO;
use Override;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;

/**
 * SQL adapter of ReplayJtiGateway over `_oauth_replay_jti`.
 *
 * The primary key (tenant_id, client_id, purpose, jti) makes the INSERT IGNORE the whole
 * check-and-set: only the request whose INSERT adds the row consumes the JWT, without a
 * previous SELECT that two concurrent requests could both pass.
 */
class ReplayJtiSqlAdapter implements ReplayJtiGateway
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    #[Override]
    public function consume(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti, \DateTimeImmutable $expiresAt): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO _oauth_replay_jti (jti, tenant_id, client_id, purpose, expires_at)
             VALUES (:jti, :tenant_id, :client_id, :purpose, :expires_at)'
        );
        $stmt->bindValue('jti', $jti, PDO::PARAM_STR);
        $stmt->bindValue('tenant_id', $tenant, PDO::PARAM_STR);
        $stmt->bindValue('client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue('purpose', $purpose->value, PDO::PARAM_STR);
        $stmt->bindValue('expires_at', $expiresAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    #[Override]
    public function release(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM _oauth_replay_jti WHERE tenant_id = :tenant_id AND client_id = :client_id AND purpose = :purpose AND jti = :jti'
        );
        $stmt->bindValue('tenant_id', $tenant, PDO::PARAM_STR);
        $stmt->bindValue('client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue('purpose', $purpose->value, PDO::PARAM_STR);
        $stmt->bindValue('jti', $jti, PDO::PARAM_STR);
        $stmt->execute();
    }

    #[Override]
    public function purgeExpired(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM _oauth_replay_jti WHERE expires_at < :now');
        $stmt->bindValue('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }
}
