<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Cart>
 */
class CartType extends AbstractType
{
    #[\Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $help = 'shop.cart.email.help';

        if ($options['data']->getEmail()) {
            $help = '';
        }
        $builder
            ->add('email', EmailType::class, [
                'help' => $help,
                'help_html' => true,
                'label' => 'shop.cart.email.label',
                'constraints' => [
                    new NotBlank([
                        'message' => 'email.required',
                    ]),
                    new Email([
                        'message' => 'email.invalid',
                    ]),
                ],
            ])
            ->add('products', CollectionType::class, [
                'entry_type' => CartItemType::class,
                'delete_empty' => fn (
                    ?CartItem $item = null,
                ): bool => !$item instanceof CartItem
                    || 0 == $item->getQuantity(),
                'allow_delete' => true,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cart::class,
        ]);
    }
}
