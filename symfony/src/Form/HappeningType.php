<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Happening;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * @extends AbstractType<Happening>
 */
class HappeningType extends AbstractType
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    #[\Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'happening.field.type',
                'choices' => [
                    'happening.type.restaurant' => 'restaurant',
                    'happening.type.event' => 'event',
                    'happening.type.lecture' => 'lecture',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'happening.validation.type_required'),
                ],
            ])
            ->add('time', null, [
                'label' => 'happening.field.time',
                'help' => 'happening.time_help',
            ])
            ->add('picture', MediaType::class, [
                'label' => 'happening.field.picture',
                'context' => 'artist',
                'provider' => 'sonata.media.provider.image',
                'translation_domain' => 'messages',
                'required' => false,
            ])
            ->add('nameFi', null, [
                'label' => 'happening.field.name_fi',
                'constraints' => [
                    new NotBlank(
                        message: 'happening.validation.name_fi_required',
                    ),
                ],
            ])
            ->add('descriptionFi', null, [
                'label' => 'happening.field.description_fi',
                'attr' => [
                    'placeholder' => 'happening.description_fi',
                    'rows' => 3,
                ],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'happening.validation.description_fi_required',
                    ),
                ],
            ])
            ->add('paymentInfoFi', null, [
                'label' => 'happening.field.payment_info_fi',
                'attr' => [
                    'placeholder' => 'happening.payment_info_fi',
                    'rows' => 3,
                ],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceFi', null, [
                'label' => 'happening.field.price_fi',
                'attr' => ['placeholder' => 'happening.price_fi'],
            ])
            ->add('nameEn', null, [
                'label' => 'happening.field.name_en',
                'constraints' => [
                    new NotBlank(
                        message: 'happening.validation.name_en_required',
                    ),
                ],
            ])
            ->add('descriptionEn', null, [
                'label' => 'happening.field.description_en',
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'happening.validation.description_en_required',
                    ),
                ],
            ])
            ->add('paymentInfoEn', null, [
                'label' => 'happening.field.payment_info_en',
                'attr' => ['rows' => 3],
                'help' => 'happening.markdown_allowed',
                'help_html' => true,
            ])
            ->add('priceEn', null, [
                'label' => 'happening.field.price_en',
            ])
            ->add('needsPreliminarySignUp', null, [
                'label' => 'happening.field.needs_preliminary_sign_up',
                'required' => false,
                'attr' => [
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#signups',
                    'aria-expanded' => 'false',
                    'aria-controls' => 'signups',
                ],
            ])
            ->add('maxSignUps', null, [
                'label' => 'happening.field.max_sign_ups',
                'constraints' => [
                    new PositiveOrZero(
                        message: 'happening.validation.max_signups_positive_or_zero',
                    ),
                ],
                'empty_data' => '0',
            ])
            ->add('signUpsOpenUntil', null, [
                'label' => 'happening.field.sign_ups_open_until',
            ])
            ->add('allowSignUpComments', null, [
                'label' => 'happening.field.allow_sign_up_comments',
            ])
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
                'label' => 'happening.field.release_in_event',
                'required' => false,
            ]);

        // Conditional validation at form-level: when preliminary payment is enabled,
        // require payment info in both languages.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (
            FormEvent $event,
        ): void {
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
                    // Keep the original entity value when time field is empty
                    $entity = $event->getForm()->getData();
                    if ($entity instanceof Happening && null !== $entity->getId()) {
                        // For existing entities, preserve the original time
                        $data['time'] = $entity->getTime()->format('Y-m-d H:i:s');
                        $event->setData($data);
                    } else {
                        // For new entities, unset to let validation handle it
                        unset($data['time']);
                        $event->setData($data);
                    }
                }
            }
        });
        $builder->addEventListener(FormEvents::SUBMIT, function (
            FormEvent $event,
        ): void {
            $data = $event->getData();

            if (!$data instanceof Happening) {
                return;
            }

            if (!$data->getSlugFi() && $data->getNameFi()) {
                $data->setSlugFi(
                    (string) $this->slugger->slug($data->getNameFi())->lower(),
                );
            }
            if (!$data->getSlugEn() && $data->getNameEn()) {
                $data->setSlugEn(
                    (string) $this->slugger->slug($data->getNameEn())->lower(),
                );
            }
        });
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (
            FormEvent $event,
        ): void {
            $form = $event->getForm();
            $data = $form->getData();

            if (!$data instanceof Happening) {
                return;
            }

            // Slugs are set in the SUBMIT listener before validation.

            if ($data->isNeedsPreliminaryPayment()) {
                $fi = $data->getPaymentInfoFi();
                $en = $data->getPaymentInfoEn();

                if (
                    (null === $fi || '' === trim($fi))
                    && $form->has('paymentInfoFi')
                ) {
                    $form
                        ->get('paymentInfoFi')
                        ->addError(
                            new FormError(
                                '',
                                'happening.payment_info_required',
                            ),
                        );
                }

                if (
                    (null === $en || '' === trim($en))
                    && $form->has('paymentInfoEn')
                ) {
                    $form
                        ->get('paymentInfoEn')
                        ->addError(
                            new FormError(
                                '',
                                'happening.payment_info_required',
                            ),
                        );
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
