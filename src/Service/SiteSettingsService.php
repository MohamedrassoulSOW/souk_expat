<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SiteSettingsService
{
    private ?SiteSettings $cache = null;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function get(): SiteSettings
    {
        if ($this->cache instanceof SiteSettings) {
            return $this->cache;
        }

        $settings = $this->repository->getSingleton();
        if (!$settings) {
            $settings = new SiteSettings();
            $this->em->persist($settings);
            $this->em->flush();
        }

        return $this->cache = $settings;
    }

    public function save(SiteSettings $settings): void
    {
        $settings->touch();
        $this->em->persist($settings);
        $this->em->flush();
        $this->cache = $settings;
    }
}
