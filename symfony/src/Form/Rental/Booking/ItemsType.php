<?php

declare(strict_types=1);

namespace App\Form\Rental\Booking;

use App\Entity\Rental\Inventory\Item;
use App\Repository\Rental\Inventory\ItemRepository;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface as Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<null>
 */
class ItemsType extends AbstractType
{
    public function __construct(
        protected ItemRepository $itemR,
        protected Category $cm,
    ) {
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['bookings'] = $options['bookings'];
        $view->vars['categories'] = $options['categories'];
        $view->vars['btn_add'] = $options['btn_add'];
        $view->vars['btn_list'] = $options['btn_list'];
        $view->vars['btn_delete'] = $options['btn_delete'];
        $view->vars['btn_catalogue'] = $options['btn_catalogue'];
    }

    private function getCategories($choices): array
    {
        $slug = $this->cm->getBySlug('item');
        $root = $this->cm->getRootCategoryWithChildren($slug);
        // map categories
        $cats = [];
        foreach ($choices as $choice) {
            foreach ($root->getChildren() as $cat) {
                if ($choice->getCategory() === $cat) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()] = $choice;
                } elseif (\in_array($choice->getCategory(), $cat->getChildren()->toArray(), true)) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()] = $choice;
                }
            }
        }

        return $cats;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'class' => Item::class,
                'required' => false,
                'choices' => static fn (): array => [],
                'bookings' => null,
                'categories' => static fn (): array => [],
                'by_reference' => false,
                'compound' => true,
                'multiple' => true,
                'expanded' => true,
                'btn_add' => 'link_add',
                'btn_list' => 'link_list',
                'btn_delete' => 'link_delete',
                'btn_catalogue' => 'SonataAdminBundle',
            ]
        );

        $resolver->setDefault('choices', fn (Options $options): array => $this->itemR->getAllItemChoices());

        $resolver->setDefault('categories', fn (Options $options): array => $this->getCategories($options['choices']));
    }

    #[\Override]
    public function getParent(): string
    {
        return EntityType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'entropy_type_items';
    }
}
