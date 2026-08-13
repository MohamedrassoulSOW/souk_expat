<?php

namespace App\Service;

final class AnnonceDisplayMixer
{
    /**
     * Mélange les annonces (aléatoire) en évitant plus de $maxSameUserInRow
     * annonces successives du même utilisateur.
     *
     * @param list<object> $annonces
     * @return list<object>
     */
    public function mix(array $annonces, int $maxSameUserInRow = 3): array
    {
        if ($annonces === []) {
            return [];
        }

        if ($maxSameUserInRow < 1) {
            $maxSameUserInRow = 1;
        }

        $groups = [];
        foreach ($annonces as $annonce) {
            $userId = $this->getUserId($annonce);
            $groupKey = $userId !== null ? 'user:'.$userId : 'guest:'.$this->getAnnonceId($annonce);

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }

            $groups[$groupKey][] = $annonce;
        }

        // Aléatoire au sein de chaque vendeur
        foreach ($groups as &$groupItems) {
            shuffle($groupItems);
        }
        unset($groupItems);

        $result = [];
        $lastUserKey = null;
        $sameUserStreak = 0;

        while ($groups !== []) {
            $availableGroups = [];
            foreach ($groups as $groupKey => $groupItems) {
                if ($groupItems === []) {
                    continue;
                }

                $isSameUserAsLast = $groupKey === $lastUserKey;
                if (!$isSameUserAsLast || $sameUserStreak < $maxSameUserInRow) {
                    $availableGroups[] = $groupKey;
                }
            }

            // Plus d'autre vendeur disponible : on continue avec le même (inévitable)
            if ($availableGroups === []) {
                $availableGroups = array_keys(array_filter(
                    $groups,
                    static fn (array $items): bool => $items !== []
                ));
            }

            if ($availableGroups === []) {
                break;
            }

            // Préférer un autre vendeur quand c'est possible (diversité)
            $differentGroups = array_values(array_filter(
                $availableGroups,
                static fn (string $key): bool => $key !== $lastUserKey
            ));
            $pickFrom = $differentGroups !== [] ? $differentGroups : $availableGroups;

            shuffle($pickFrom);
            $groupKey = $pickFrom[0];
            $result[] = array_shift($groups[$groupKey]);

            if ($groupKey === $lastUserKey) {
                ++$sameUserStreak;
            } else {
                $lastUserKey = $groupKey;
                $sameUserStreak = 1;
            }

            if ($groups[$groupKey] === []) {
                unset($groups[$groupKey]);
            }
        }

        return $result;
    }

    private function getUserId(object $annonce): ?int
    {
        if (!method_exists($annonce, 'getUser')) {
            return null;
        }

        $user = $annonce->getUser();
        if ($user === null || !method_exists($user, 'getId')) {
            return null;
        }

        return $user->getId();
    }

    private function getAnnonceId(object $annonce): string
    {
        if (method_exists($annonce, 'getId') && $annonce->getId() !== null) {
            return (string) $annonce->getId();
        }

        return spl_object_hash($annonce);
    }
}
