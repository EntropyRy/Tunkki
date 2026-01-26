<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Member;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * MemberFactory.
 *
 * Creates Member entities with sensible defaults and, by default, auto-creates a
 * linked User while ensuring the owning side (User->member) is set before the
 * first flush. This prevents NULL foreign key inserts and keeps the 1:1 invariant.
 *
 * Behaviors:
 * - If a User is provided via withLinkedUser(), the owning side is synchronized.
 * - If no User is provided, a minimal User is created and linked on afterInstantiate().
 * - Relies on Doctrine cascade persist and owning side assignment to insert both.
 *
 * @extends PersistentObjectFactory<Member>
 */
final class MemberFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Member::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => \sprintf(
                'member_%s@example.test',
                bin2hex(random_bytes(6)),
            ),
            'firstname' => self::faker()->firstName(),
            'lastname' => self::faker()->lastName(),
            'locale' => self::faker()->randomElement(['fi', 'en']),
            'theme' => self::faker()->randomElement(['dark', 'light']),
            'code' => 'MBR'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
            'allowInfoMails' => true,
            'allowActiveMemberMails' => self::faker()->boolean(70),
            'isActiveMember' => false,
            // Do NOT set 'user' here; linkage is handled in initialize()->afterInstantiate().
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Member $member): void {
            $existing = $member->getUser();

            // If caller supplied a user explicitly, ensure the owning side is in sync.
            if ($existing instanceof User) {
                if ($existing->getMember() !== $member) {
                    $existing->setMember($member);
                }

                return;
            }

            // Auto-create a minimal user and link both sides BEFORE first flush.
            $user = new User();
            $user->setPassword(password_hash('password', \PASSWORD_BCRYPT));
            $user->setRoles([]);
            $user->setAuthId(bin2hex(random_bytes(10)));

            // Owning side first, then inverse side.
            $user->setMember($member);
            $member->setUser($user);
        });
    }

    public function active(): static
    {
        return $this->with(['isActiveMember' => true]);
    }

    public function inactive(): static
    {
        return $this->with([
            'isActiveMember' => false,
            'Application' => null,
            'ApplicationDate' => null,
        ]);
    }

    public function applicationPending(): static
    {
        return $this->with([
            'isActiveMember' => false,
            'Application' => self::faker()->paragraph(),
            'ApplicationDate' => new \DateTime('-7 days'),
        ]);
    }

    public function english(): static
    {
        return $this->with(['locale' => 'en']);
    }

    public function finnish(): static
    {
        return $this->with(['locale' => 'fi']);
    }

    /**
     * Supply a pre-built User for linkage; the factory will synchronize the owning side.
     */
    public function withLinkedUser(User $user): static
    {
        return $this->with(['user' => $user]);
    }

    /**
     * Attach an OAuth wiki-capable user to the member.
     */
    public function withOAuthWikiAccess(): static
    {
        return $this->afterInstantiate(static function (Member $member): void {
            $user = $member->getUser();
            if (!$user instanceof User) {
                $user = new User();
                $user->setPassword(password_hash('password', \PASSWORD_BCRYPT));
                $user->setRoles([]);
                $user->setAuthId(bin2hex(random_bytes(10)));
                $user->setMember($member);
                $member->setUser($user);
            }

            $user->setRoles(
                array_values(
                    array_unique(
                        array_merge($user->getRoles(), ['ROLE_OAUTH2_WIKI']),
                    ),
                ),
            );
        });
    }

    /**
     * Attach an OAuth forum-capable user to the member.
     */
    public function withOAuthForumAccess(): static
    {
        return $this->afterInstantiate(static function (Member $member): void {
            $user = $member->getUser();
            if (!$user instanceof User) {
                $user = new User();
                $user->setPassword(password_hash('password', \PASSWORD_BCRYPT));
                $user->setRoles([]);
                $user->setAuthId(bin2hex(random_bytes(10)));
                $user->setMember($member);
                $member->setUser($user);
            }

            $user->setRoles(
                array_values(
                    array_unique(
                        array_merge($user->getRoles(), ['ROLE_OAUTH2_FORUM']),
                    ),
                ),
            );
        });
    }
}
