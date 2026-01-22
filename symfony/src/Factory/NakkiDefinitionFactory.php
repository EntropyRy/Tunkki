<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\NakkiDefinition;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<NakkiDefinition>
 */
final class NakkiDefinitionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return NakkiDefinition::class;
    }

    protected function defaults(): array
    {
        return [
            'nameFi' => self::faker()->words(3, true),
            'nameEn' => self::faker()->words(3, true),
            'descriptionFi' => self::faker()->sentence(),
            'descriptionEn' => self::faker()->sentence(),
            'onlyForActiveMembers' => false,
        ];
    }

    /**
     * Set only for active members requirement.
     */
    public function onlyActiveMembers(): static
    {
        return $this->with(['onlyForActiveMembers' => true]);
    }

    /**
     * Allow all members.
     */
    public function allMembers(): static
    {
        return $this->with(['onlyForActiveMembers' => false]);
    }
}
