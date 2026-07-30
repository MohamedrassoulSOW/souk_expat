<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Adresse unique pour l’envoi et la réception des e-mails de la plateforme
 * (contact, réinitialisation de mot de passe, modération d’annonces, etc.).
 */
final class SiteContact
{
    public const string EMAIL = 'contact@soukexpat.com';

    public const string FROM_NAME = 'SoukExpat';
}
