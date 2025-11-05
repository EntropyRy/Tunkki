<?php

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class MemberAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Member::class,
            'placeholder' => 'nakkikone.board.choose_responsible',
            // 'choice_label' => 'name',

            // choose which fields to use in the search
            // if not passed, *all* fields are used
            // 'searchable_fields' => ['name'],

            // Require user to be authenticated to search members
            'security' => 'ROLE_ADMIN',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
