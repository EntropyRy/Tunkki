<?php
namespace Entropy\TunkkiBundle\Form\Type;

use Sonata\AdminBundle\Form\DataTransformer\ModelsToArrayTransformer;
use Sonata\AdminBundle\Form\DataTransformer\ModelToIdTransformer;
use Sonata\AdminBundle\Form\EventListener\MergeCollectionListener;
use Sonata\AdminBundle\Form\ChoiceList\ModelChoiceLoader;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Entropy\TunkkiBundle\Entity\Item;

use Doctrine\ORM\EntityManagerInterface;

class ItemsType extends AbstractType
{
   /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;
    protected $em;
    protected $cm;

    public function __construct(PropertyAccessorInterface $propertyAccessor, $em, $cm)
    {
        $this->propertyAccessor = $propertyAccessor;
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
    public function configureOptions(OptionsResolver $resolver)
    {
		$root = $this->cm->getRootCategory('item');
	    $queryBuilder = $this->em->createQueryBuilder('i')
                ->select('i')
                ->from('EntropyTunkkiBundle:Item', 'i')
                ->Where('i.needsFixing = false')
                ->andWhere('i.rent >= 0.00')
                ->andWhere('i.toSpareParts = false')
                ->leftJoin('i.packages', 'p')
				->andWhere('p IS NULL')
                ->orderBy('i.name', 'ASC');

		$choices = $queryBuilder->getQuery()->getResult();
		// map categories
		foreach($choices as $choice) {
			foreach($root->getChildren() as $cat) {
				if($choice->getCategory() == $cat){
					$cats[$cat->getName()]=$choice;
				}
				elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())){
					$cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
				}	
			}	
		}
		$resolver->setDefaults([
			'class' => Item::class,
			'required' => false,
			'choices' => $choices,
			'bookings' => null,
			'categories' => $cats,
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

