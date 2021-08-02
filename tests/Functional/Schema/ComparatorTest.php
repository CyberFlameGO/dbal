<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = new Comparator();
    }

    /**
     * @param mixed $value
     *
     * @dataProvider defaultValueProvider
     */
    public function testDefaultValueComparison(string $type, $value): void
    {
        $table = new Table('default_value');
        $table->addColumn('test', $type, ['default' => $value]);

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('default_value');

        self::assertNull($this->comparator->diffTable($table, $onlineTable));
    }

    /**
     * @return mixed[][]
     */
    public static function defaultValueProvider(): iterable
    {
        return [
            ['integer', 1],
            ['boolean', false],
        ];
    }
}
