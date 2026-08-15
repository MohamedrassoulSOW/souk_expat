<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraint;

final class PasswordConstraints
{
    /**
     * @return list<Constraint>
     */
    public static function newPassword(): array
    {
        return [
            new NotBlank(message: 'Veuillez entrer un mot de passe'),
            new Length(
                min: 8,
                minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères',
                max: 4096,
            ),
            new NotCompromisedPassword(
                message: 'Ce mot de passe apparaît dans une fuite de données. Choisissez-en un autre.',
                skipOnError: true,
            ),
        ];
    }
}
