<?php

namespace Cocorico\CoreBundle\Form\Type\Frontend;

use Cocorico\CoreBundle\Entity\Quote;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

class QuoteType extends AbstractType
{
    public function __construct()
    {
        // Nothing yet
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options 
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $quote = $builder->getData();

        $builder
            ->add('budget',
                HiddenType::class,
                array(
                    'data' => 0
                ))
                // array(
                //     'label' => 'quote.form.budget',
                //     'required' => 'false'
                // ))
            ->add('prestaStartDate',
                HiddenType::class,
                array(
                    'data' => null
                ))
                // array_merge(
                //     array(
                //     'required' => 'false',
                //     'label' => 'quote.form.presta_start_date',
                //     'property_path' => 'prestaStartDate',
                //     'widget' => 'single_text',
                //     'format' => 'dd/MM/yyyy',
                //     )
                // ))
            # ->add('frequency_period', ChoiceType::class, ['choices' => ['month', 'week']])
            # ->add('surface_m2', NumberType::class)
            # ->add('surface_type', ChoiceType::class, ['choices' => ['wood', 'concrete']])
            ->add('preferredContact',
                ChoiceType::class,
                array(
                    'label' => 'quote.form.preferredContact',
                    'required' => 'true',
                    'expanded' => 'true',
                    'empty_data' => Quote::PREFCONTACT_PHONE,
                    'data' => Quote::PREFCONTACT_PHONE,
                    'choices' => array_flip(Quote::$preferredContactTypes),
                ),
            )
            ->add('communication',
                TextareaType::class,
                array_merge(
                    array(
                    'required' => 'true',
                    'label' => 'quote.form.communication'
                    )
                ));
    }


    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'allow_extra_fields' => true,
                'data_class' => 'Cocorico\CoreBundle\Entity\Quote',
                'csrf_token_id' => 'quote',
                'translation_domain' => 'cocorico_quote',
                'constraints' => new Valid(),
                'validation_groups' => array('new'),
//                'error_bubbling' => false,//To prevent errors bubbling up to the parent form
//                //To map errors of all unmapped properties (date_range) to a particular field (date_range)
//                'error_mapping' => array(
//                    '.' => 'date_range',
//                ),
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'quote';
    }
}
