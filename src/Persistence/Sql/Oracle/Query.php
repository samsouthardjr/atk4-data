<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected $paramBase = 'xxaaaa';

    protected $expression_class = Expression::class;

    public function render(): array
    {
        if ($this->mode === 'select' && count($this->args['table'] ?? []) === 0) {
            try {
                $this->table('DUAL');

                return parent::render();
            } finally {
                unset($this->args['table']);
            }
        }

        return parent::render();
    }

    protected function _sub_render_condition(array $row): string
    {
        if (count($row) === 2) {
            [$field, $value] = $row;
            $cond = '=';
        } elseif (count($row) >= 3) {
            [$field, $cond, $value] = $row;
        } else {
            // for phpstan only, remove else block once
            // https://github.com/phpstan/phpstan/issues/6017 is fixed
            $field = null;
            $cond = null;
            $value = null;
        }

        if (count($row) >= 2 && $field instanceof Field
            && in_array($field->getTypeObject()->getName(), ['text', 'blob'], true)) {
            if ($field->getTypeObject()->getName() === 'text') {
                $field = $this->expr('LOWER([])', [$field]);
                $value = $this->expr('LOWER([])', [$value]);
            }

            if (in_array($cond, ['=', '!=', '<>'], true)) {
                $row = [$this->expr('dbms_lob.compare([], [])', [$field, $value]), $cond, 0];
            } else {
                throw (new Exception('Unsupported CLOB/BLOB field operator'))
                    ->addMoreInfo('operator', $cond)
                    ->addMoreInfo('type', $field->type);
            }
        }

        return parent::_sub_render_condition($row);
    }

    public function _render_limit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        return ($shift ? ' offset ' . $shift . ' rows' : '')
            . ($cnt ? ' fetch next ' . $cnt . ' rows only' : '');
    }

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $delimiter]);
    }

    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
