<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Platforms\OraclePlatform;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Query
 */
class ConnectionTest extends TestCase
{
    public function testServerConnection(): void
    {
        $c = Connection::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);

        $this->assertSame('1', $c->expr('SELECT 1' . ($c->getDatabasePlatform() instanceof OraclePlatform ? ' FROM DUAL' : ''))->getOne());
    }
}
