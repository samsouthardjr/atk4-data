<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected $escape_char = ']';

    protected $expression_class = Expression::class;

    protected $template_insert = <<<'EOF'
        begin try
          insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
        end try begin catch
          if ERROR_NUMBER() = 544 begin
            set IDENTITY_INSERT [table_noalias] on;
            begin try
              insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
              set IDENTITY_INSERT [table_noalias] off;
            end try begin catch
              set IDENTITY_INSERT [table_noalias] off;
              throw;
            end catch
          end else begin
            throw;
          end
        end catch
        EOF;

    public function __construct($properties = [], $arguments = null)
    {
        // fix error throwing for MSSQL insert, see https://github.com/microsoft/msphpsql/issues/1387
        foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT) as $trace) {
            if (($trace['object'] ?? null) instanceof \PHPUnit\Framework\TestCase) {
                if (!in_array($trace['object']->toString(), ['Atk4\Data\Tests\ConditionSqlTest::testBasic', 'Atk4\Data\Tests\ConditionSqlTest::testEntityReloadWithDifferentIdException', 'Atk4\Data\Tests\ConditionSqlTest::testNull', 'Atk4\Data\Tests\ConditionSqlTest::testOperations', 'Atk4\Data\Tests\ConditionSqlTest::testExpressions1', 'Atk4\Data\Tests\ConditionSqlTest::testExpressions2', 'Atk4\Data\Tests\ConditionSqlTest::testExpressionJoin', 'Atk4\Data\Tests\ConditionSqlTest::testArrayCondition', 'Atk4\Data\Tests\ConditionSqlTest::testDateCondition', 'Atk4\Data\Tests\ConditionSqlTest::testDateCondition2', 'Atk4\Data\Tests\ConditionSqlTest::testDateConditionFailure', 'Atk4\Data\Tests\ConditionSqlTest::testOrConditions', 'Atk4\Data\Tests\ConditionSqlTest::testLoadBy', 'Atk4\Data\Tests\ConditionSqlTest::testLikeCondition', 'Atk4\Data\Tests\ContainsManyTest::testModelCaption', 'Atk4\Data\Tests\ContainsManyTest::testContainsMany', 'Atk4\Data\Tests\ContainsManyTest::testNestedContainsMany', 'Atk4\Data\Tests\ContainsOneTest::testModelCaption', 'Atk4\Data\Tests\ContainsOneTest::testContainsOne', 'Atk4\Data\Tests\ContainsOneTest::testContainsOneWhenChangeModelFields', 'Atk4\Data\Tests\ExpressionSqlTest::testExpressions', 'Atk4\Data\Tests\FieldTest::testMandatory2', 'Atk4\Data\Tests\FieldTest::testRequired2', 'Atk4\Data\Tests\FieldTest::testMandatory3', 'Atk4\Data\Tests\FieldTest::testMandatory4', 'Atk4\Data\Tests\FieldTest::testNeverPersist', 'Atk4\Data\Tests\FieldTest::testTitle', 'Atk4\Data\Tests\FieldTest::testActual', 'Atk4\Data\Tests\FieldTest::testCalculatedField', 'Atk4\Data\Tests\JoinSqlTest::testJoinLoading', 'Atk4\Data\Tests\JoinSqlTest::testJoinUpdate', 'Atk4\Data\Tests\JoinSqlTest::testJoinDelete', 'Atk4\Data\Tests\JoinSqlTest::testDoubleJoin', 'Atk4\Data\Tests\JoinSqlTest::testDoubleReverseJoin', 'Atk4\Data\Tests\JoinSqlTest::testJoinHasOneHasMany', 'Atk4\Data\Tests\JoinSqlTest::testJoinReverseOneOnOne', 'Atk4\Data\Tests\JoinSqlTest::testJoinActualFieldNamesAndPrefix', 'Atk4\Data\Tests\ModelNestedSqlTest::testSelectSql', 'Atk4\Data\Tests\ModelNestedSqlTest::testSelectExport', 'Atk4\Data\Tests\ModelNestedSqlTest::testInsert', 'Atk4\Data\Tests\ModelNestedSqlTest::testUpdate', 'Atk4\Data\Tests\ModelNestedSqlTest::testDelete', 'Atk4\Data\Tests\ModelWithoutIdTest::testBasic', 'Atk4\Data\Tests\ModelWithoutIdTest::testGetIdException', 'Atk4\Data\Tests\ModelWithoutIdTest::testSetIdException', 'Atk4\Data\Tests\ModelWithoutIdTest::testFail1', 'Atk4\Data\Tests\ModelWithoutIdTest::testInsert', 'Atk4\Data\Tests\ModelWithoutIdTest::testSave1', 'Atk4\Data\Tests\ModelWithoutIdTest::testSave2', 'Atk4\Data\Tests\ModelWithoutIdTest::testLoadBy', 'Atk4\Data\Tests\ModelWithoutIdTest::testLoadCondition', 'Atk4\Data\Tests\ModelWithoutIdTest::testFailDelete1', 'Atk4\Data\Tests\Persistence\SqlTest::testLoadArray', 'Atk4\Data\Tests\Persistence\SqlTest::testModelLoadOneAndAny', 'Atk4\Data\Tests\Persistence\SqlTest::testPersistenceInsert', 'Atk4\Data\Tests\Persistence\SqlTest::testModelInsert', 'Atk4\Data\Tests\Persistence\SqlTest::testModelSaveNoReload', 'Atk4\Data\Tests\Persistence\SqlTest::testPersistenceDelete', 'Atk4\Data\Tests\Persistence\SqlTest::testExport', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testBasicQueries', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testExpression', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testOtherQueries', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testEmptyGetOne', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testWhereExpression', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testExecuteException', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testUtf8mb4Support', 'Atk4\Data\Tests\Persistence\Sql\WithDb\SelectTest::testImportAndAutoincrement', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testCommitException1', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testCommitException2', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testRollbackException1', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testRollbackException2', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testTransactions', 'Atk4\Data\Tests\Persistence\Sql\WithDb\TransactionTest::testInTransaction', 'Atk4\Data\Tests\RandomTest::testAddFields', 'Atk4\Data\Tests\RandomTest::testAddFields2', 'Atk4\Data\Tests\RandomTest::testSameTable', 'Atk4\Data\Tests\RandomTest::testSameTable2', 'Atk4\Data\Tests\RandomTest::testSameTable3', 'Atk4\Data\Tests\RandomTest::testUpdateCondition', 'Atk4\Data\Tests\RandomTest::testGetTitle', 'Atk4\Data\Tests\RandomTest::testExport', 'Atk4\Data\Tests\ReadOnlyModeTest::testBasic', 'Atk4\Data\Tests\ReadOnlyModeTest::testLoad', 'Atk4\Data\Tests\ReadOnlyModeTest::testLoadSave', 'Atk4\Data\Tests\ReadOnlyModeTest::testInsert', 'Atk4\Data\Tests\ReadOnlyModeTest::testSave1', 'Atk4\Data\Tests\ReadOnlyModeTest::testLoadBy', 'Atk4\Data\Tests\ReadOnlyModeTest::testLoadCondition', 'Atk4\Data\Tests\ReadOnlyModeTest::testFailDelete1', 'Atk4\Data\Tests\ReferenceSqlTest::testBasic', 'Atk4\Data\Tests\ReferenceSqlTest::testBasicOne', 'Atk4\Data\Tests\ReferenceSqlTest::testAddOneField', 'Atk4\Data\Tests\ReferenceSqlTest::testRelatedExpression', 'Atk4\Data\Tests\ReferenceSqlTest::testAggregateHasMany', 'Atk4\Data\Tests\ReferenceSqlTest::testOtherAggregates', 'Atk4\Data\Tests\ReferenceSqlTest::testAddTitle', 'Atk4\Data\Tests\ReferenceSqlTest::testHasOneTitleSet', 'Atk4\Data\Tests\ReferenceSqlTest::testHasOneReferenceCaption', 'Atk4\Data\Tests\ReferenceSqlTest::testHasOneReferenceType', 'Atk4\Data\Tests\Schema\ModelTest::testMigrateTable', 'Atk4\Data\Tests\Schema\TestCaseTest::testInit', 'Atk4\Data\Tests\TypecastingTest::testEmptyValues', 'Atk4\Data\Tests\TypecastingTest::testTypecastNull', 'Atk4\Data\Tests\WithTest::testWith'], true)) {
                    $this->template_insert = 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])';
                }

                break;
            }
        }

        parent::__construct($properties, $arguments);
    }

    public function _render_limit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        return (!isset($this->args['order']) ? ' order by (select null)' : '')
            . ' offset ' . $shift . ' rows'
            . ' fetch next ' . $cnt . ' rows only';
    }

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('string_agg({}, \'' . $delimiter . '\')', [$field]);
    }

    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
