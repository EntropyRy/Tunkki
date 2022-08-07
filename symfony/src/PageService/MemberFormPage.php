<?php

namespace App\PageService;

use App\Entity\Member;
use App\Entity\User;
use App\Entity\Email;
use App\Form\MemberType;
use App\Helper\Mattermost;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Twig\Environment;

class MemberFormPage implements PageServiceInterface
{
    private $templateManager;
    private $em;
    private $name;
    private $mailer;
    private $formF;
    private $bag;
    private $twig;
    private $mm;
    private $passwordEncoder;
    private $flash;

    public function __construct(
        $name,
        TemplateManager $templateManager,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        FormFactoryInterface $formF,
        ParameterBagInterface $bag,
        Environment $twig,
        Mattermost $mm,
        UserPasswordEncoderInterface $passwordEncoder,
        FlashBagInterface $flash
    ) {
        $this->name             = $name;
        $this->templateManager  = $templateManager;
        $this->em               = $em;
        $this->mailer           = $mailer;
        $this->formF            = $formF;
        $this->bag              = $bag;
        $this->twig             = $twig;
        $this->mm               = $mm;
        $this->flash            = $flash;
        $this->passwordEncoder  = $passwordEncoder;
    }
    public function getName()
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = array(), Response $response = null)
    {
        $member = new Member();
        $form = $this->formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $member = $form->getData();
                $memberRepo = $this->em->getRepository(Member::class);
                $name = $memberRepo->getByName($member->getFirstname(), $member->getLastname());
                $email = $memberRepo->getByEmail($member->getEmail());
                if (!$name && !$email) {
                    $user = $member->getUser();
                    $user->setPassword($this->passwordEncoder->encodePassword($user, $form->get('user')->get('plainPassword')->getData()));
                    $member->setLocale($request->getlocale());
                    $this->em->persist($user);
                    $this->em->persist($member);
                    $this->em->flush();
                    $this->sendEmailToMember('member', $member, $this->em, $this->mailer);
                    //$code = $this->addToInfoMailingList($member);
                    $this->announceToMattermost($member);
                    $this->flash->add('info', 'member.join.added');
                } else {
                    $this->flash->add('warning', 'member.join.update');
                }
            } else {
                $this->flash->add('danger', 'member.join.error');
            }
        }

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            array_merge($parameters, array('form'=>$form->createView())),
            $response
        );
    }
    protected function sendEmailToMember($purpose, $member, $em, $mailer)
    {
        $email_content = $em->getRepository(Email::class)->findOneBy(['purpose' => $purpose]);
        $email = (new TemplatedEmail())
            ->from(new Address($this->bag->get('mailer_sender_address'), 'Tunkki'))
            ->to($member->getEmail())
            ->subject($email_content->getSubject())
            ->htmlTemplate('emails/member.html.twig')
            ->context([
                'email_data' => $email_content,
            ])
        ;
        $mailer->send($email);
    }
    public function addToInfoMailingList($member)
    {
        $client = HttpClient::create();
        $response = $client->request('POST', 'https://list.ayy.fi/postorius/lists/tiedotus.entropy.fi/subscribe', [
            'query' => [
                'email' => $member->getEmail(),
                'display_name' => $member->getName()
            ]
        ]);
        return $response->getStatusCode();
    }
    protected function announceToMattermost($member)
    {
        $text = '**New Member: '.$member.'**';
        $this->mm->SendToMattermost($text, 'yhdistys');
    }
}
