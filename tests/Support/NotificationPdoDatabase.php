<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Contracts\DatabaseInterface;
use PDO;
use Throwable;

/**
 * DatabaseInterface over an existing PDO, so a test can share one in-memory
 * SQLite database between DatabaseChannel (PDO) and the repository.
 *
 * Name-prefixed: every package shares the `Tests\` namespace in the root run.
 */
final class NotificationPdoDatabase implements DatabaseInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($bindings));

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($bindings));

        return $stmt->rowCount();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $fn();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
