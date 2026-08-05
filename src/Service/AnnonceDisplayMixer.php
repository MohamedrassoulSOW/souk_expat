<?php

namespace App\Service;

final class AnnonceDisplayMixer
{
    /**
     * Réordonne les annonces pour les mélanger et éviter de voir plus de
     * $maxSameUserInRow annonces successives d'un même utilisateur.
     *
     * @param list<object> $annonces
     * @return list<object>
     */
    public function mix(array $annonces, int $maxSameUserInRow = 3): array
    {
        if ($annonces === []) {
            return [];
        }

        $groups = [];
        foreach ($annonces as $annonce) {
            $userId = $this->getUserId($annonce);
            $groupKey = $userId !== null ? 'user:' . $userId : 'guest';

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }

            $groups[$groupKey][] = $annonce;
        }

        $result = [];
        $lastUserKey = null;
        $sameUserStreak = 0;

        while ($groups !== []) {
            $availableGroups = [];
            foreach ($groups as $groupKey => $groupItems) {
                if ($groupItems === []) {
                    continue;
                }

                $groupUserKey = $groupKey;
                $isSameUserAsLast = $groupUserKey === $lastUserKey;

                if (!$isSameUserAsLast || $sameUserStreak < $maxSameUserInRow) {
                    $availableGroups[] = $groupKey;
                }
            }

            if ($availableGroups === []) {
                $availableGroups = array_keys(array_filter($groups, static fn (array $items): bool => $items !== []));
            }

            if ($availableGroups === []) {
                break;
            }

            shuffle($availableGroups);
            $groupKey = $availableGroups[0];
            $result[] = array_shift($groups[$groupKey]);

            $groupUserKey = $groupKey;
            if ($groupUserKey === $lastUserKey) {
                ++$sameUserStreak;
            } else {
                $lastUserKey = $groupUserKey;
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
}
