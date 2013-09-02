<?php
/*
 * (c) Suhinin Ilja <isuhinin@armd.ru>
 */
namespace Armd\TranslationBundle\Query;

use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\UpdateStatement;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

use Armd\TranslationBundle\EventListener\TranslationListener;

class TranslationTreeWalker extends SqlWalker
{
    protected $translationListener;

    /**
     * {@inheritdoc}
     */
    public function __construct($query, $parserResult, array $queryComponents)
    {
        $query = $this->getDecorateQuery($query);
        parent::__construct($query, $parserResult, $queryComponents);
    }

    /**
     * @param \Doctrine\ORM\Query $query
     * @return \Doctrine\ORM\Query
     */
    public function getDecorateQuery(Query $query)
    {
        $AST = $query->getAST();
        if ($AST instanceof SelectStatement) {
            $query = $this->getSelectStamentQuery($AST, $query);
        }

        if ($AST instanceof UpdateStatement) {
            $query = $this->getUpdateStamentQuery($AST, $query);
        }

        if ($AST instanceof DeleteStatement) {
            $query = $this->getDeleteStamentQuery($AST, $query);
        }

        return $query;
    }

    /**
     * @param \Doctrine\ORM\Query\AST\SelectStatement $AST
     * @param \Doctrine\ORM\Query $query
     */
    public function getSelectStamentQuery(SelectStatement $AST, Query $query)
    {
        foreach ($AST->fromClause->identificationVariableDeclarations as $declarations) {
            $classMetaData = $this->getClassMetaData($declarations->rangeVariableDeclaration->abstractSchemaName, $query);
            $query = $this->getQueryDecorateTranslationFields($classMetaData, $query);
        }

        return $query;
    }

    /**
     * @param \Doctrine\ORM\Query\AST\UpdateStatement $AST
     * @param \Doctrine\ORM\Query $query
     * @return \Doctrine\ORM\Query
     */
    public function getUpdateStamentQuery(UpdateStatement $AST, Query $query)
    {
        $classMetaData = $this->getClassMetaData($AST->updateClause->abstractSchemaName, $query);
        return $this->getQueryDecorateTranslationFields($classMetaData, $query);
    }

    /**
     * @param \Doctrine\ORM\Query\AST\DeleteStatement $AST
     * @param \Doctrine\ORM\Query $query
     * @return \Doctrine\ORM\Query
     */
    public function getDeleteStamentQuery(DeleteStatement $AST, Query $query)
    {
        $classMetaData = $this->getClassMetaData($AST->deleteClause->abstractSchemaName, $query);
        return $this->getQueryDecorateTranslationFields($classMetaData, $query);
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetaData
     * @param \Doctrine\ORM\Query $query
     * @return \Doctrine\ORM\Query
     */
    public function getQueryDecorateTranslationFields(ClassMetadata $classMetaData, Query $query)
    {
        if (!$this->getTranslationListener($query)->needTranslate()) {
            return $query;
        }

        foreach ($classMetaData->getAssociationNames() as $association) {
            $associationMetaData = $this->getClassMetaData($classMetaData->getAssociationTargetClass($association),
                $query);
            $this->decorateTableFields($associationMetaData, $query);
        }
        $this->decorateTableFields($classMetaData, $query);

        return $query;
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata $classMetaData
     * @param \Doctrine\ORM\Query $query
     */
    public function decorateTableFields(ClassMetadata $classMetaData, Query $query)
    {
        $locale = $query->getHint(TranslationListener::LOCALE_HINT)?
            $query->getHint(TranslationListener::LOCALE_HINT):
            $this->getTranslationListener($query)->getCurrentLocale();

        if ($decorateFields = $this->getTranslationListener($query)->getDecoratedTable($classMetaData->table['name'])) {
            foreach ($classMetaData->fieldMappings as $key => $filed) {
                foreach ($decorateFields as $decorateField) {
                    if ($filed['fieldName'] == $decorateField['fieldName']) {
                        $classMetaData->fieldMappings[$key]['columnName'] = "{$filed['columnName']}_{$locale}";
                    }
                }
            }
        }
    }

    /**
     * @param $className
     * @return \Doctrine\ORM\Mapping\ClassMetadata
     */
    public function getClassMetaData($className, Query $query)
    {
        return $query->getEntityManager()->getClassMetadata($className);
    }

    /**
     * Get the currently used TranslatableListener
     *
     * @throws \Gedmo\Exception\RuntimeException - if listener is not found
     * @return \Armd\TranslationBundle\EventListener\TranslationListener
     */
    private function getTranslationListener(\Doctrine\ORM\Query $query)
    {
        if (!$this->translationListener) {
            foreach ($query->getEntityManager()->getEventManager()->getListeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof TranslationListener) {
                        $this->translationListener = $listener;
                        break;
                    }
                }
                if ($this->translationListener) {
                    break;
                }
            }

            if (is_null($this->translationListener)) {
                throw new \RuntimeException('The translation listener could not be found');
            }
        }
        return $this->translationListener;
    }
}