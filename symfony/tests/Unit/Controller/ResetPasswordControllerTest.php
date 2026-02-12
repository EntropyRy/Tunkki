<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ResetPasswordController;
use App\Entity\Member;
use App\Entity\User;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Unit tests for ResetPasswordController.
 *
 * Focus on request/reset flow branching without real Symfony kernel.
 */
final class ResetPasswordControllerTest extends TestCase
{
    private TestableResetPasswordController $controller;
    private FakeResetPasswordHelper $helper;
    private EntityManagerInterface $em;
    private MemberRepository $repo;

    protected function setUp(): void
    {
        $this->helper = new FakeResetPasswordHelper();
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->repo = $this->createStub(MemberRepository::class);

        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
    }

    public function testRequestRendersWhenNotSubmitted(): void
    {
        $form = $this->makeRequestForm(false, false, 'test@example.com');
        $this->controller->setForm($form);

        $mailer = $this->createStub(MailerInterface::class);
        $response = $this->controller->request(new Request(), $mailer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }

    public function testRequestWithUnknownEmailRedirectsToCheckEmail(): void
    {
        $form = $this->makeRequestForm(true, true, 'unknown@example.com');
        $this->controller->setForm($form);

        $this->repo = $this->createStub(MemberRepository::class);
        $this->repo->method('findOneBy')->willReturn(null);
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
        $this->controller->setForm($form);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $response = $this->controller->request(new Request(), $mailer);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_check_email', $response->headers->get('Location'));
    }

    public function testRequestWithValidMemberSendsEmail(): void
    {
        $token = $this->makeResetToken('token_ok');
        $this->helper->generatedToken = $token;

        $member = $this->createStub(Member::class);
        $member->method('getEmail')->willReturn('member@example.com');
        $member->method('getUser')->willReturn($this->createStub(User::class));
        $this->repo = $this->createStub(MemberRepository::class);
        $this->repo->method('findOneBy')->willReturn($member);
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);

        $form = $this->makeRequestForm(true, true, 'member@example.com');
        $this->controller->setForm($form);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $response = $this->controller->request(new Request(), $mailer);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_check_email', $response->headers->get('Location'));
        $this->assertTrue($this->helper->generateResetCalled);
        $this->assertInstanceOf(
            ResetPasswordToken::class,
            $this->controller->getSession()->get('ResetPasswordToken'),
        );
    }

    public function testRequestWhenTokenGenerationFailsRedirects(): void
    {
        $exception = new class('fail') extends \RuntimeException implements ResetPasswordExceptionInterface {
            public function getReason(): string
            {
                return 'reset_password_error';
            }
        };

        $this->helper->generateResetException = $exception;

        $member = $this->createStub(Member::class);
        $member->method('getEmail')->willReturn('member@example.com');
        $member->method('getUser')->willReturn($this->createStub(User::class));
        $this->repo = $this->createStub(MemberRepository::class);
        $this->repo->method('findOneBy')->willReturn($member);
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);

        $form = $this->makeRequestForm(true, true, 'member@example.com');
        $this->controller->setForm($form);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $response = $this->controller->request(new Request(), $mailer);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_check_email', $response->headers->get('Location'));
    }

    public function testCheckEmailUsesSessionTokenWhenPresent(): void
    {
        $token = $this->makeResetToken('token_session');
        $this->controller->getSession()->set('ResetPasswordToken', $token);

        $this->helper->generateFakeCalled = false;
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
        $this->controller->getSession()->set('ResetPasswordToken', $token);

        $response = $this->controller->checkEmail();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertFalse($this->helper->generateFakeCalled);
    }

    public function testCheckEmailGeneratesFakeTokenWhenMissing(): void
    {
        $token = $this->makeResetToken('token_fake');
        $this->helper->fakeToken = $token;
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);

        $response = $this->controller->checkEmail();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($this->helper->generateFakeCalled);
    }

    public function testResetWithTokenInUrlRedirectsAndStores(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        $response = $this->controller->reset(new Request(), $translator, $hasher, 'token_in_url');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_reset_password', $response->headers->get('Location'));
        $this->assertSame(
            'token_in_url',
            $this->controller->getSession()->get('ResetPasswordPublicToken'),
        );
    }

    public function testResetWithoutTokenInSessionThrowsNotFound(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->controller->reset(new Request(), $translator, $hasher);
    }

    public function testResetWithInvalidTokenRedirectsToRequest(): void
    {
        $exception = new class('fail') extends \RuntimeException implements ResetPasswordExceptionInterface {
            public function getReason(): string
            {
                return 'reset_password_error';
            }
        };

        $this->controller->getSession()->set('ResetPasswordPublicToken', 'bad_token');

        $this->helper->validateException = $exception;
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
        $this->controller->getSession()->set('ResetPasswordPublicToken', 'bad_token');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('translated');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        $response = $this->controller->reset(new Request(), $translator, $hasher);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_forgot_password_request', $response->headers->get('Location'));
        $this->assertSame(['reset_password_error' => ['translated']], $this->controller->getFlashes());
    }

    public function testResetValidTokenRendersFormWhenNotSubmitted(): void
    {
        $this->controller->getSession()->set('ResetPasswordPublicToken', 'good_token');

        $user = $this->createStub(User::class);
        $this->helper->userForToken = $user;
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
        $this->controller->getSession()->set('ResetPasswordPublicToken', 'good_token');

        $form = $this->makeResetForm(false, false, null);
        $this->controller->setForm($form);

        $translator = $this->createStub(TranslatorInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        $response = $this->controller->reset(new Request(), $translator, $hasher);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }

    public function testResetValidTokenSubmitsAndRedirects(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('setPassword')->with('hashed');

        $this->helper->userForToken = $user;
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('flush');
        $this->controller = $this->makeController($this->helper, $this->em, $this->repo);
        $this->controller->getSession()->set('ResetPasswordPublicToken', 'good_token');

        $form = $this->makeResetForm(true, true, 'new_password');
        $this->controller->setForm($form);

        $translator = $this->createStub(TranslatorInterface::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->with($user, 'new_password')->willReturn('hashed');

        $response = $this->controller->reset(new Request(), $translator, $hasher);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_login', $response->headers->get('Location'));
        $this->assertSame('good_token', $this->helper->removedToken);
        $session = $this->controller->getSession();
        $this->assertFalse($session->has('ResetPasswordPublicToken'));
        $this->assertFalse($session->has('ResetPasswordCheckEmail'));
        $this->assertFalse($session->has('ResetPasswordToken'));
    }

    private function makeRequestForm(bool $submitted, bool $valid, string $email): FormInterface
    {
        $emailField = $this->createStub(FormInterface::class);
        $emailField->method('getData')->willReturn($email);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('get')->willReturn($emailField);

        return $form;
    }

    private function makeResetForm(bool $submitted, bool $valid, ?string $plainPassword): FormInterface
    {
        $passwordField = $this->createStub(FormInterface::class);
        $passwordField->method('getData')->willReturn($plainPassword);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('get')->willReturn($passwordField);

        return $form;
    }

    private function makeResetToken(string $token): ResetPasswordToken
    {
        return new ResetPasswordToken(
            $token,
            new \DateTimeImmutable('+1 hour'),
            time(),
        );
    }

    private function makeController(
        ResetPasswordHelperInterface $helper,
        EntityManagerInterface $em,
        MemberRepository $repo,
    ): TestableResetPasswordController {
        $controller = new TestableResetPasswordController($helper, $em, $repo);
        $controller->initSession();

        return $controller;
    }
}

final class TestableResetPasswordController extends ResetPasswordController
{
    private ?FormInterface $form = null;
    /**
     * @var array<string, string[]>
     */
    private array $flashes = [];
    private Session $session;

    public function setForm(FormInterface $form): void
    {
        $this->form = $form;
    }

    /**
     * @return array<string, string[]>
     */
    public function getFlashes(): array
    {
        return $this->flashes;
    }

    public function initSession(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new Container();
        $container->set('request_stack', $requestStack);

        $this->setContainer($container);
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    #[\Override]
    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        if (null === $this->form) {
            throw new \LogicException('Form not configured for test.');
        }

        return $this->form;
    }

    #[\Override]
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return $response ?? new Response($view);
    }

    #[\Override]
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/'.$route, $status);
    }

    #[\Override]
    protected function addFlash(string $type, mixed $message): void
    {
        if (!\array_key_exists($type, $this->flashes)) {
            $this->flashes[$type] = [];
        }
        $this->flashes[$type][] = (string) $message;
    }
}

final class FakeResetPasswordHelper implements ResetPasswordHelperInterface
{
    public ?ResetPasswordToken $fakeToken = null;
    public ?ResetPasswordToken $generatedToken = null;
    public bool $generateFakeCalled = false;
    public bool $generateResetCalled = false;
    public ?ResetPasswordExceptionInterface $generateResetException = null;
    public ?ResetPasswordExceptionInterface $validateException = null;
    public ?object $userForToken = null;
    public ?string $removedToken = null;
    public int $tokenLifetime = 3600;

    public function generateResetToken(object $user): ResetPasswordToken
    {
        if ($this->generateResetException instanceof ResetPasswordExceptionInterface) {
            throw $this->generateResetException;
        }

        $this->generateResetCalled = true;

        return $this->generatedToken
            ?? new ResetPasswordToken(
                'generated',
                new \DateTimeImmutable('+1 hour'),
                time(),
            );
    }

    public function generateFakeResetToken(?int $resetRequestLifetime = null): ResetPasswordToken
    {
        $this->generateFakeCalled = true;

        return $this->fakeToken
            ?? new ResetPasswordToken(
                'fake',
                new \DateTimeImmutable('+1 hour'),
                time(),
            );
    }

    public function validateTokenAndFetchUser(string $fullToken): object
    {
        if ($this->validateException instanceof ResetPasswordExceptionInterface) {
            throw $this->validateException;
        }

        return $this->userForToken ?? new \stdClass();
    }

    public function removeResetRequest(string $fullToken): void
    {
        $this->removedToken = $fullToken;
    }

    public function getTokenLifetime(): int
    {
        return $this->tokenLifetime;
    }
}
