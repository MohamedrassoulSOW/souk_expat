<?php

declare(strict_types=1);

namespace App\Validator;

use App\Security\ImageCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CaptchaChallengeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ImageCaptcha $captcha,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CaptchaChallenge) {
            throw new UnexpectedTypeException($constraint, CaptchaChallenge::class);
        }

        $session = $this->requestStack->getSession();
        if (!\is_string($value) || !$this->captcha->isValid($session, $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
