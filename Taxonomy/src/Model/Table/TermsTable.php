<?php

namespace Croogo\Taxonomy\Model\Table;

use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;
use Croogo\Core\Model\Table\CroogoTable;

/**
 * Term
 *
 * @property VocabulariesTable Vocabularies
 * @property TaxonomiesTable Taxonomies
 * @category Taxonomy.Model
 * @package  Croogo.Taxonomy.Model
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class TermsTable extends CroogoTable
{

/**
 * Validation
 *
 * @var array
 * @access public
 */
    public $validate = [
        'slug' => [
            'isUnique' => [
                'rule' => 'isUnique',
                'message' => 'This slug has already been taken.',
            ],
            'minLength' => [
                'rule' => ['minLength', 1],
                'message' => 'Slug cannot be empty.',
            ],
        ],
    ];

    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'updated' => 'always'
                ]
            ]
        ]);
        $this->addBehavior('Croogo/Core.Trackable');

        $this->belongsToMany('Croogo/Taxonomy.Vocabularies', [
            'through' => 'Croogo/Taxonomy.Taxonomies',
            'foreignKey' => 'term_id',
            'targetForeignKey' => 'vocabulary_id',
        ]);
        $this->hasMany('Croogo/Taxonomy.Taxonomies');
    }

    /**
     * @param \Cake\Validation\Validator $validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator->notBlank('title', __d('croogo', 'The title cannot be empty'))
            ->notBlank('slug', __d('croogo', 'The slug cannot be empty'));

        return parent::validationDefault($validator);
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->isUnique(['alias'], __d('croogo', 'That slug is already taken'));

        return parent::buildRules($rules);
    }

    /**
 * Save Term and return ID.
 * If another Term with same slug exists, return ID of that Term without saving.
 *
 * @param  Entity $entity
 * @return integer
 */
    public function saveAndGetId(Entity $entity)
    {
        $term = $this->find()->where([
            'slug' => $entity->slug,
        ])->first();
        if ($term) {
            $id = $term->{$this->primaryKey()};
            if ($id && $entity->dirty('description')) {
                $this->id = $id;
                $this->save($entity);
            }
            return $id;
        }

        $savedEntity = $this->save($entity);
        if ($savedEntity) {
            return $savedEntity->id;
        }

        return false;
    }

/**
 * Allow delete on whether given Term has any association left with Taxonomy
 *
 * @return bool
 */
    public function beforeDelete($cascade = true)
    {
        $Taxonomy = ClassRegistry::init('Taxonomy.Taxonomy');
        $count = $Taxonomy->find('count', [
            'recursive' => -1,
            'conditions' => [
                $Taxonomy->escapeField('term_id') => $this->id,
            ],
        ]);
        return $count === 0;
    }

/**
 * Convenience method to check whether term exists within a vocabulary
 *
 * @param int$idTerm Id
 * @param int$vocabularyIdVocabulary Id
 * @param int$taxonomyIdTaxonomy Id
 * @return bool True if Term exists in Vocabulary
 */
    public function isInVocabulary($id, $vocabularyId, $taxonomyId = null)
    {
        $conditions = ['term_id' => $id, 'vocabulary_id' => $vocabularyId];
        if (!is_null($taxonomyId)) {
            $conditions['Taxonomies.id !='] = $taxonomyId;
        }
        return (bool)$this->Vocabularies->Taxonomies->find('all')->where($conditions)->count();
    }

/**
 * Save term
 *
 * @see Term::_save()
 * @return array|bool Array of saved term or boolean false
 */
    public function add($data, $vocabularyId)
    {
        return $this->_save($data, $vocabularyId);
    }

/**
 * Edit term
 *
 * @see Term::_save()
 * @return array|bool Array of saved term or boolean false
 */
    public function edit(Entity $entity, $vocabularyId)
    {
        if ($entity->dirty('slug') && $this->slugExists($entity->slug)) {
            $edited = false;
        } else {
            $taxonomyId = $entity->taxonomies[0]->id;
            $edited = $this->_save($entity, $vocabularyId, $taxonomyId);
        }
        return $edited;
    }

/**
 * Convenience check for slug
 *
 * @return bool
 */
    public function slugExists($slug)
    {
        return $this->hasAny(compact('slug'));
    }

/**
 * Remove term
 *
 * @param int$idTerm Id
 * @param int$vocabularyIdVocabulary Id
 */
    public function remove($id, $vocabularyId)
    {
        $taxonomyId = $this->Vocabulary->Taxonomy->field('id', [
            'term_id' => $id, 'vocabulary_id' => $vocabularyId
        ]);
        $this->setScopeForTaxonomy($vocabularyId);
        return $this->Taxonomy->delete($taxonomyId) && $this->delete($id);
    }

    public function findByVocabulary(Query $query, array $options)
    {
        if (empty($options['vocabulary_id'])) {
            trigger_error(__d('croogo', '"vocabulary_id" key not found'));
        }

        $vocabulary = $this->Vocabularies->find()->select('alias')->where(['id' => $options['vocabulary_id']])->first();

        $termsId = $this->Vocabularies->Taxonomies->getTree($vocabulary->alias, [
            'key' => 'id', 'value' => 'title'
        ]);

        if (empty($termsId)) {
            $query->where([
                '1 = 0'
            ]);
        } else {
            $query->where([
                $this->primaryKey() . ' IN' => array_keys($termsId)
            ]);
        }

        return $query;
    }

/**
 * Save new/updated term data
 *
 * @param Entity $entity Term \\
 * @param int$vocabularyIdVocabulary Id
 * @param int$taxonomyIdTaxonomy Id
 */
    protected function _save(Entity $entity, $vocabularyId, $taxonomyId = null)
    {
        $added = false;

        $termId = $this->saveAndGetId($entity);
        if (!$this->isInVocabulary($termId, $vocabularyId, $taxonomyId)) {
            $this->setScopeForTaxonomy($vocabularyId);
            $dataToPersist = (!is_null($taxonomyId)) ? $this->Taxonomies->get($taxonomyId) : $this->Taxonomies->newEntity();

            $dataToPersist = $this->Taxonomies->patchEntity($dataToPersist, [
                'parent_id' => $entity->parent_id,
                'term_id' => $termId,
                'vocabulary_id' => $vocabularyId,
            ]);

            $added = $this->Taxonomies->save($dataToPersist);
        }
        return $added;
    }

/**
 * Set Scope
 *
 * @param int$vocabularyIdVocabulary Id
 */
    public function setScopeForTaxonomy($vocabularyId)
    {
        $scopeSettings = ['scope' => [
            'Taxonomies.vocabulary_id' => $vocabularyId,
        ]];
        if ($this->Vocabularies->Taxonomies->hasBehavior('Tree')) {
            $this->Vocabularies->Taxonomies->behaviors()->get('Tree')->config($scopeSettings);
        } else {
            $this->Vocabularies->Taxonomies->addBehavior('Tree', $scopeSettings);
        }
    }
}