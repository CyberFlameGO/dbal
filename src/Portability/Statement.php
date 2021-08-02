<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;

/**
 * Portability wrapper for a Statement.
 */
final class Statement implements DriverStatement
{
    private DriverStatement $stmt;

    private Converter $converter;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures.
     */
    public function __construct(DriverStatement $stmt, Converter $converter)
    {
        $this->stmt      = $stmt;
        $this->converter = $converter;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null): void
    {
        $this->stmt->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute(?array $params = null): ResultInterface
    {
        return new Result(
            $this->stmt->execute($params),
            $this->converter
        );
    }
}
