<?php

namespace App\Form;

use App\Entity\Member;
use App\Form\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, ['help'=> 'used_in_other_services'])
            ->add('firstname', TextType::class)
            ->add('lastname', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('user', UserType::class)
            ->add('locale', ChoiceType::class, [
                'choices' => ['fi' => 'fi', 'en' => 'en']
            ])
            ->add('CityOfResidence', TextType::class)
            ->add('StudentUnionMember', CheckboxType::class, [
                'label_attr' => ['class'=>'switch-custom'],
                'required' => false
            ])
            ->add('Submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Member::class,
        ]);
    }
}
