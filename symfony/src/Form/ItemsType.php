<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Item;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface as Category;

class ItemsType extends AbstractType
{
   /**
     * @var PropertyAccessorInterface
     */
    protected $em;
    protected $cm;

    public function __construct(EntityManagerInterface $em, Category $cm)
    {
        $this->em = $em;
        $this->cm = $cm;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['bookings'] = $options['bookings'];
        $view->vars['categories'] = $options['categories'];
        $view->vars['btn_add'] = $options['btn_add'];
        $view->vars['btn_list'] = $options['btn_list'];
        $view->vars['btn_delete'] = $options['btn_delete'];
        $view->vars['btn_catalogue'] = $options['btn_catalogue'];
    }
    private function getCategories($choices)
    {
        $root = $this->cm->getRootCategory('item');
        // map categories
        foreach($choices as $choice) {
            foreach($root->getChildren() as $cat) {
                if($choice->getCategory() == $cat){
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
                elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())){
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
            }   
        }
        return $cats;

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = $this->em->getRepository(Item::class)->getAllItemChoices();
        $categories = $this->getCategories($choices);

        $resolver->setDefaults([
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
        ]);
    }
    public function getParent()
    {
        return EntityType::class;
    }

    public function getBlockPrefix()
    {
        return 'entropy_type_items';
    }

}

