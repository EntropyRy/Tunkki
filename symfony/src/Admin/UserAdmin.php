<?php
namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Sonata\UserBundle\Admin\Model\UserAdmin as BaseUserAdmin;
use Sonata\AdminBundle\Route\RouteCollection;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\DatePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class UserAdmin extends BaseUserAdmin
{
    protected $baseRoutePattern = 'user';
    protected $ts;
    protected $mm;
    protected $userManager;
    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        parent::configureDatagridFilters($datagridMapper);
        $datagridMapper;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        parent::configureListFields($listMapper);
        $listMapper;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        parent::configureFormFields($formMapper);
        $formMapper
            ->tab('Member')
                ->with('Entropy', ['class' => 'col-md-6'])
                    ->add('member')
                ->end()
            ->end()
            ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        parent::configureShowFields($showMapper);
        $showMapper;
    }
    public function postPersist($Event)
    {
        $user = $this->ts->getToken()->getUser();
        $text = $this->getMMtext($Event, $user);
        $this->mm->SendToMattermost($text);
    }
    private function getMMtext($NewUser, $user)
    {
        $text = 'USER: <'.$this->generateUrl('show', ['id'=>$NewUser->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.
                $NewUser.'> ';
        $text .= ' by '. $user;
        return $text;
    }
    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null) 
    { 
        $this->mm = $mm; 
        $this->ts = $ts; 
        parent::__construct($code, $class, $baseControllerName); 
    }
    /**
     * @param UserManagerInterface $userManager
     */
    public function setUserManager(UserManagerInterface $userManager): void
    {
        $this->userManager = $userManager;
    }
    /**
     * @return UserManagerInterface
     */
    public function getUserManager()
    {
        return $this->userManager;
    }
    public function configure()
    {
        parent::configure();

        $this->setUserManager($this->getConfigurationPool()->getContainer()->get('fos_user.user_manager'));
    }

}
