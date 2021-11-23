<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;

class HasOneSql extends HasOne
{
    /**
     * WARNING: returned model can have invalid reference (guarded) values.
     *
     * @return mixed
     */
    protected function guardOwnerSeedRefRecursion(\Closure $fx)
    {
        $ownerElementsBackup = $this->getOwner()->getModel(true)->elements;
        try {
            $ownerCloned = null;
            foreach ($ownerElementsBackup as $k => $v) {
                if (str_starts_with($k, '#ref_') && is_array($v->model) && $v->model[0] === get_class($this->getOwner())) {
                    if ($ownerCloned === null) {
                        $ownerCloned = clone $this->getOwner();
                    }

                    $refCloned = clone $v;
                    $refCloned->unsetOwner();
                    $refCloned->setOwner($ownerCloned);
                    $refCloned->model = $ownerCloned;

                    $this->getOwner()->getModel(true)->elements[$k] = $refCloned;
                }
            }

            return $fx();
        } finally {
            $this->getOwner()->getModel(true)->elements = $ownerElementsBackup;
        }
    }

    /**
     * Creates expression which sub-selects a field inside related model.
     */
    public function addField(string $fieldName, string $theirFieldName = null, array $defaults = []): SqlExpressionField
    {
        if ($theirFieldName === null) {
            $theirFieldName = $fieldName;
        }

        $ourModel = $this->getOurModel(null);

        // if caption/type is not defined in $defaults -> get it directly from the linked model field $theirFieldName
        $theirFieldGuarded = $this->guardOwnerSeedRefRecursion(fn () => $ourModel->refModel($this->link))->getField($theirFieldName);
        $defaults['type'] ??= $theirFieldGuarded->type;
        $defaults['enum'] ??= $theirFieldGuarded->enum;
        $defaults['values'] ??= $theirFieldGuarded->values;
        $defaults['caption'] ??= $theirFieldGuarded->caption;
        $defaults['ui'] ??= $theirFieldGuarded->ui;

        $fieldExpression = $ourModel->addExpression($fieldName, array_merge(
            [
                function (Model $ourModel) use ($theirFieldName) {
                    $theirModel = $ourModel->refLink($this->link);

                    // remove order if we just select one field from hasOne model
                    // that is mandatory for Oracle
                    return $theirModel->action('field', [$theirFieldName])->reset('order');
                },
            ],
            $defaults,
            [
                // to be able to change field, but not save it
                // afterSave hook will take care of the rest
                'read_only' => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($fieldName, $theirFieldName) {
            // if field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($fieldName) && !$ourModel->isDirty($this->our_field)) {
                $theirModel = $this->createTheirModel();

                $theirModel->addCondition($theirFieldName, $ourModel->get($fieldName));
                $ourModel->set($this->getOurFieldName(), $theirModel->action('field', [$theirModel->id_field]));
                $ourModel->_unset($fieldName);
            }
        }, [], 20);

        return $fieldExpression;
    }

    /**
     * Add multiple expressions by calling addField several times. Fields
     * may contain 3 types of elements:.
     *
     * [ 'name', 'surname' ] - will import those fields as-is
     * [ 'full_name' => 'name', 'day_of_birth' => ['dob', 'type' => 'date'] ] - use alias and options
     * [ ['dob', 'type' => 'date'] ]  - use options
     *
     * You may also use second param to specify parameters:
     *
     * addFields(['from', 'to'], ['type' => 'date']);
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $ourFieldName => $ourFieldDefaults) {
            $ourFieldDefaults = array_merge($defaults, (array) $ourFieldDefaults);

            $theirFieldName = $ourFieldDefaults[0] ?? null;
            unset($ourFieldDefaults[0]);
            if (is_int($ourFieldName)) {
                $ourFieldName = $theirFieldName;
            }

            $this->addField($ourFieldName, $theirFieldName, $ourFieldDefaults);
        }

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        $theirModel->addCondition(
            $this->their_field ?: $theirModel->id_field,
            $this->referenceOurValue()
        );

        return $theirModel;
    }

    /**
     * Navigate to referenced model.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = parent::ref($ourModel, $defaults);
        $ourModel = $this->getOurModel($ourModel);

        $theirFieldName = $this->their_field ?? $theirModel->id_field; // TODO why not $this->getTheirFieldName() ?

        // At this point the reference
        // if our_field is the id_field and is being used in the reference
        // we should persist the relation in condtition
        // example - $model->load(1)->ref('refLink')->import($rows);
        if ($ourModel->isEntity() && $ourModel->isLoaded() && !$theirModel->isLoaded()) {
            if ($ourModel->id_field === $this->getOurFieldName()) {
                return $theirModel->getModel()
                    ->addCondition($theirFieldName, $this->getOurFieldValue($ourModel));
            }
        }

        // handles the deep traversal using an expression
        $ourFieldExpression = $ourModel->action('field', [$this->getOurField()]);

        $theirModel->getModel(true)
            ->addCondition($theirFieldName, $ourFieldExpression);

        return $theirModel;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * This method returns newly created expression field.
     */
    public function addTitle(array $defaults = []): SqlExpressionField
    {
        $ourModel = $this->getOurModel(null);

        $ourFieldName = $defaults['field'] ?? preg_replace('~_(' . preg_quote($ourModel->id_field, '~') . '|id)$~', '', $this->link);

        $theirFieldGuarded = $this->guardOwnerSeedRefRecursion(fn () => $ourModel->refModel($this->link));
        $theirFieldName = $theirFieldGuarded->title_field;

        $defaults['ui'] = array_merge(['editable' => false, 'visible' => true], $defaults['ui'] ?? []);

        $field = $this->addField($ourFieldName, $theirFieldName, $defaults);

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->getOurField()->ui)) {
            $this->getOurField()->ui['visible'] = false;
        }

        return $field;
    }
}
