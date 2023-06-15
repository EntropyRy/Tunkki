<?php

namespace App\Form;

use App\Entity\Happening;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HappeningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Restaurant' => 'restaurant',
                    'Event' => 'event'
                ],
                'required' => true
            ])
            ->add('time', null, [
                'help' => 'When is this happening?',
                //'html5' => false,
                //'date_format' => 'D, G:i'
            ])
            ->add('picture', MediaType::class, [
                'context' => 'artist',
                'provider' => 'sonata.media.provider.image',
                'translation_domain' => 'messages',
            ])
            ->add('nameFi')
            ->add('descriptionFi', null, [
                'attr' => ['placeholder' => 'happening.description_fi']
            ])
            ->add('paymentInfoFi', null, [
                'attr' => ['placeholder' => 'happening.payment_info_fi']

            ])
            ->add('priceFi', null, [
                'attr' => ['placeholder' => 'happening.price_fi']

            ])
            ->add('nameEn', null, [])
            ->add('descriptionEn', null, [])
            ->add('paymentInfoEn')
            ->add('priceEn')
            ->add('needsPreliminarySignUp')
            ->add('maxSignUps')
            ->add('signUpsOpenUntil')
            ->add('needsPreliminaryPayment', null, [
                'label' => 'happening.show_payment_info'
            ])
            ->add('releaseThisHappeningInEvent');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Happening::class,
        ]);
    }
}
