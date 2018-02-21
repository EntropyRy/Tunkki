<?php
namespace Entropy\TunkkiBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Entropy\TunkkiBundle\Entity\Item;

use Doctrine\ORM\EntityManagerInterface;

class ItemsType extends AbstractType
{
   /**
     * @var PropertyAccessorInterface
     */
    protected $em;
    protected $cm;

    public function __construct($em, $cm)
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
	private function getChoices($options = null)
	{
	    $queryBuilder = $this->em->createQueryBuilder('i')
                ->select('i')
                ->from('EntropyTunkkiBundle:Item', 'i')
                ->Where('i.needsFixing = false')
//                ->andWhere('i.rent >= 0.00')
                ->andWhere('i.toSpareParts = false')
                ->andWhere('i.forSale = false')
                ->leftJoin('i.packages', 'p')
				->andWhere('p IS NULL')
                ->orderBy('i.name', 'ASC');
		$choices = $queryBuilder->getQuery()->getResult();
		return $choices;
	}

	private function getCategories($choices)
	{
		$root = $this->cm->getRootCategory('item');
		// map categories
		foreach($choices as $choice) {
			foreach($root->getChildren() as $cat) {
				if($choice->getCategory() == $cat){
					$cats[$cat->getName()][]=0;
				}
				elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())){
					$cats[$cat->getName()][$choice->getCategory()->getName()]=0;
				}
			}	
		}
		return $cats;

	}
    public function configureOptions(OptionsResolver $resolver)
	{
		$choices = $this->getChoices();
		$categories = $this->getCategories($choices);

		$resolver->setDefaults([
			'class' => Item::class,
			'required' => false,
			'choices' => $choices,
			'bookings' => null,
			'categories' => $categories,
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

