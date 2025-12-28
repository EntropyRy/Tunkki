<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ProfileController;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Testable subclass for unit testing ProfileController.
 *
 * Overrides AbstractController dependencies to avoid needing full Symfony setup.
 */
class TestableProfileController extends ProfileController
{
    private ?User $testUser = null;
    private RouterInterface $testRouter;
    private FlashBagInterface $testFlashBag;
    private ?FormInterface $testForm = null;

    public function setTestDependencies(
        RouterInterface $router,
        FlashBagInterface $flashBag,
        ?User $user = null,
    ): void {
        $this->testRouter = $router;
        $this->testFlashBag = $flashBag;
        $this->testUser = $user;
    }

    public function setTestForm(FormInterface $form): void
    {
        $this->testForm = $form;
    }

    #[\Override]
    protected function getUser(): ?User
    {
        return $this->testUser;
    }

    #[\Override]
    protected function addFlash(string $type, mixed $message): void
    {
        $this->testFlashBag->add($type, $message);
    }

    #[\Override]
    protected function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
    ): RedirectResponse {
        $url = $this->testRouter->generate($route, $parameters);

        return new RedirectResponse($url, $status);
    }

    #[\Override]
    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        if (null !== $this->testForm) {
            return $this->testForm;
        }

        throw new \LogicException('Test form not set');
    }

    #[\Override]
    protected function render(string $view, array $parameters = [], ?\Symfony\Component\HttpFoundation\Response $response = null): \Symfony\Component\HttpFoundation\Response
    {
        return $response ?? new \Symfony\Component\HttpFoundation\Response('rendered');
    }
}

/**
 * Unit tests for ProfileController edge cases.
 *
 * These tests cover defensive code paths that cannot be reached through
 * functional tests because Symfony's form component guarantees data types.
 */
#[CoversClass(ProfileController::class)]
final class ProfileControllerTest extends TestCase
{
    private TestableProfileController $controller;
    private RouterInterface $router;
    private FlashBagInterface $flashBag;

    protected function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->flashBag = $this->createStub(FlashBagInterface::class);

        $this->controller = new TestableProfileController();
        $this->controller->setTestDependencies($this->router, $this->flashBag);
    }

    /**
     * Test password returns early when plainPassword is empty.
     * Covers lines 248-250.
     */
    public function testPasswordReturnsEarlyWhenPlainPasswordIsEmpty(): void
    {
        // Create mock user
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('fi');

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $this->controller->setTestDependencies($this->router, $this->flashBag, $user);

        // Create form that returns empty password
        $plainPasswordField = $this->createStub(FormInterface::class);
        $plainPasswordField->method('getData')->willReturn(''); // Empty password

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($user);
        $form->method('get')->with('plainPassword')->willReturn($plainPasswordField);

        $this->controller->setTestForm($form);

        $request = new Request();

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        // Execute the password action
        $response = $this->controller->password($request, $hasher, $em);

        // Should return a response (the guard clause renders the form)
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
        // Not a redirect - it renders the password form again
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * Test password returns early when plainPassword is null.
     * Covers lines 248-250.
     */
    public function testPasswordReturnsEarlyWhenPlainPasswordIsNull(): void
    {
        // Create mock user
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('en');

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $this->controller->setTestDependencies($this->router, $this->flashBag, $user);

        // Create form that returns null password
        $plainPasswordField = $this->createStub(FormInterface::class);
        $plainPasswordField->method('getData')->willReturn(null); // Null password

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($user);
        $form->method('get')->with('plainPassword')->willReturn($plainPasswordField);

        $this->controller->setTestForm($form);

        $request = new Request();

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        // Execute the password action
        $response = $this->controller->password($request, $hasher, $em);

        // Should return a response (the guard clause renders the form)
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
        // Not a redirect - it renders the password form again
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }
}
