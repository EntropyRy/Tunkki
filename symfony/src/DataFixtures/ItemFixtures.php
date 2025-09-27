<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Item;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Provides Item fixtures for dashboard blocks.
 * Creates both working and broken items to test BrokenItemsBlock functionality.
 */
final class ItemFixtures extends Fixture
{
    public const WORKING_ITEM_REFERENCE = "fixture_item_working";
    public const BROKEN_ITEM_REFERENCE = "fixture_item_broken";

    public function load(ObjectManager $manager): void
    {
        // Create a working item
        $workingItem = new Item();
        $workingItem->setName("Working Microphone");
        $workingItem->setManufacturer("Shure");
        $workingItem->setModel("SM58");
        $workingItem->setDescription("A reliable dynamic microphone");
        $workingItem->setNeedsFixing(false);
        $workingItem->setToSpareParts(false);

        $manager->persist($workingItem);
        $this->addReference(self::WORKING_ITEM_REFERENCE, $workingItem);

        // Create broken items for the BrokenItemsBlock
        $brokenMixer = new Item();
        $brokenMixer->setName("Broken Audio Mixer");
        $brokenMixer->setManufacturer("Yamaha");
        $brokenMixer->setModel("MG12XU");
        $brokenMixer->setDescription("Channel 3 not working properly");
        $brokenMixer->setNeedsFixing(true);
        $brokenMixer->setToSpareParts(false);

        $manager->persist($brokenMixer);

        $brokenCable = new Item();
        $brokenCable->setName("Damaged XLR Cable");
        $brokenCable->setManufacturer("Neutrik");
        $brokenCable->setModel("NC3MXX");
        $brokenCable->setDescription("Connector is loose");
        $brokenCable->setNeedsFixing(true);
        $brokenCable->setToSpareParts(false);

        $manager->persist($brokenCable);
        $this->addReference(self::BROKEN_ITEM_REFERENCE, $brokenCable);

        $manager->flush();
    }
}