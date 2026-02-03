<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Email;
use App\Enum\EmailPurpose;
use App\Form\MarkdownEditorType;
use App\Repository\EmailRepository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface as RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * @extends AbstractAdmin<Email>
 */
final class EmailAdmin extends AbstractAdmin
{
    public function __construct(
        protected EmailRepository $emailRepository,
    ) {
    }

    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'email';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('purpose')
            ->add('event')
            ->add('subject')
            ->add('body')
            ->add('sentAt')
            ->add('sentBy')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        if (!$this->isChild()) {
            $listMapper
                ->add('event')
                ->addIdentifier('purpose');
        } else {
            $listMapper
                ->addIdentifier('purpose');
        }
        $listMapper
            ->add('subject')
            // ->add('body', 'html')
            ->add('updatedAt', 'datetime')
            ->add('sentAt', 'datetime')
            ->add('sentBy')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'preview' => ['template' => 'admin/crud/list__action_email_preview.html.twig'],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $existingSingletonPurposes = $this->getExistingSingletonPurposes();
        if (!$this->hasSubject() || null === $this->getSubject()->getId()) {
            $this->configureCreateFormFields($formMapper, $existingSingletonPurposes);

            return;
        }

        if (!$this->isChild()) {
            $this->configureStandaloneFormFields($formMapper, $existingSingletonPurposes);
        } else {
            $this->configureChildFormFields($formMapper, $existingSingletonPurposes);
        }

        $this->configureCommonFormFields($formMapper);
    }

    /**
     * Only expose purpose on create.
     *
     * @param array<EmailPurpose> $existingSingletonPurposes
     */
    private function configureCreateFormFields(FormMapper $formMapper, array $existingSingletonPurposes): void
    {
        $currentPurpose = $this->hasSubject() ? $this->getSubject()->getPurpose() : null;

        if (!$this->isChild()) {
            $formMapper
                ->add('purpose', EnumType::class, [
                    'class' => EmailPurpose::class,
                    'choice_label' => static fn (?EmailPurpose $purpose): string => $purpose?->label() ?? 'None',
                    'choice_filter' => static function (?EmailPurpose $purpose) use ($existingSingletonPurposes, $currentPurpose): bool {
                        if (!$purpose instanceof EmailPurpose) {
                            return false;
                        }
                        if (!$purpose->canBeUsedInStandaloneAdmin()) {
                            return false;
                        }
                        if ($purpose === $currentPurpose) {
                            return true;
                        }

                        return !\in_array($purpose, $existingSingletonPurposes, true);
                    },
                    'required' => false,
                    'expanded' => true,
                    'help' => 'There is also automatic Booking email to vuokra list and "application rejected" for active member (sent from member list). these cannot be edited here. Other kinds of emails can be defined.',
                ]);

            return;
        }

        $formMapper
            ->add('purpose', EnumType::class, [
                'class' => EmailPurpose::class,
                'choice_label' => static fn (?EmailPurpose $purpose): string => $purpose?->label() ?? 'None',
                'choice_filter' => static function (?EmailPurpose $purpose) use ($existingSingletonPurposes, $currentPurpose): bool {
                    if (!$purpose instanceof EmailPurpose) {
                        return false;
                    }
                    if (!$purpose->canBeUsedInChildAdmin()) {
                        return false;
                    }
                    if ($purpose === $currentPurpose) {
                        return true;
                    }

                    return !\in_array($purpose, $existingSingletonPurposes, true);
                },
                'required' => false,
                'expanded' => false,
                'help' => 'Main purpose determines the email template.',
            ]);
    }

    /**
     * Query existing singleton purposes to filter them from dropdowns.
     *
     * @return array<EmailPurpose>
     */
    private function getExistingSingletonPurposes(): array
    {
        if (!$this->hasSubject()) {
            return [];
        }

        $currentEmail = $this->getSubject();

        return $this->emailRepository->findExistingSingletonPurposes($currentEmail);
    }

    /**
     * Configure form fields for standalone admin (non-event context).
     *
     * @param array<EmailPurpose> $existingSingletonPurposes
     */
    private function configureStandaloneFormFields(FormMapper $formMapper, array $existingSingletonPurposes): void
    {
        $currentEmail = $this->getSubject();

        $formMapper
            ->add('purpose', EnumType::class, [
                'class' => EmailPurpose::class,
                'choice_label' => static fn (?EmailPurpose $purpose): string => $purpose?->label() ?? 'None',
                'choice_filter' => static function (?EmailPurpose $purpose) use ($existingSingletonPurposes, $currentEmail): bool {
                    if (!$purpose instanceof EmailPurpose) {
                        return false;
                    }
                    // Filter out event-requiring purposes
                    if (!$purpose->canBeUsedInStandaloneAdmin()) {
                        return false;
                    }
                    // ALWAYS allow current email's purpose (even if duplicates exist due to data corruption)
                    if ($purpose === $currentEmail->getPurpose()) {
                        return true;
                    }

                    // Filter out singleton purposes that already exist
                    return !\in_array($purpose, $existingSingletonPurposes, true);
                },
                'required' => false,
                'expanded' => true,
                'help' => 'There is also automatic Booking email to vuokra list and "application rejected" for active member (sent from member list). these cannot be edited here. Other kinds of emails can be defined.',
            ]);

        // Show event and recipientGroups fields only when editing (not on create)
        if ($currentEmail->getId()) {
            $formMapper
                ->add('event', null, [
                    'disabled' => true,
                    'help' => 'Event association (read-only in standalone admin)',
                ])
            ;
        }
    }

    /**
     * Configure form fields for child admin (event context).
     *
     * @param array<EmailPurpose> $existingSingletonPurposes
     */
    private function configureChildFormFields(FormMapper $formMapper, array $existingSingletonPurposes): void
    {
        $currentEmail = $this->getSubject();

        $formMapper
            ->add('purpose', EnumType::class, [
                'class' => EmailPurpose::class,
                'choice_label' => static fn (?EmailPurpose $purpose): string => $purpose?->label() ?? 'None',
                'choice_filter' => static function (?EmailPurpose $purpose) use ($existingSingletonPurposes, $currentEmail): bool {
                    if (!$purpose instanceof EmailPurpose) {
                        return false;
                    }
                    // Only show event-requiring purposes or recipient groups
                    if (!$purpose->canBeUsedInChildAdmin()) {
                        return false;
                    }
                    // ALWAYS allow current email's purpose (even if duplicates exist due to data corruption)
                    if ($purpose === $currentEmail->getPurpose()) {
                        return true;
                    }

                    // Filter out singleton purposes that already exist
                    return !\in_array($purpose, $existingSingletonPurposes, true);
                },
                'required' => false,
                'expanded' => false,
                'help' => 'Main purpose determines the email template.',
            ]);

        // Only show recipientGroups if purpose is NOT TICKET_QR (automatic email)
        if (EmailPurpose::TICKET_QR !== $currentEmail->getPurpose()) {
            $formMapper
                ->add('recipientGroups', EnumType::class, [
                    'class' => EmailPurpose::class,
                    'choice_label' => static fn (?EmailPurpose $purpose): string => $purpose?->label() ?? 'None',
                    'choice_filter' => static function (?EmailPurpose $purpose) use ($existingSingletonPurposes, $currentEmail): bool {
                        if (!$purpose instanceof EmailPurpose) {
                            return false;
                        }
                        // Show purposes that can be recipient groups
                        if (!$purpose->canBeRecipientGroup()) {
                            return false;
                        }
                        // ALWAYS allow purposes already in current email's recipientGroups (even if duplicates exist)
                        if (\in_array($purpose, $currentEmail->getRecipientGroups(), true)) {
                            return true;
                        }

                        // Filter out singleton purposes that already exist
                        return !\in_array($purpose, $existingSingletonPurposes, true);
                    },
                    'required' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'help' => 'Also send to these groups (recipients will be deduplicated).',
                ]);
        }

        $formMapper
            ->add('replyTo', null, [
                'help' => 'Empty defaults to hallitus@entropy.fi.',
            ]);
    }

    /**
     * Configure common form fields (subject, body, etc).
     */
    private function configureCommonFormFields(FormMapper $formMapper): void
    {
        $email = $this->getSubject();
        $subjectHelp = 'All mails have forced prefix "[Entropy]" for consistency. Include Finnish and English version to same message!';
        $disabled = false;
        $placeholder = $email->getSubject();

        if (EmailPurpose::TICKET_QR === $email->getPurpose()) {
            $subjectHelp = 'Generated automatically';
            $disabled = true;
            $placeholder = '[event name] Ticket #1 / Lippusi #1';
        }

        $bodyAttr = [];
        if ($this->isChild()) {
            $event = $email->getEvent();
            if ($event) {
                $bodyAttr['data-markdown-editor-event-button-fi-value'] = $event->getUrlByLang('fi');
                $bodyAttr['data-markdown-editor-event-button-en-value'] = $event->getUrlByLang('en');
            }
        }

        $formMapper
            ->add('subject', null, [
                'help' => $subjectHelp,
                'disabled' => $disabled,
                'data' => $placeholder,
            ])
            ->add('body', MarkdownEditorType::class, [
                'attr' => $bodyAttr,
            ])
            ->add('addLoginLinksToFooter', null, ['help' => 'adds links to login']);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('purpose')
            ->add('subject')
            ->add('body', 'html')
            ->add('addLoginLinksToFooter')
            ->add('createdAt')
            ->add('updatedAt');
    }

    #[\Override]
    protected function configureRoutes(RouteCollection $collection): void
    {
        $collection->remove('show');
        $collection->add('preview', $this->getRouterIdParameter().'/preview');
        $collection->add('send', $this->getRouterIdParameter().'/send');
        $collection->add('send_progress', $this->getRouterIdParameter().'/send-progress');
    }
}
