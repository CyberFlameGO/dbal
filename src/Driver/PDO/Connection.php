<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use PDO;
use PDOException;
use PDOStatement;

use function assert;

final class Connection implements ServerInfoAwareConnection
{
    private PDO $connection;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param array<int, mixed> $options
     *
     * @throws ExceptionInterface
     */
    public function __construct(string $dsn, string $username = '', string $password = '', array $options = [])
    {
        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function exec(string $sql): int
    {
        try {
            $result = $this->connection->exec($sql);

            assert($result !== false);

            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getServerVersion(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritDoc}
     *
     * @return Statement
     */
    public function prepare(string $sql): StatementInterface
    {
        try {
            $stmt = $this->connection->prepare($sql);
            assert($stmt instanceof PDOStatement);

            return $this->createStatement($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function query(string $sql): ResultInterface
    {
        try {
            $stmt = $this->connection->query($sql);
            assert($stmt instanceof PDOStatement);

            return new Result($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        try {
            $value = $this->connection->lastInsertId();
        } catch (PDOException $exception) {
            [$sqlState] = $exception->errorInfo;

            // if the PDO driver does not support this capability, PDO::lastInsertId() triggers an IM001 SQLSTATE
            // see https://www.php.net/manual/en/pdo.lastinsertid.php
            if ($sqlState === 'IM001') {
                throw IdentityColumnsNotSupported::new();
            }

            // PDO PGSQL throws a 'lastval is not yet defined in this session' error when no identity value is
            // available, with SQLSTATE 55000 'Object Not In Prerequisite State'
            if ($sqlState === '55000' && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                throw NoIdentityValue::new($exception);
            }

            throw Exception::new($exception);
        }

        // pdo_mysql & pdo_sqlite return '0', pdo_sqlsrv returns ''
        if ($value === '0' || $value === '') {
            throw NoIdentityValue::new();
        }

        return $value;
    }

    /**
     * Creates a wrapped statement
     */
    protected function createStatement(PDOStatement $stmt): Statement
    {
        return new Statement($stmt);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->connection->rollBack();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getWrappedConnection(): PDO
    {
        return $this->connection;
    }
}
