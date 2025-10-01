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
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Restaurant' => 'restaurant',
                    'Event' => 'event',
                ],
                'required' => true,
            ])
            ->add('time', null, [
                'help' => 'When is this happening?',
                // 'html5' => false,
                // 'date_format' => 'D, G:i'
            ])
            ->add('picture', MediaType::class, [
                'context' => 'artist',
                'provider' => 'sonata.media.provider.image',
                'translation_domain' => 'messages',
            ])
            ->add('nameFi')
            ->add('descriptionFi', null, [
                'attr' => ['placeholder' => 'happening.description_fi', 'rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('paymentInfoFi', null, [
                'attr' => ['placeholder' => 'happening.payment_info_fi', 'rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceFi', null, [
                'attr' => ['placeholder' => 'happening.price_fi'],
            ])
            ->add('nameEn', null, [])
            ->add('descriptionEn', null, [
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('paymentInfoEn', null, [
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceEn')
            ->add('needsPreliminarySignUp', null, [
                'attr' => [
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#signups',
                    'aria-expanded' => 'false',
                    'aria-controls' => 'signups',
                ],
            ])
            ->add('maxSignUps')
            ->add('signUpsOpenUntil')
            ->add('allowSignUpComments')
            ->add('needsPreliminaryPayment', null, [
                'label' => 'happening.show_payment_info',
                'attr' => [
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#payment',
                    'aria-expanded' => 'false',
                    'aria-controls' => 'payment',
                ],
            ])
            ->add('releaseThisHappeningInEvent');
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Happening::class,
        ]);
    }
}
