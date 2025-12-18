<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\Email;
use App\Enum\EmailPurpose;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for Email entity lifecycle callbacks (createdAt, updatedAt).
 *
 * @covers \App\Entity\Email
 */
final class EmailLifecycleTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreatedAtAndUpdatedAtSetOnPersist(): void
    {
        $beforeCreate = new \DateTimeImmutable('-1 second');

        $email = new Email();
        $email->setSubject('Test Email');
        $email->setBody('<p>Test body</p>');
        $email->setPurpose(EmailPurpose::TIEDOTUS);

        $this->em->persist($email);
        $this->em->flush();

        $afterCreate = new \DateTimeImmutable('+1 second');

        // Verify createdAt was set by PrePersist lifecycle callback
        $this->assertGreaterThanOrEqual(
            $beforeCreate,
            $email->getCreatedAt(),
            'createdAt should be set during persist'
        );
        $this->assertLessThanOrEqual(
            $afterCreate,
            $email->getCreatedAt(),
            'createdAt should be set to current time'
        );

        // Verify updatedAt was set by PrePersist lifecycle callback
        $this->assertGreaterThanOrEqual(
            $beforeCreate,
            $email->getUpdatedAt(),
            'updatedAt should be set during persist'
        );
        $this->assertLessThanOrEqual(
            $afterCreate,
            $email->getUpdatedAt(),
            'updatedAt should be set to current time'
        );

        // Initially, createdAt and updatedAt should be identical
        $this->assertEquals(
            $email->getCreatedAt()->getTimestamp(),
            $email->getUpdatedAt()->getTimestamp(),
            'createdAt and updatedAt should be identical on creation'
        );
    }

    public function testUpdatedAtChangesOnUpdate(): void
    {
        // Create and persist email
        $email = new Email();
        $email->setSubject('Original Subject');
        $email->setBody('<p>Original body</p>');
        $email->setPurpose(EmailPurpose::TIEDOTUS);

        $this->em->persist($email);
        $this->em->flush();

        $originalCreatedAt = $email->getCreatedAt();
        $originalUpdatedAt = $email->getUpdatedAt();

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // Update the email
        $beforeUpdate = new \DateTimeImmutable('-1 second');
        $email->setSubject('Updated Subject');
        $this->em->flush();
        $afterUpdate = new \DateTimeImmutable('+1 second');

        // Verify createdAt did NOT change
        $this->assertEquals(
            $originalCreatedAt,
            $email->getCreatedAt(),
            'createdAt should not change on update'
        );

        // Verify updatedAt DID change
        $this->assertGreaterThan(
            $originalUpdatedAt,
            $email->getUpdatedAt(),
            'updatedAt should change on update'
        );

        $this->assertGreaterThanOrEqual(
            $beforeUpdate,
            $email->getUpdatedAt(),
            'updatedAt should be set during update'
        );
        $this->assertLessThanOrEqual(
            $afterUpdate,
            $email->getUpdatedAt(),
            'updatedAt should be set to current time'
        );
    }

    public function testCreatedAtNotChangedOnMultipleUpdates(): void
    {
        // Create email
        $email = new Email();
        $email->setSubject('Test');
        $email->setBody('<p>Test</p>');
        $email->setPurpose(EmailPurpose::AKTIIVIT);

        $this->em->persist($email);
        $this->em->flush();

        $originalCreatedAt = $email->getCreatedAt();

        // Perform multiple updates
        sleep(1);
        $email->setSubject('Update 1');
        $this->em->flush();

        sleep(1);
        $email->setSubject('Update 2');
        $this->em->flush();

        // Verify createdAt remained constant
        $this->assertEquals(
            $originalCreatedAt,
            $email->getCreatedAt(),
            'createdAt should remain constant across multiple updates'
        );
    }

    public function testLifecycleCallbacksOverrideManualTimestamps(): void
    {
        // Verify that lifecycle callbacks always set timestamps (cannot be manually overridden)
        $customCreatedAt = new \DateTimeImmutable('2023-01-01 10:00:00');
        $customUpdatedAt = new \DateTimeImmutable('2023-01-02 15:30:00');

        $email = new Email();
        $email->setSubject('Lifecycle Override Test');
        $email->setBody('<p>Test</p>');
        $email->setPurpose(EmailPurpose::TIEDOTUS);

        // Set custom timestamps (but lifecycle callbacks will override them)
        $email->setCreatedAt($customCreatedAt);
        $email->setUpdatedAt($customUpdatedAt);

        $beforePersist = new \DateTimeImmutable('-1 second');
        $this->em->persist($email);
        $this->em->flush();
        $afterPersist = new \DateTimeImmutable('+1 second');

        // Verify lifecycle callbacks overrode the manual timestamps
        $this->assertGreaterThanOrEqual(
            $beforePersist,
            $email->getCreatedAt(),
            'PrePersist callback should override manual createdAt'
        );
        $this->assertLessThanOrEqual(
            $afterPersist,
            $email->getCreatedAt(),
            'PrePersist callback should set createdAt to current time'
        );

        // Manual timestamps should have been ignored
        $this->assertNotEquals(
            $customCreatedAt->format('Y-m-d'),
            $email->getCreatedAt()->format('Y-m-d'),
            'Manual createdAt should be ignored by lifecycle callback'
        );
    }

    protected function tearDown(): void
    {
        // Clean up - remove test data
        $subjects = [
            'Test Email',
            'Original Subject',
            'Updated Subject',
            'Test',
            'Update 1',
            'Update 2',
            'Lifecycle Override Test',
        ];

        $emails = $this->em->getRepository(Email::class)->findBy(['subject' => $subjects]);
        foreach ($emails as $email) {
            $this->em->remove($email);
        }

        $this->em->flush();

        parent::tearDown();
    }
}
