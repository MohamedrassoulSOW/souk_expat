<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Entity\City;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApiResourceFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function user(User $user, bool $private = false): array
    {
        $lastName = $user->getLastName();

        $data = [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $private
                ? $lastName
                : ($lastName ? mb_substr($lastName, 0, 1) . '.' : null),
            'avatarUrl' => $this->avatarUrl($user),
        ];

        if ($private) {
            $data['email'] = $user->getEmail();
            $data['roles'] = $user->getRoles();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function category(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'icon' => $category->getIcon(),
            'imageUrl' => $category->getImageName()
                ? $this->absolute('/uploads/categories/' . $category->getImageName())
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function city(City $city): array
    {
        return [
            'id' => $city->getId(),
            'name' => $city->getName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function annonce(Annonce $annonce, bool $detailed = false): array
    {
        $images = [];
        foreach ($annonce->getAnnonceImages() as $image) {
            $name = $image->getImadeName();
            if ($name) {
                $images[] = $this->absolute('/uploads/annonces/' . $name);
            }
        }

        $data = [
            'id' => $annonce->getId(),
            'title' => $annonce->getTitle(),
            'slug' => $annonce->getSlug(),
            'price' => $annonce->getPrice(),
            'currency' => 'MAD',
            'status' => $annonce->getStatus(),
            'createdAt' => $annonce->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'city' => $this->city($annonce->getCity()),
            'category' => $this->category($annonce->getCategory()),
            'seller' => $annonce->getUser() ? $this->user($annonce->getUser()) : null,
            'imageUrl' => $images[0] ?? null,
            'images' => $images,
        ];

        if ($detailed) {
            $data['description'] = $annonce->getDescription();
            $data['approvedAt'] = $annonce->getApprovedAt()?->format(\DateTimeInterface::ATOM);
            $data['updatedAt'] = $annonce->getUpdatedAt()?->format(\DateTimeInterface::ATOM);
            $data['webUrl'] = $this->urlGenerator->generate(
                'app_annonce_show',
                ['id' => $annonce->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $data;
    }

    private function avatarUrl(User $user): ?string
    {
        if (!$user->getAvatar()) {
            return $this->absolute('/uploads/avatars/avatar.png');
        }

        return $this->absolute('/uploads/avatars/' . $user->getAvatar());
    }

    private function absolute(string $path): string
    {
        $base = rtrim($this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');

        return $base . $path;
    }
}
