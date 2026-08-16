<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SiteSettingsService
{
    private const CACHE_KEY = 'app.site_settings';
    private const CACHE_TTL = 900; // 15 min

    private ?SiteSettings $requestCache = null;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    public function get(): SiteSettings
    {
        if ($this->requestCache instanceof SiteSettings) {
            return $this->requestCache;
        }

        $settings = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): SiteSettings {
            $item->expiresAfter(self::CACHE_TTL);

            $settings = $this->repository->getSingleton();
            if (!$settings) {
                $settings = new SiteSettings();
                $this->em->persist($settings);
                $this->em->flush();
            }

            // Détaché : sérialisable dans le cache filesystem Hostinger
            $this->em->detach($settings);

            return $settings;
        });

        return $this->requestCache = $settings;
    }

    public function save(SiteSettings $settings): void
    {
        $settings->touch();
        $this->em->persist($settings);
        $this->em->flush();
        $this->requestCache = $settings;
        $this->cache->delete(self::CACHE_KEY);
    }
}
