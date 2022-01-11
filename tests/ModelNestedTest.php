<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\HookBreaker;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Result as DbalResult;

class ModelNestedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
            'user' => [
                ['_id' => 1, 'name' => 'John', '_birthday' => '1980-02-01'],
                ['_id' => 2, 'name' => 'Sue', '_birthday' => '2005-04-03'],
            ],
        ]);
    }

    /** @var array */
    public $hookLog = [];

    protected function createTestModel(): Model
    {
        $mWithLoggingClass = get_class(new class() extends Model {
            /** @var \WeakReference<ModelNestedTest> */
            protected $testCaseWeakRef;
            /** @var string */
            protected $testModelAlias;

            public function hook(string $spot, array $args = [], HookBreaker &$brokenBy = null)
            {
                if (!str_starts_with($spot, '__atk__method__') && $spot !== Model::HOOK_NORMALIZE) {
                    $convertValueToLogFx = function ($v) use (&$convertValueToLogFx) {
                        if (is_array($v)) {
                            return array_map($convertValueToLogFx, $v);
                        } elseif (is_scalar($v) || $v === null) {
                            return $v;
                        } elseif ($v instanceof self) {
                            return $this->testModelAlias;
                        }

                        $res = preg_replace('~(?<=^Atk4\\\\Data\\\\Persistence\\\\Sql\\\\)\w+\\\\(?=\w+$)~', '', get_debug_type($v));
                        if (Connection::isComposerDbal2x() && $res === 'Doctrine\DBAL\Statement') {
                            $res = DbalResult::class;
                        }

                        return $res;
                    };

                    $this->testCaseWeakRef->get()->hookLog[] = [$convertValueToLogFx($this), $spot, $convertValueToLogFx($args)];
                }

                return parent::hook($spot, $args, $brokenBy);
            }
        });

        $mInner = new $mWithLoggingClass($this->db, [
            'testCaseWeakRef' => \WeakReference::create($this),
            'testModelAlias' => 'inner',
            'table' => 'user',
        ]);
        $mInner->removeField('id');
        $mInner->id_field = 'uid';
        $mInner->addField('uid', ['actual' => '_id', 'type' => 'integer']);
        $mInner->addField('name');
        $mInner->addField('y', ['actual' => '_birthday', 'type' => 'date']);

        $m = new $mWithLoggingClass($this->db, [
            'testCaseWeakRef' => \WeakReference::create($this),
            'testModelAlias' => 'main',
            'table' => $mInner,
        ]);
        $m->removeField('id');
        $m->id_field = 'birthday';
        $m->addField('name');
        $m->addField('birthday', ['actual' => 'y', 'type' => 'date']);

        return $m;
    }

    public function testSelectSql(): void
    {
        $m = $this->createTestModel();
        $m->table->setOrder('name', 'desc');
        $m->table->setLimit(5);
        $m->setOrder('birthday');

        $this->assertSame(
            ($this->db->connection->dsql())
                ->table(
                    ($this->db->connection->dsql())
                        ->field('_id', 'uid')
                        ->field('name')
                        ->field('_birthday', 'y')
                        ->table('user')
                        ->order('name', true)
                        ->limit(5),
                    '_tm'
                )
                ->field('name')
                ->field('y', 'birthday')
                ->order('y')
                ->render()[0],
            $m->action('select')->render()[0]
        );

        $this->assertSame([
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
        ], $this->hookLog);
    }

    public function testSelectExport(): void
    {
        $m = $this->createTestModel();

        $this->assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3')],
        ], $m->export());

        $this->assertSame([
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
        ], $this->hookLog);
    }

    public function testInsert(): void
    {
        $m = $this->createTestModel();

        $entity = $m->createEntity()
            ->setMulti([
                'name' => 'Karl',
                'birthday' => new \DateTime('2000-6-1'),
            ])->save();

        $this->assertSame([
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [false]],
            ['main', Model::HOOK_BEFORE_INSERT, [['name' => 'Karl', 'birthday' => \DateTime::class]]],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [false]],
            ['inner', Model::HOOK_BEFORE_INSERT, [['uid' => null, 'name' => 'Karl', 'y' => \DateTime::class]]],
            ['inner', Persistence\Sql::HOOK_BEFORE_INSERT_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_INSERT_QUERY, [Query::class, DbalResult::class]],
            ['inner', Model::HOOK_AFTER_INSERT, []],
            ['inner', Model::HOOK_AFTER_SAVE, [false]],
            ['main', Model::HOOK_AFTER_INSERT, []],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],
            ['main', Model::HOOK_AFTER_SAVE, [false]],
        ], $this->hookLog);

        $this->assertSame(3, $m->table->loadBy('name', 'Karl')->getId());
        $this->assertSameExportUnordered([[new \DateTime('2000-6-1')]], [[$entity->getId()]]);

        $this->assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3')],
            ['name' => 'Karl', 'birthday' => new \DateTime('2000-6-1')],
        ], $m->export());
    }

    public function testUpdate(): void
    {
        $m = $this->createTestModel();

        $m->load(new \DateTime('2005-4-3'))
            ->setMulti([
                'name' => 'Susan',
            ])->save();

        $this->assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [true]],
            ['main', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [true]],
            ['inner', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Persistence\Sql::HOOK_BEFORE_UPDATE_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_UPDATE_QUERY, [Query::class, DbalResult::class]],
            ['inner', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_AFTER_SAVE, [true]],
            ['main', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],
            ['main', Model::HOOK_AFTER_SAVE, [true]],
        ], $this->hookLog);

        $this->assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Susan', 'birthday' => new \DateTime('2005-4-3')],
        ], $m->export());
    }

    public function testDelete(): void
    {
        $m = $this->createTestModel();

        $m->delete(new \DateTime('2005-4-3'));

        $this->assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Persistence\Sql::HOOK_BEFORE_DELETE_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_DELETE_QUERY, [Query::class, DbalResult::class]],
            ['inner', Model::HOOK_AFTER_DELETE, []],
            ['inner', Model::HOOK_BEFORE_UNLOAD, []],
            ['inner', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_AFTER_DELETE, []],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
        ], $this->hookLog);

        $this->assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
        ], $m->export());
    }
}
