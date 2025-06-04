<?php

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class MemberEditType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class)
            ->add('firstname', TextType::class)
            ->add('lastname', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('locale', ChoiceType::class, [
                'choices' => ['fi' => 'fi', 'en' => 'en']
            ])
            ->add('CityOfResidence', TextType::class)
            ->add('StudentUnionMember', CheckboxType::class, [
                'label_attr' => ['class' => 'switch-custom'],
                'required' => false
            ])
            ->add('theme', ChoiceType::class, [
                'choices' => [
                    'bright' => 'light',
                    'dark' => 'dark'
                ],
                'required' => true
            ])
            ->add('allowInfoMails', CheckboxType::class, [
                'label_attr' => ['class' => 'switch-custom'],
                'required' => false,
                'label' => 'member.allow_info_mails'
            ])
        ;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $member = $event->getData();
            $form = $event->getForm();

            // checks if the member is active member
            if ($member && $member->getIsActiveMember()) {
                $form->add('allowActiveMemberMails', CheckboxType::class, [
                    'label_attr' => ['class' => 'switch-custom'],
                    'label' => 'member.allow_active_member_mails',
                    'required' => false
                ]);
            }
        });
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Member::class,
        ]);
    }
}
