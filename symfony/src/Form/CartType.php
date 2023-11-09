<?php

namespace App\Form;

use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disabled = false;
        $help = 'e30v.cart.email.help';

        if ($options['data']->getEmail()) {
            $disabled = true;
            $help = '';
        }
        $builder
            ->add('email', EmailType::class, [
                'disabled' => $disabled,
                'help' => $help,
                'help_html' => true,
            ])
            ->add('products', CollectionType::class, [
                'entry_type' => CartItemType::class,
                'delete_empty' => function (CartItem $item = null): bool {
                    return null === $item || $item->getQuantity() == 0;
                },
                'allow_delete' => true
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cart::class,
        ]);
    }
}
