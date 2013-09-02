<?php
/*
 * (c) Suhinin Ilja <iljasuhinin@gmail.com>
 */
namespace Armd\TranslationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;

use Armd\TranslationBundle\Form\DataTransformer\FieldToTranslationTransformer;
use Armd\TranslationBundle\EventListener\TranslationListener;

use Doctrine\ORM\EntityManager;

class TranslationType extends AbstractType
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \Armd\TranslationBundle\EventListener\TranslationListener
     */
    protected $translationListener;

    /**
     * @param \Armd\TranslationBundle\EventListener\TranslationListener $translationListener
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
        /** @var \Sonata\DoctrineORMAdminBundle\Admin\FieldDescription $sonataFieldDescription */
        $sonataFieldDescription = $options['sonata_field_description'];

        $transformer = new FieldToTranslationTransformer($this->em, $this->translationListener, $sonataFieldDescription);
        $builder->addModelTransformer($transformer);

        $builder->add($this->translationListener->getDefaultLocale(), $options['type'], array('required' => $options['required']));
        foreach ($this->translationListener->getLocales() as $allowLang) {
            $builder->add($allowLang, $options['type'], array('required' => false));
        }

    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $options = array('compound' => true, 'type' => null);

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