<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SARL (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SARL (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.0.0
 */

namespace App\Model\Table;

use App\Model\Entity\Permission;
use App\Model\Rule\IsNotSoftDeletedRule;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Validation\Validation;
use Cake\Validation\Validator;

/**
 * Permissions Model
 *
 * @property \App\Model\Table\SecretsTable|\Cake\ORM\Association\BelongsTo $Groups
 * @property \App\Model\Table\SecretsTable|\Cake\ORM\Association\BelongsTo $Resources
 * @property \App\Model\Table\SecretsTable|\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\Permission get($primaryKey, $options = [])
 * @method \App\Model\Entity\Permission newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Permission[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Permission|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Permission patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Permission[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Permission findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PermissionsTable extends Table
{

    /**
     * List of allowed aco models on which Permissions can be plugged.
     */
    const ALLOWED_ACOS = [
        'Resource'
    ];

    /**
     * List of allowed aro models on which Permissions can be plugged.
     */
    const ALLOWED_AROS = [
        'Group',
        'User',
    ];

    /**
     * List of allowed permission types.
     */
    const ALLOWED_TYPES = [
        Permission::READ,
        Permission::UPDATE,
        Permission::OWNER,
    ];

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('permissions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Groups', [
            'foreignKey' => 'aro_foreign_key'
        ]);
        $this->belongsTo('Resources', [
            'foreignKey' => 'aco_foreign_key'
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'aro_foreign_key'
        ]);

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->uuid('id')
            ->allowEmpty('id', 'create');

        $validator
            ->inList('aco', self::ALLOWED_ACOS)
            ->requirePresence('aco', 'create')
            ->notEmpty('aco');

        $validator
            ->uuid('aco_foreign_key')
            ->requirePresence('aco_foreign_key', 'create')
            ->notEmpty('aco_foreign_key');

        $validator
            ->inList('aro', self::ALLOWED_AROS)
            ->requirePresence('aro', 'create')
            ->notEmpty('aro');

        $validator
            ->uuid('aro_foreign_key')
            ->requirePresence('aro_foreign_key', 'create')
            ->notEmpty('aro_foreign_key');

        $validator
            ->inList('type', self::ALLOWED_TYPES)
            ->requirePresence('type', 'create')
            ->notEmpty('type');

        return $validator;
    }

    /**
     * Create resource validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationSaveResource(Validator $validator)
    {
        $validator = $this->validationDefault($validator);

        // The resource_id is added by cake after the resource is created.
        $validator->remove('aco_foreign_key');

        return $validator;
    }

    /**
     * Custom validation rule to validate permission type
     *
     * @param int $value permission type
     * @return bool
     */
    public function isValidPermissionType($value)
    {
        $permissionTypes = [
            Permission::READ,
            Permission::UPDATE,
            Permission::OWNER
        ];

        return is_int($value) && in_array($value, $permissionTypes);
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->addCreate(
            $rules->isUnique(
                ['aco_foreign_key', 'aro_foreign_key'],
                __('A permission already exists for the given aco and aro.')
            ),
            'permission_unique'
        );
        $rules->addCreate([$this, 'acoExistsRule'], 'aco_exists', [
            'errorField' => 'aco_foreign_key',
            'message' => __('The aco does not exist.')
        ]);
        $rules->addCreate([$this, 'aroExistsRule'], 'aro_exists', [
            'errorField' => 'aro_foreign_key',
            'message' => __('The aro does not exist.')
        ]);

        return $rules;
    }

    /**
     * Checks that the aco exists
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to test
     * @param array $options The additional options for this rule
     * @return bool
     */
    public function acoExistsRule($entity, $options)
    {
        $rules = new RulesChecker($options);
        if ($entity->aco == 'Resource') {
            $rule = $rules->existsIn('aco_foreign_key', 'Resources');
            $existIn = $rule($entity, $options);
            $rule = new IsNotSoftDeletedRule();
            $isNotSoftDeleted = $rule($entity, [
                'table' => 'Resources',
                'errorField' => 'aco_foreign_key',
            ]);

            return $existIn && $isNotSoftDeleted;
        }

        return false;
    }

    /**
     * Checks that the aro exists
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to test
     * @param array $options The additional options for this rule
     * @return bool
     */
    public function aroExistsRule($entity, $options)
    {
        $rules = new RulesChecker($options);
        $aro = Inflector::pluralize($entity->aro);
        if (in_array($aro, ['Users', 'Groups'])) {
            $existRule = $rules->existsIn('aro_foreign_key', $aro);
            $existIn = $existRule($entity, $options);
            $isNotSoftDeletedRule = new IsNotSoftDeletedRule();
            $isNotSoftDeleted = $isNotSoftDeletedRule($entity, [
                'table' => $aro,
                'errorField' => 'aro_foreign_key',
            ]);

            return $existIn && $isNotSoftDeleted;
        }

        return false;
    }

    /**
     * Build the query that fetches data for aco permissions view
     *
     * @param string $acoForeignKey The aco instance id to retrieve to get the permissions for
     * @param array $options options
     * @throws \InvalidArgumentException if the userId parameter is not a valid uuid.
     * @throws \InvalidArgumentException if the resourceId parameter is not a valid uuid.
     * @return \Cake\ORM\Query
     */
    public function findViewAcoPermissions($acoForeignKey, $options = [])
    {
        if (!Validation::uuid($acoForeignKey)) {
            throw new \InvalidArgumentException(__('The parameter acoForeignKey should be a valid uuid.'));
        }

        $query = $this->find()
            ->where(['Permissions.aco_foreign_key' => $acoForeignKey]);

        // If contains group.
        if (isset($options['contain']['group'])) {
            $query->contain('Groups');
        }

        // If contains user.
        if (isset($options['contain']['user'])) {
            $query->contain('Users');
        }

        // If contains user profile.
        if (isset($options['contain']['user.profile'])) {
            $query->contain('Users.Profiles');
            // @TODO when Avatars model is implemented.
            // ->contain('Users.Profiles.Avatars');
        }

        return $query;
    }
}