<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CaptchaChallenge extends Constraint
{
    public string $message = 'Le code de sécurité est incorrect ou a expiré.';
}
