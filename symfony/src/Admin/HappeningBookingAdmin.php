<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Happening;
use App\Entity\HappeningBooking;
use App\Entity\Member;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class HappeningBookingAdmin extends AbstractAdmin
{
    /**
     * Enable inline editing in Happening admin's OneToMany collection by telling
     * Sonata which association links this child to its parent.
     */
    protected $parentAssociationMapping = 'happening';

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('happening')
            ->add('member')
            ->add('comment')
            ->add('createdAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id')
            ->add('happening', null, [
                'associated_property' => static function (?Happening $h): ?string {
                    return $h?->getNameEn() ?? $h?->getNameFi();
                },
            ])
            ->add('member', null, [
                'associated_property' => static function (?Member $m): ?string {
                    return $m?->getName();
                },
            ])
            ->add('comment')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                    'show' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        /** @var HappeningBooking|null $subject */
        $subject = $this->getSubject();
        $isEmbeddedInHappening = $this->hasParentFieldDescription();

        // When used standalone (not embedded under Happening), allow selecting the Happening.
        if (!$isEmbeddedInHappening) {
            // Either a compact autocomplete or a full ModelList chooser works; keep both patterns handy.
            $form->add('happening', ModelListType::class, [
                'btn_add' => false,
                'required' => true,
                'label' => 'Happening',
            ]);
        }

        // Member selection
        // Prefer ModelAutocomplete for large datasets; fallback ModelList if you want modal selection.
        $form->add('member', ModelAutocompleteType::class, [
            'property' => ['firstname', 'lastname', 'email', 'username'],
            'required' => true,
            'label' => 'Member',
            'to_string_callback' => static function (Member $member, ?string $property = null): string {
                return $member->getName() . ' <' . $member->getEmail() . '>';
            },
        ]);

        // Comment field (optional)
        $form->add('comment', TextType::class, [
            'required' => false,
            'empty_data' => '',
        ]);

        // Read-only createdAt for visibility
        $form->add('createdAt', null, [
            'disabled' => true,
            'required' => false,
        ]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('happening')
            ->add('member')
            ->add('comment')
            ->add('createdAt');
    }

    #[\Override]
    public function prePersist(object $object): void
    {
        // When the admin is used inline under Happening, ensure the association is set.
        if ($object instanceof HappeningBooking) {
            if ($this->hasParentFieldDescription() && method_exists($this, 'getParent') && $this->getParent()) {
                $parent = $this->getParent()->getSubject();
                if ($parent instanceof Happening && $object->getHappening() === null) {
                    $object->setHappening($parent);
                }
            }
        }
    }

    #[\Override]
    public function preUpdate(object $object): void
    {
        // Same safety as prePersist to keep association intact during inline edits.
        if ($object instanceof HappeningBooking) {
            if ($this->hasParentFieldDescription() && method_exists($this, 'getParent') && $this->getParent()) {
                $parent = $this->getParent()->getSubject();
                if ($parent instanceof Happening && $object->getHappening() === null) {
                    $object->setHappening($parent);
                }
            }
        }
    }
}
