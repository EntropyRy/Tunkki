<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nakki;

use App\Factory\NakkiDefinitionFactory;
use App\Repository\NakkiDefinitionRepository;
use App\Tests\_Base\FixturesWebTestCase;
use App\Twig\Components\Nakki\DefinitionForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DefinitionFormTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->primeSession();
    }

    public function testNewDefinitionActivatesComponent(): void
    {
        $component = $this->component();
        $component->newDefinition();

        self::assertTrue($component->active);
        self::assertNull($component->definitionId);
    }

    public function testEditDefinitionLoadsEntity(): void
    {
        $definition = NakkiDefinitionFactory::new()->create();
        $component = $this->component();

        $component->editDefinition(definitionId: $definition->getId());

        self::assertSame($definition->getId(), $component->definitionId);
        $component->definitionIdUpdated();
    }

    private function component(): DefinitionForm
    {
        $container = self::getContainer();

        return new DefinitionForm(
            Forms::createFormFactoryBuilder()->getFormFactory(),
            $container->get(EntityManagerInterface::class),
            $container->get(NakkiDefinitionRepository::class),
            $container->get(TranslatorInterface::class),
        );
    }

    private function primeSession(): void
    {
        $container = self::getContainer();
        if (!$container->has('request_stack')) {
            return;
        }

        $session = null;
        if ($container->has('session')) {
            $session = $container->get('session');
        } elseif ($container->has('session.factory')) {
            $session = $container->get('session.factory')->createSession();
        }

        if ($session instanceof SessionInterface && !$session->isStarted()) {
            $session->start();
        }

        $request = Request::create('/');
        if ($session instanceof SessionInterface) {
            $request->setSession($session);
        }

        $container->get('request_stack')->push($request);
    }
}
