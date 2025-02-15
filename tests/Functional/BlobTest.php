<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\OCI8\Driver as OCI8Driver;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;

use function fopen;
use function str_repeat;
use function stream_get_contents;

class BlobTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDriver() instanceof PDO\OCI\Driver) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped('DBAL doesn\'t support storing LOBs represented as streams using PDO_OCI');
        }

        $table = new Table('blob_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('clobcolumn', 'text');
        $table->addColumn('blobcolumn', 'blob');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsert(): void
    {
        $ret = $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertEquals(1, $ret);
    }

    public function testInsertProcessesStream(): void
    {
        // https://github.com/doctrine/dbal/issues/3290
        if ($this->connection->getDriver() instanceof OCI8Driver) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $longBlob = str_repeat('x', 4 * 8192); // send 4 chunks
        $this->connection->insert('blob_table', [
            'id'        => 1,
            'clobcolumn' => 'ignored',
            'blobcolumn' => fopen('data://text/plain,' . $longBlob, 'r'),
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains($longBlob);
    }

    public function testSelect(): void
    {
        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test');
    }

    public function testUpdate(): void
    {
        $this->connection->insert('blob_table', [
            'id' => 1,
            'clobcolumn' => 'test',
            'blobcolumn' => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', ['blobcolumn' => 'test2'], ['id' => 1], [
            ParameterType::LARGE_OBJECT,
            ParameterType::INTEGER,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testUpdateProcessesStream(): void
    {
        // https://github.com/doctrine/dbal/issues/3290
        if ($this->connection->getDriver() instanceof OCI8Driver) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'ignored',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', [
            'id'          => 1,
            'blobcolumn'   => fopen('data://text/plain,test2', 'r'),
        ], ['id' => 1], [
            ParameterType::INTEGER,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testBindParamProcessesStream(): void
    {
        if ($this->connection->getDriver() instanceof OCI8Driver) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO blob_table(id, clobcolumn, blobcolumn) VALUES (1, 'ignored', ?)"
        );

        $stream = null;
        $stmt->bindParam(1, $stream, ParameterType::LARGE_OBJECT);

        // Bind param does late binding (bind by reference), so create the stream only now:
        $stream = fopen('data://text/plain,test', 'r');

        $stmt->execute();

        $this->assertBlobContains('test');
    }

    private function assertBlobContains(string $text): void
    {
        $rows = $this->connection->fetchFirstColumn('SELECT blobcolumn FROM blob_table');

        self::assertCount(1, $rows);

        $blobValue = Type::getType('blob')->convertToPHPValue($rows[0], $this->connection->getDatabasePlatform());

        self::assertIsResource($blobValue);
        self::assertEquals($text, stream_get_contents($blobValue));
    }
}
