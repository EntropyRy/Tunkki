<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Item;
use App\Repository\ItemRepository;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface as Category;

class ItemsType extends AbstractType
{
    public function __construct(
        protected ItemRepository $itemR,
        protected Category $cm
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
                if ($choice->getCategory() == $cat) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                } elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
            }
        }
        return $cats;
    }
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = $this->itemR->getAllItemChoices();
        $categories = $this->getCategories($choices);

        $resolver->setDefaults(
            [
            'class' => Item::class,
            'required' => false,
            'choices' => $choices,
            'bookings' => null,
            'categories' => $categories,
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
