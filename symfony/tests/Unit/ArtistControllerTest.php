<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\ArtistController;
use App\Entity\Artist;
use App\Entity\Member;
use App\Entity\User;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pure unit tests for ArtistController logic paths.
 *
 * We do not boot the Symfony kernel; instead we override framework-dependent
 * methods (render, redirectToRoute, generateUrl, addFlash, getUser) in a
 * test subclass to observe controller behavior in isolation.
 */
final class ArtistControllerTest extends TestCase
{
    /**
     * Create a controller test double with an injected "current user".
     */
    private function makeController(User $user): TestableArtistController
    {
        return new TestableArtistController($user);
    }

    private function makeUserWithMember(int $memberId = 1): User
    {
        $member = new Member();
        // Member class likely has setters (not shown here), but we only need an ID.
        // We reflectively set the id to simulate a persisted entity.
        $this->setPrivateProperty($member, 'id', $memberId);

        $user = new User();
        $user->setMember($member);

        return $user;
    }

    /**
     * Helper to set a private/protected property via reflection.
     */
    private function setPrivateProperty(
        object $object,
        string $prop,
        mixed $value,
    ): void {
        $ref = new \ReflectionClass($object);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass();
        }
        if (!$ref) {
            self::fail("Property {$prop} not found via reflection.");
        }
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($object, $value);
    }

    public function testCreateInitialRenderShowsForm(): void
    {
        $user = $this->makeUserWithMember();
        $controller = $this->makeController($user);

        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);

        /** @var FormFactoryInterface&MockObject $formFactory */
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(self::anything(), self::isInstanceOf(Artist::class))
            ->willReturn($form);

        /** @var MattermostNotifierService&MockObject $mm */
        $mm = $this->createMock(MattermostNotifierService::class);
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $request = new Request();

        $response = $controller->create($request, $formFactory, $mm, $em);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('render', $controller->lastAction);
        self::assertArrayHasKey('form', $controller->lastRenderParams);
        self::assertEmpty($controller->flashes, 'No flash messages expected.');
    }

    public function testCreateSubmittedValidMissingPictureAddsWarningFlash(): void
    {
        $user = $this->makeUserWithMember();
        $controller = $this->makeController($user);

        $artist = new Artist();
        $artist->setName('Test Artist')->setType('DJ'); // no picture set

        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($artist);

        /** @var FormFactoryInterface&MockObject $formFactory */
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        /** @var MattermostNotifierService&MockObject $mm */
        $mm = $this->createMock(MattermostNotifierService::class);
        $mm->expects(self::never())->method('sendToMattermost');

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $request = new Request();

        $response = $controller->create($request, $formFactory, $mm, $em);

        self::assertInstanceOf(Response::class, $response);
        self::assertArrayHasKey('warning', $controller->flashes);
        self::assertSame(
            ['artist.form.pic_missing'],
            $controller->flashes['warning'],
        );
        self::assertSame('render', $controller->lastAction);
    }

    public function testCreateSubmittedValidWithPicturePersistsAndRedirects(): void
    {
        $user = $this->makeUserWithMember();
        $controller = $this->makeController($user);

        $artist = new Artist();
        $artist->setName('Live Act')->setType('band');
        // Create a real SonataMediaMedia instance to satisfy the type-hint
        $picture = new \App\Entity\Sonata\SonataMediaMedia();
        $picture->setProviderName('sonata.media.provider.image');
        $picture->setContext('artist');
        $picture->setName('unit-test-picture');
        $picture->setEnabled(true);
        $picture->setProviderStatus(1);
        $picture->setProviderReference('unit-test-picture-ref');
        // Assign the media entity as the artist picture
        $artist->setPicture($picture);

        // Need to fake an ID after persist so generateUrl can embed artist ID
        $this->setPrivateProperty($artist, 'id', 123);

        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($artist);

        /** @var FormFactoryInterface&MockObject $formFactory */
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        /** @var MattermostNotifierService&MockObject $mm */
        $mm = $this->createMock(MattermostNotifierService::class);
        $mm->expects(self::once())
            ->method('sendToMattermost')
            ->with(
                self::callback(function (string $msg): bool {
                    return str_contains(
                        $msg,
                        'New artist! type: band, name: Live Act',
                    )
                        && str_contains($msg, '[FI](')
                        && str_contains($msg, '[EN](');
                }),
                'yhdistys',
            );

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($artist);
        $em->expects(self::once())->method('flush');

        $request = new Request();
        // Provide a session-like referer to test that removal path too
        $session = new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage(),
        );
        $request->setSession($session);

        $response = $controller->create($request, $formFactory, $mm, $em);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('redirect', $controller->lastAction);
        self::assertArrayHasKey('success', $controller->flashes);
        self::assertSame(['edited'], $controller->flashes['success']);
    }

    public function testStreamsWithDifferentOwnerRedirectsAndWarning(): void
    {
        $currentUser = $this->makeUserWithMember(1);
        $controller = $this->makeController($currentUser);

        $foreignMember = new Member();
        $this->setPrivateProperty($foreignMember, 'id', 2);

        $artist = new Artist();
        $artist->setName('Other Artist');
        $artist->setMember($foreignMember);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $controller->streams(new Request(), $em, $artist);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertArrayHasKey('warning', $controller->flashes);
        self::assertSame(
            ['stream.artist.not_yours'],
            $controller->flashes['warning'],
        );
        self::assertSame('redirect', $controller->lastAction);
    }

    public function testStreamsWithSameOwnerRenders(): void
    {
        $currentUser = $this->makeUserWithMember(10);
        $controller = $this->makeController($currentUser);

        $member = new Member();
        $this->setPrivateProperty($member, 'id', 10);

        $artist = new Artist();
        $artist->setName('Owned Artist');
        $artist->setMember($member);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $controller->streams(new Request(), $em, $artist);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('render', $controller->lastAction);
        self::assertArrayNotHasKey('warning', $controller->flashes);
    }
}

/**
 * Test double for ArtistController overriding framework-integrated methods.
 */
class TestableArtistController extends ArtistController
{
    public string $lastAction = '';
    public array $lastRenderParams = [];
    public array $flashes = [];

    public function __construct(private readonly User $testUser)
    {
    }

    protected function render(
        string $view,
        array $parameters = [],
        ?Response $response = null,
    ): Response {
        $this->lastAction = 'render';
        $this->lastRenderParams = $parameters;

        return $response ?? new Response('rendered:'.$view);
    }

    protected function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
    ): RedirectResponse {
        $this->lastAction = 'redirect';

        return new RedirectResponse('/_route/'.$route, $status);
    }

    protected function generateUrl(
        string $route,
        array $parameters = [],
        int $referenceType = 1,
    ): string {
        // Simplified deterministic URL for assertions
        return 'http://unit.test/'.$route;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashes[$type] ??= [];
        $this->flashes[$type][] = $message;
    }

    public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
    {
        return $this->testUser;
    }
}
