<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Contract;
use App\Tests\_Base\FixturesWebTestCase;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\ContractController
 */
final class ContractControllerTest extends FixturesWebTestCase
{
    private ObjectManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initSiteAwareClient();
        $this->entityManager = $this->em();
    }

    public function testFinnishPrivacyNoticeRendersMarkdown(): void
    {
        $this->createContract(
            purpose: 'privacy-notice',
            contentFi: '**Tietosuojaseloste**',
            contentEn: null,
        );

        $this->seedClientHome('fi');
        $path = $this->pathForLocale('fi', 'rekisteriseloste');
        $this->client()->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client()->assertSelectorExists('.contract-content strong');
    }

    public function testEnglishPrivacyNoticeRendersMarkdown(): void
    {
        $this->createContract(
            purpose: 'privacy-notice',
            contentFi: '**Tietosuojaseloste**',
            contentEn: '**Privacy notice**',
        );

        $this->seedClientHome('en');
        $path = $this->pathForLocale('en', 'privacy-notice');
        $this->client()->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client()->assertSelectorTextContains('.contract-content', 'Privacy notice');
    }

    public function testUnknownPurposeReturnsNotFound(): void
    {
        $this->seedClientHome('fi');
        $path = $this->pathForLocale('fi', 'unknown-purpose');
        $this->client()->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createContract(
        string $purpose,
        string $contentFi,
        ?string $contentEn,
    ): Contract {
        $contract = new Contract();
        $contract->setPurpose($purpose);
        $contract->setContentFi($contentFi);
        $contract->setContentEn($contentEn);

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    private function pathForLocale(string $locale, string $purpose): string
    {
        $router = static::getContainer()->get('router');

        return $router->generate('contract_show', [
            '_locale' => $locale,
            'purpose' => $purpose,
        ]);
    }
}
