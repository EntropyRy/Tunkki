<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Member;
use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifierTest extends TestCase
{
    public function testSendEmailConfirmationSetsContextAndSendsEmail(): void
    {
        $verifyEmailHelper = $this->createMock(
            VerifyEmailHelperInterface::class,
        );
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $member = new Member()
            ->setFirstname('Alice')
            ->setLastname('Doe')
            ->setEmail('alice@example.test');
        $user = new User()->setMember($member);

        // Set private id via reflection for deterministic expectations
        $this->setPrivateProperty($user, 'id', 123);

        $extraParams = ['id' => 123];

        // Use real signature components (class is final) with deterministic values
        $signatureComponents = new VerifyEmailSignatureComponents(
            new \DateTimeImmutable('+1 hour'),
            'https://example.test/verify?token=abc123',
            time(),
        );

        $verifyEmailHelper
            ->expects(self::once())
            ->method('generateSignature')
            ->with(
                'app_verify_email',
                (string) $user->getId(),
                $user->getEmail() ?? '',
                $extraParams,
            )
            ->willReturn($signatureComponents);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static function (TemplatedEmail $email): bool {
                    $ctx = $email->getContext();

                    // Assert context enrichment
                    if (
                        !isset(
                            $ctx['signedUrl'],
                            $ctx['expiresAtMessageKey'],
                            $ctx['expiresAtMessageData'],
                        )
                    ) {
                        return false;
                    }

                    if (
                        'https://example.test/verify?token=abc123' !==
                        $ctx['signedUrl']
                    ) {
                        return false;
                    }

                    if (
                        !\is_string($ctx['expiresAtMessageKey'])
                        || '' === $ctx['expiresAtMessageKey']
                    ) {
                        return false;
                    }

                    if (!\is_array($ctx['expiresAtMessageData'])) {
                        return false;
                    }

                    $count = $ctx['expiresAtMessageData']['%count%'] ?? null;
                    if (!\is_int($count) || $count < 1) {
                        return false;
                    }

                    return true;
                }),
            );

        $email = new TemplatedEmail()
            ->to('alice@example.test')
            ->subject('Verify your email')
            ->htmlTemplate('emails/verify_email.html.twig');

        $svc = new EmailVerifier($verifyEmailHelper, $mailer, $em);
        $svc->sendEmailConfirmation(
            'app_verify_email',
            $user,
            $email,
            $extraParams,
        );
    }

    public function testHandleEmailConfirmationForAuthenticatedUserMarksMemberVerifiedAndFlushes(): void
    {
        $verifyEmailHelper = new class implements VerifyEmailHelperInterface {
            public function generateSignature(
                string $verifyEmailRouteName,
                string $userId,
                string $userEmail,
                array $extraParams = [],
            ): VerifyEmailSignatureComponents {
                throw new \LogicException('not used in this test');
            }

            public function validateEmailConfirmation(
                string $uri,
                string $userId,
                string $userEmail,
            ): void {
                // no-op
            }

            public function validateEmailConfirmationFromRequest(
                Request $request,
                string $userId,
                string $userEmail,
            ): void {
                // no-op (treated as successful validation)
            }
        };
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $member = new Member()
            ->setFirstname('Bob')
            ->setLastname('Smith')
            ->setEmail('bob@example.test');
        self::assertFalse($member->isEmailVerified());

        $user = new User()->setMember($member);
        $this->setPrivateProperty($user, 'id', 42);

        $request = Request::create('/verify-email?signature=xyz');

        // validation handled by helper stub (no-op)

        $em->expects(self::once())->method('flush');

        $svc = new EmailVerifier($verifyEmailHelper, $mailer, $em);
        $svc->handleEmailConfirmationForAuthenticatedUser($request, $user);

        self::assertTrue(
            $member->isEmailVerified(),
            'Member must be marked verified after successful validation.',
        );
    }

    public function testHandleEmailConfirmationAnonymousMarksMemberVerifiedAndFlushes(): void
    {
        $verifyEmailHelper = new class implements VerifyEmailHelperInterface {
            public function generateSignature(
                string $verifyEmailRouteName,
                string $userId,
                string $userEmail,
                array $extraParams = [],
            ): VerifyEmailSignatureComponents {
                throw new \LogicException('not used in this test');
            }

            public function validateEmailConfirmation(
                string $uri,
                string $userId,
                string $userEmail,
            ): void {
                // no-op
            }

            public function validateEmailConfirmationFromRequest(
                Request $request,
                string $userId,
                string $userEmail,
            ): void {
                // no-op (treated as successful validation)
            }
        };
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $member = new Member()
            ->setFirstname('Carol')
            ->setLastname('Jones')
            ->setEmail('carol@example.test');
        self::assertFalse($member->isEmailVerified());

        $user = new User()->setMember($member);
        $this->setPrivateProperty($user, 'id', 777);

        $request = Request::create('/verify-email?signature=anon');

        // validation handled by helper stub (no-op)

        $em->expects(self::once())->method('flush');

        $svc = new EmailVerifier($verifyEmailHelper, $mailer, $em);
        $svc->handleEmailConfirmationAnonymous($request, $user);

        self::assertTrue(
            $member->isEmailVerified(),
            'Member must be marked verified after anonymous validation.',
        );
    }

    private function setPrivateProperty(
        object $object,
        string $property,
        mixed $value,
    ): void {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
