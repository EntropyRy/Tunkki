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
use Symfony\UX\LiveComponent\LiveResponder;
use Zenstruck\Foundry\Test\Factories;

final class DefinitionFormTest extends FixturesWebTestCase
{
    use Factories;

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

    public function testSavePersistsDefinition(): void
    {
        $component = $this->component();
        $component->formValues = [
            'nameFi' => 'Test FI',
            'nameEn' => 'Test EN',
            'descriptionFi' => 'FI desc',
            'descriptionEn' => 'EN desc',
            'onlyForActiveMembers' => false,
        ];

        $component->save();

        self::assertNotNull($component->definitionId);
        self::assertNotNull($component->notice);

        $repository = self::getContainer()->get(NakkiDefinitionRepository::class);
        self::assertNotNull($repository->find($component->definitionId));
    }

    public function testResolveDefinitionBranches(): void
    {
        $component = $this->component();

        $method = new \ReflectionMethod(DefinitionForm::class, 'resolveDefinition');
        $method->setAccessible(true);

        $component->definitionId = null;
        $newDefinition = $method->invoke($component);
        self::assertInstanceOf(\App\Entity\NakkiDefinition::class, $newDefinition);

        $property = new \ReflectionProperty(DefinitionForm::class, 'definition');
        $property->setAccessible(true);
        $property->setValue($component, null);

        $missingId = 999999;
        $component->definitionId = $missingId;
        $resolvedMissing = $method->invoke($component);
        self::assertInstanceOf(\App\Entity\NakkiDefinition::class, $resolvedMissing);
        self::assertNull($component->definitionId);

        $cached = new \App\Entity\NakkiDefinition();
        $property->setValue($component, $cached);

        $resolvedCached = $method->invoke($component);
        self::assertSame($cached, $resolvedCached);
    }

    private function component(): DefinitionForm
    {
        $container = self::getContainer();

        $component = new DefinitionForm(
            Forms::createFormFactoryBuilder()->getFormFactory(),
            $container->get(EntityManagerInterface::class),
            $container->get(NakkiDefinitionRepository::class),
            $container->get(TranslatorInterface::class),
        );

        $component->setLiveResponder(new LiveResponder());

        return $component;
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
