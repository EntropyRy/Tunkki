<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Happening;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class HappeningType extends AbstractType
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

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
                'constraints' => [new NotBlank(message: 'happening.validation.type_required')],
            ])
            ->add('time', null, [
                'help' => 'When is this happening?',
                // 'format' => 'D, G:i'
            ])
/*            ->add('time', DateTimeType::class, [

                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd HH:mm:ss',
                'input' => 'datetime',
                'required' => false,
            ])
                */
            ->add('picture', MediaType::class, [
                'context' => 'artist',
                'provider' => 'sonata.media.provider.image',
                'translation_domain' => 'messages',
                'required' => false,
            ])
            ->add('nameFi', null, [
                'constraints' => [new NotBlank(message: 'happening.validation.name_fi_required')],
            ])
            ->add('descriptionFi', null, [
                'attr' => ['placeholder' => 'happening.description_fi', 'rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
                'constraints' => [new NotBlank(message: 'happening.validation.description_fi_required')],
            ])
            ->add('paymentInfoFi', null, [
                'attr' => ['placeholder' => 'happening.payment_info_fi', 'rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceFi', null, [
                'attr' => ['placeholder' => 'happening.price_fi'],
            ])
            ->add('nameEn', null, [
                'constraints' => [new NotBlank(message: 'happening.validation.name_en_required')],
            ])
            ->add('descriptionEn', null, [
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
                'constraints' => [new NotBlank(message: 'happening.validation.description_en_required')],
            ])
            ->add('paymentInfoEn', null, [
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceEn')
            ->add('needsPreliminarySignUp', null, [
                'required' => false,
                'attr' => [
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#signups',
                    'aria-expanded' => 'false',
                    'aria-controls' => 'signups',
                ],
            ])
            ->add('maxSignUps', null, [
                'constraints' => [new PositiveOrZero(message: 'happening.validation.max_signups_positive_or_zero')],
                'empty_data' => '0',
            ])
            ->add('signUpsOpenUntil')
            ->add('allowSignUpComments')
            ->add('needsPreliminaryPayment', null, [
                'required' => false,
                'label' => 'happening.show_payment_info',
                'attr' => [
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#payment',
                    'aria-expanded' => 'false',
                    'aria-controls' => 'payment',
                ],
            ])
            ->add('releaseThisHappeningInEvent', null, [
                'required' => false,
            ]);

        // Conditional validation at form-level: when preliminary payment is enabled,
        // require payment info in both languages.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }
            if (\array_key_exists('time', $data)) {
                $value = $data['time'];
                if (\is_string($value)) {
                    $value = trim($value);
                }
                if ('' === $value || null === $value) {
                    // Keep the original entity value set earlier (e.g., event date) by unsetting empty submission
                    unset($data['time']);
                    $event->setData($data);
                }
            }
        });
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            if (!$data instanceof Happening) {
                return;
            }

            if (!$data->getSlugFi() && $data->getNameFi()) {
                $data->setSlugFi($this->slugger->slug($data->getNameFi())->lower());
            }
            if (!$data->getSlugEn() && $data->getNameEn()) {
                $data->setSlugEn($this->slugger->slug($data->getNameEn())->lower());
            }
        });
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $form->getData();

            if (!$data instanceof Happening) {
                return;
            }

            // Slugs are set in the SUBMIT listener before validation.

            if ($data->isNeedsPreliminaryPayment()) {
                $fi = $data->getPaymentInfoFi();
                $en = $data->getPaymentInfoEn();

                if ((null === $fi || '' === trim($fi)) && $form->has('paymentInfoFi')) {
                    $form->get('paymentInfoFi')->addError(new FormError('', 'happening.payment_info_required'));
                }

                if ((null === $en || '' === trim($en)) && $form->has('paymentInfoEn')) {
                    $form->get('paymentInfoEn')->addError(new FormError('', 'happening.payment_info_required'));
                }
            }
        });
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Happening::class,
            'error_translation_domain' => 'validators',
        ]);
    }
}
