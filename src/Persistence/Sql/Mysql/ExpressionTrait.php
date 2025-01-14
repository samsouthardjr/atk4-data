<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

trait ExpressionTrait
{
    protected function hasNativeNamedParamSupport(): bool
    {
        $dbalConnection = $this->connection->connection();

        return !$dbalConnection->getNativeConnection() instanceof \mysqli;
    }
}
