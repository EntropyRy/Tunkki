<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Nakkikone;
use App\Form\MarkdownEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * @extends AbstractAdmin<Nakkikone>
 */
final class NakkikoneAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('enabled', CheckboxType::class, [
                'help' => 'Publish nakkikone and allow members to reserve Nakkis',
                'required' => false,
            ])
            ->add('showLinkInEvent', CheckboxType::class, [
                'help' => 'Publish nakkikone in event',
            ])
            ->add('requireDifferentTimes', CheckboxType::class, [
                'help' => 'Make sure member nakki bookings do not overlap',
            ])
            ->add('requiredForTicketReservation', CheckboxType::class, [
                'help' => 'allow tikets to be reserved only after nakki reservation',
                'required' => false,
            ])
            ->add('responsibleAdmins', ModelAutocompleteType::class, [
                'help' => 'Admins that can manage nakkikone even if they are not event admins',
                'multiple' => true,
                'required' => false,
                'btn_add' => false,
                'property' => 'name',
            ])
            ->add('infoEn', MarkdownEditorType::class, [
                'required' => false,
            ])
            ->add('infoFi', MarkdownEditorType::class, [
                'required' => false,
            ]);
    }
}
