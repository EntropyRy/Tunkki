<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Event>
 */
final class EventNakkiAdminVoter extends Voter
{
    public const string ATTRIBUTE = 'event_nakki_admin';

    public function __construct(private readonly Security $security)
    {
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && $subject instanceof Event;
    }

    #[\Override]
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        \assert($subject instanceof Event);

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $member = $user->getMember();

        if ($subject->getNakkiResponsibleAdmin()->contains($member)) {
            return true;
        }

        foreach ($subject->getNakkis() as $nakki) {
            if ($nakki->getResponsible() === $member) {
                return true;
            }
        }

        return false;
    }
}
