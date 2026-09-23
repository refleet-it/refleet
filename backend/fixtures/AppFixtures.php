<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Fixtures\Stories\FleetStory;
use App\Fixtures\Stories\IdentityColorsStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        IdentityColorsStory::load();
        $manager->flush();
        $manager->clear();

        FleetStory::load();
        $manager->flush();
        $manager->clear();
    }
}
