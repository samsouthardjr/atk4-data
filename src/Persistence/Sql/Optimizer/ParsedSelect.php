<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Optimizer;

use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;

class ParsedSelect implements \Atk4\Data\Persistence\Sql\Expressionable // remove Expressionable later
{
    /** @var Query|string */
    public $expr;
    /** @var string|null */
    public $tableAlias;

    /**
     * @param Query|string $expr
     */
    public function __construct($expr, ?string $tableAlias)
    {
        $exprIdentifier = Util::tryParseIdentifier($expr);
        if ($exprIdentifier !== false) {
            $this->expr = Util::parseSingleIdentifier($expr);
        } else {
            $this->expr = $expr;
        }

        $this->tableAlias = $tableAlias !== null ? Util::parseSingleIdentifier($tableAlias) : null;
    }

    public function getDsqlExpression(Expression $expression): Expression
    {
        return new Expression('{}', [$this->expr]);
    }
}
