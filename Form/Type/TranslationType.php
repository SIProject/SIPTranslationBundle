<?php
/*
 * (c) Suhinin Ilja <iljasuhinin@gmail.com>
 */
namespace SIP\TranslationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;

use SIP\TranslationBundle\Form\DataTransformer\FieldToTranslationTransformer;
use SIP\TranslationBundle\EventListener\TranslationListener;

use Doctrine\ORM\EntityManager;

class TranslationType extends AbstractType
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \SIP\TranslationBundle\EventListener\TranslationListener
     */
    protected $translationListener;

    /**
     * @param \SIP\TranslationBundle\EventListener\TranslationListener $translationListener
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function __construct(TranslationListener $translationListener, EntityManager $em)
    {
        $this->translationListener = $translationListener;
        $this->em = $em;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (isset($options['sonata_field_description'])) {
            /** @var \Sonata\DoctrineORMAdminBundle\Admin\FieldDescription $sonataFieldDescription */
            $sonataFieldDescription = $options['sonata_field_description'];
            if (!$options['parent_data'])
                $options['parent_data'] = $sonataFieldDescription->getAdmin()->getSubject();
            if (!$options['field_name'])
                $options['field_name'] = $sonataFieldDescription->getFieldName();
        }

        $transformer = new FieldToTranslationTransformer($this->em, $this->translationListener, $options['parent_data'], $options['field_name']);
        $builder->addModelTransformer($transformer);

        $builder->add($this->translationListener->getDefaultLocale(), $options['type'], array_merge($options['field_options'], array('required' => $options['required'])));
        foreach ($this->translationListener->getLocales() as $allowLang) {
            $builder->add($allowLang, $options['type'], array_merge($options['field_options'], array('required' => false)));
        }

    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $options = array(
            'compound'      => true,
            'type'          => null,
            'field_options' => array(),
            'parent_data'   => null,
            'field_name'    => null
        );

        $resolver->setDefaults($options);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'translation';
    }
}