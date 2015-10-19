<?php
/*
 * (c) Suhinin Ilja <iljasuhinin@gmail.com>
 */
namespace SIP\TranslationBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;

use SIP\TranslationBundle\EventListener\TranslationListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;

class FieldToTranslationTransformer implements DataTransformerInterface
{
    /**
     * @var EntityManager
     */
    protected $em;

    protected $data;

    protected $fieldName;

    /**
     * @var \SIP\TranslationBundle\EventListener\TranslationListener
     */
    protected $translationListener;

    /**
     * @param \Doctrine\ORM\EntityManager $em
     * @param \SIP\TranslationBundle\EventListener\TranslationListener $translationListener
     * @param $data
     * @param $fieldName
     * @internal param $dafaultLang
     * @internal param $allowLangs
     */
    public function __construct(EntityManager $em, TranslationListener $translationListener, $data, $fieldName)
    {
        $this->translationListener = $translationListener;
        $this->em                  = $em;
        $this->data                = $data;
        $this->fieldName           = $fieldName;
    }

    /**
     * Transforms an object (issue) to a string (number).
     *
     * @param  Issue|null $issue
     * @return string
     */
    public function transform($defaultLangValue)
    {
        if (is_array($defaultLangValue)) {
            return $defaultLangValue;
        }

        $subject = $this->data;
        if ($subject && $subject->getId()) {
            $classMetaData = $this->em->getClassMetadata(get_class($subject));

            $tableName = $classMetaData->getTableName();
            $fieldName = $this->fieldName;

            if (!$this->checkColumn($tableName, $fieldName)) {
                throw new \LogicException('Field is not translatable!');
            }

            $columnName = $classMetaData->getColumnName($fieldName);
            $columns = array();
            foreach ($this->translationListener->getLocales() as $allowLang) {
                $columns[] = "c.{$columnName}_{$allowLang}";
            }

            $select = join(', ', $columns);
            $result = $this->em->getConnection()->fetchArray("SELECT {$select} FROM {$tableName} c WHERE c.id = {$subject->getId()}");

            $translations[$this->translationListener->getDefaultLocale()] = $defaultLangValue;

            foreach ($this->translationListener->getLocales() as $key => $allowLang) {
                $translations[$allowLang] = $result[$key];
            }

            return $translations;
        }

        return $defaultLangValue;
    }

    /**
     * @param mixed $translationValue
     * @return mixed
     */
    public function reverseTransform($translationValue)
    {
        $defaultLang = $translationValue[$this->translationListener->getDefaultLocale()];
        unset($translationValue[$this->translationListener->getDefaultLocale()]);

        if ($subject = $this->data) {
            $classMetaData = $this->em->getClassMetadata(get_class($subject));
            $columnName = $classMetaData->getColumnName($this->fieldName);
            $this->translationListener->addTranslationValue(get_class($subject), $columnName, $translationValue);

            if ($subject->getId()) {
                $this->translationListener->setTranslateSubject($subject);
            }
        }

        return $defaultLang;
    }

    /**
     * @param $tableName
     * @param $fieldName
     * @return bool
     * @throws \LogicException
     */
    public function checkColumn($tableName, $fieldName)
    {
        if ($decorateTable = $this->translationListener->getDecoratedTable($tableName)) {
            foreach ($decorateTable as $translateField) {
                if (isset($translateField['fieldName']) && ($translateField['fieldName'] == $fieldName)) {
                    return true;
                }
            }
        }

        return false;
    }
}