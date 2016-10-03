<?php
/*
 * (c) Suhinin Ilja <iljasuhinin@gmail.com>
 */
namespace SIP\TranslationBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;

use Doctrine\Common\Annotations\Reader;

use Symfony\Component\DependencyInjection\ContainerInterface;

class TranslationListener
{
    const LOCALE_HINT = 'locale.hint';

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var array
     */
    private $decoratedTables = array();

    /**
     * @var \Doctrine\Common\Annotations\Reader
     */
    private $reader;

    /**
     * @var array
     */
    private $translationValue;

    private $translateSubject;

    /**
     * @param \Doctrine\Common\Annotations\Reader $reader
     * @param array $locales
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container     = $container;
        $this->locales       = $this->container->getParameter('sip.translation.allow_langs');
        $this->defaultLocale = $this->container->getParameter('sip.translation.dafault_lang');
        $this->reader        = $this->container->get('annotation_reader');
    }

    /**
     * @param \Doctrine\ORM\Event\LoadClassMetadataEventArgs $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflectionClass = $classMetadata->getReflectionClass();
        if ($reflectionClass && $this->reader->getClassAnnotation($reflectionClass, 'SIP\TranslationBundle\Annotation\Translatable')) {
            foreach ($classMetadata->fieldMappings as $filed) {
                $property = $classMetadata->getReflectionClass()->getProperty($filed['fieldName']);
                if ($this->reader->getPropertyAnnotation($property, 'SIP\TranslationBundle\Annotation\Translated')) {
                    $this->decoratedTables[$classMetadata->getTableName()][] = $filed;
                }
            }
        }
    }

    /**
     * @param \Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $eventArgs
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs)
    {
        foreach ($this->decoratedTables as $decorateTable => $columns) {
            if ($eventArgs->getSchema()->hasTable($decorateTable)) {
                $table = $eventArgs->getSchema()->getTable($decorateTable);

                foreach ($columns as $column) {
                    foreach ($this->locales as $locale) {
                        $options = $column;
                        unset($options['columnName']);
                        unset($options['type']);
                        $options['notnull'] = false;
                        $table->addColumn("{$column['columnName']}_{$locale}", $column['type'], $options);
                    }
                }
            }
        }
    }

    /**
     * @param \Doctrine\ORM\Event\LifecycleEventArgs $args
     */
    public function postPersist(\Doctrine\ORM\Event\LifecycleEventArgs $args)
    {
        if ($this->translationValue) {
            $entity = $args->getEntity();

            foreach ($this->translationValue as $className => $value) {
                if ($entity instanceof $className) {
                    $this->translateSubject = $entity;
                }
            }
        }
    }

    /**
     * @param \Doctrine\ORM\Event\PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if ($this->translateSubject) {
            $connection = $args->getEntityManager()->getConnection();
            $data = array();
            foreach ($this->translationValue[get_class($this->translateSubject)] as $translateColumnName => $translateColumn) {
                foreach ($translateColumn as $locale => $translateValue) {
                    $data["{$translateColumnName}_{$locale}"] = $translateValue;
                }
            }
            $tableName = $args->getEntityManager()->getClassMetadata(get_class($this->translateSubject))->getTableName();
            $connection->update($tableName, $data, array('id' => $this->translateSubject->getId()));
        }
    }

    /**
     * @return array
     */
    public function getDecoratedTables()
    {
        return $this->decoratedTables;
    }

    /**
     * @param $tableName
     * @return null
     */
    public function getDecoratedTable($tableName)
    {
        if (isset($this->decoratedTables[$tableName])) {
            return $this->decoratedTables[$tableName];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getLocales()
    {
        return $this->locales;
    }

    /**
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * @param array $translationValue
     */
    public function addTranslationValue($className, $column, $translationValue)
    {
        $this->translationValue[$className][$column] = $translationValue;
    }

    /**
     * @param $translateSubject
     */
    public function setTranslateSubject($translateSubject)
    {
        $this->translateSubject = $translateSubject;
    }

    /**
     * @return string
     */
    public function getCurrentLocale()
    {
        return $this->container->get('request')->getLocale();
    }

    /**
     * @return bool
     */
    public function needTranslate()
    {
        return !($this->container->get('request')->getLocale() == $this->defaultLocale);
    }
}