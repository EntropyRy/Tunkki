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
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $help = 'e30v.cart.email.help';

        if ($options['data']->getEmail()) {
            $help = '';
        }
        $builder
            ->add('email', EmailType::class, [
                'help' => $help,
                'help_html' => true,
                'label' => 'e30v.cart.email.label'
            ])
            ->add('products', CollectionType::class, [
                'entry_type' => CartItemType::class,
                'delete_empty' => fn(CartItem $item = null): bool => null === $item || $item->getQuantity() == 0,
                'allow_delete' => true,
            ]);;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cart::class,
        ]);
    }
}
