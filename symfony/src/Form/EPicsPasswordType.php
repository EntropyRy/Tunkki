<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for setting or overriding the user's Lychee (ePics) account password.
 *
 * This form is intentionally unmapped; controllers should read the submitted password from
 * the "plainPassword" field and call the Lychee API to create/update the account password.
 */
class EPicsPasswordType extends AbstractType
{
    #[\Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'passwords_need_to_match',
            'first_options' => [
                'label' => 'profile.epics.form.password',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'profile.epics.form.help',
            ],
            'second_options' => [
                'label' => 'profile.epics.form.password_repeat',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => [
                new NotBlank(message: 'password.required'),
                new Length(min: 8, minMessage: 'password.min_length'),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Unmapped standalone form
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'epics_password',
            // Allow controllers to override labels via translations if needed
            'translation_domain' => 'messages',
        ]);
    }
}
