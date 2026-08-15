<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AdminTwoFactor
{
    public const SESSION_VERIFIED = '_admin_2fa_user_id';
    public const SESSION_SETUP_SECRET = '_admin_2fa_setup_secret';

    public function isAdmin(User $user): bool
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function isVerified(SessionInterface $session, User $user): bool
    {
        return (int) $session->get(self::SESSION_VERIFIED, 0) === (int) $user->getId();
    }

    public function markVerified(Request $request, User $user): void
    {
        $session = $request->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_VERIFIED, $user->getId());
        $session->remove(self::SESSION_SETUP_SECRET);
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_VERIFIED);
        $session->remove(self::SESSION_SETUP_SECRET);
    }
}
