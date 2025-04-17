<?php

namespace App\Twig\Components\Stream;

use App\Entity\Member;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use App\Entity\User;
use App\Form\StreamArtistType;
use App\Repository\StreamArtistRepository;
use App\Repository\StreamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
final class ArtistControl extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    public ?Stream $stream = null;

    #[LiveProp(writable: true)]
    public ?StreamArtist $initialForm = null;

    #[LiveProp]
    public ?Member $member = null;

    #[LiveProp(writable: true)]
    public bool $isOnline = false;

    #[LiveProp(writable: true)]
    public bool $isInStream = false;

    #[LiveProp(writable: true)]
    public ?StreamArtist $existingStreamArtist = null;

    public function __construct(
        private readonly StreamRepository $streamRepository,
        private readonly StreamArtistRepository $streamArtistRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->stream = $this->streamRepository->findOneBy(['online' => true]);
    }

    public function mount(): void
    {
        $this->init();
    }

    protected function init(): void
    {
        // Check if user is logged in
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }
        assert($user instanceof User);
        $this->member = $user->getMember();

        // Only proceed if member has artists
        if (!$this->member || !$this->member->getArtist()->count()) {
            return;
        }

        if (!$this->stream instanceof Stream) {
            $this->stream = $this->streamRepository->findOneBy(['online' => true], ['id' => 'DESC']);
        }

        // Check if member already has an active artist in the stream
        if ($this->stream !== null) {
            // Find if any of the member's artists are active in the stream
            $activeStreamArtist = $this->streamArtistRepository->findActiveMemberArtistInStream(
                $this->member,
                $this->stream
            );

            if ($activeStreamArtist) {
                $this->existingStreamArtist = $activeStreamArtist;
                $this->isInStream = true;
            }
        }

    }
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        // If member doesn't exist, return an empty form
        if (!$this->member instanceof Member) {
            return $this->createForm(StreamArtistType::class, null, [
                'member' => null,
                'stream' => null,
            ]);
        }

        if ($this->isInStream && $this->existingStreamArtist) {
            // If already in stream, instantiate form for removal
            return $this->createForm(StreamArtistType::class, $this->existingStreamArtist, [
                'member' => $this->member,
                'stream' => $this->stream,
                'is_in_stream' => true
            ]);
        }

        // Otherwise instantiate form for adding
        $sa = new StreamArtist();
        if ($this->stream instanceof Stream) {
            $sa->setStream($this->stream);
        }
        $this->initialForm = $sa;
        return $this->createForm(StreamArtistType::class, $this->initialForm, [
            'member' => $this->member,
            'stream' => $this->stream,
            'is_in_stream' => false
        ]);
    }

    #[LiveAction]
    public function save(): void
    {
        // Only process if member exists
        if (!$this->member instanceof Member) {
            return;
        }

        $this->validate();

        // Submit the form
        $this->submitForm();
        $form = $this->getForm();

        if ($this->isInStream && $this->existingStreamArtist) {
            // Handle removal - Set stoppedAt to mark it as inactive
            $this->existingStreamArtist->setStoppedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            // Reset the state after removal
            $this->isInStream = false;
            $this->existingStreamArtist = null;
            $this->resetForm();
        } else {
            // Handle addition
            $streamArtist = $form->getData();

            // Check if member already has an active artist and deactivate it first
            $existingActiveArtists = $this->streamArtistRepository->findActiveArtistsInStream(
                $this->stream
            );

            foreach ($existingActiveArtists as $activeArtist) {
                $memberArtists = $this->member->getArtist();
                foreach ($memberArtists as $memberArtist) {
                    if ($activeArtist->getArtist()->getId() === $memberArtist->getId()) {
                        // This is a current member's active artist, deactivate it
                        $activeArtist->setStoppedAt(new \DateTimeImmutable());
                    }
                }
            }

            // Now add the new one
            $this->entityManager->persist($streamArtist);
            $this->entityManager->flush();

            // Update state after addition
            $this->isInStream = true;
            $this->existingStreamArtist = $streamArtist;
            $this->resetForm();
        }

        // Force component re-render
        $this->emit('stream:updated');
    }

    #[LiveListener('stream:started')]
    public function onStreamStarted(): void
    {
        $this->init();
    }

    #[LiveListener('stream:stopped')]
    public function onStreamStopped(): void
    {
        $this->isOnline = false;
        $this->isInStream = false;
        $this->existingStreamArtist = null;
        $this->stream = null;
        $this->emit('stream:updated');
    }

    #[LiveAction]
    public function cancel(): void
    {
        // Only process if member exists
        if (!$this->member || !$this->existingStreamArtist) {
            return;
        }

        if ($this->isInStream) {
            // Handle explicit cancel request - mark as stopped
            $this->existingStreamArtist->setStoppedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            // Reset state
            $this->isInStream = false;
            $this->existingStreamArtist = null;
            $this->resetForm();

            // Force component re-render
            $this->emit('stream:updated');
        }
    }
}
