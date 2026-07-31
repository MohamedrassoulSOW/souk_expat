<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Annonce;
use App\Entity\Contact;
use App\Entity\User;
use App\Mail\SiteContact;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * E-mails transactionnels — adresse depuis les paramètres du site (fallback SiteContact).
 */
final class PlatformMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly SiteSettingsService $siteSettings,
    ) {
    }

    public function contactEmail(): string
    {
        try {
            $email = trim($this->siteSettings->get()->getContactEmail());
            if ($email !== '') {
                return $email;
            }
        } catch (\Throwable) {
        }

        return SiteContact::EMAIL;
    }

    public function fromName(): string
    {
        try {
            $name = trim($this->siteSettings->get()->getSiteName());
            if ($name !== '') {
                return $name;
            }
        } catch (\Throwable) {
        }

        return SiteContact::FROM_NAME;
    }

    public function fromAddress(): Address
    {
        return new Address($this->contactEmail(), $this->fromName());
    }

    public function sendPasswordReset(User $user, ResetPasswordToken $resetToken): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to((string) $user->getEmail())
            ->subject($this->fromName() . ' — Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->textTemplate('reset_password/email.txt.twig')
            ->context([
                'resetToken' => $resetToken,
                'siteContactEmail' => $this->contactEmail(),
            ]);

        return $this->dispatch($email);
    }

    public function sendContactToInbox(Contact $contact): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo(new Address((string) $contact->getEmail(), (string) $contact->getName()))
            ->to($this->fromAddress())
            ->subject('Contact ' . $this->fromName() . ' — ' . (string) $contact->getSubject())
            ->htmlTemplate('emails/contact_inbox.html.twig')
            ->context([
                'contact' => $contact,
                'siteContactEmail' => $this->contactEmail(),
            ]);

        return $this->dispatch($email);
    }

    public function sendContactReply(string $toEmail, string $toName, string $originalSubject, string $replyBody): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to(new Address($toEmail, $toName !== '' ? $toName : $toEmail))
            ->subject($this->fromName() . ' — Réponse : ' . $originalSubject)
            ->htmlTemplate('emails/contact_reply.html.twig')
            ->context([
                'toName' => $toName,
                'originalSubject' => $originalSubject,
                'replyBody' => $replyBody,
                'siteContactEmail' => $this->contactEmail(),
            ]);

        return $this->dispatch($email);
    }

    public function sendAnnonceApproved(Annonce $annonce): bool
    {
        $user = $annonce->getUser();
        if ($user === null || !$user->getEmail()) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to((string) $user->getEmail())
            ->subject($this->fromName() . ' — Votre annonce a été approuvée')
            ->htmlTemplate('emails/annonce_status.html.twig')
            ->context([
                'annonce' => $annonce,
                'user' => $user,
                'approved' => true,
                'siteContactEmail' => $this->contactEmail(),
            ]);

        return $this->dispatch($email);
    }

    public function sendAnnonceRejected(Annonce $annonce): bool
    {
        $user = $annonce->getUser();
        if ($user === null || !$user->getEmail()) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to((string) $user->getEmail())
            ->subject($this->fromName() . ' — Votre annonce n’a pas été validée')
            ->htmlTemplate('emails/annonce_status.html.twig')
            ->context([
                'annonce' => $annonce,
                'user' => $user,
                'approved' => false,
                'siteContactEmail' => $this->contactEmail(),
            ]);

        return $this->dispatch($email);
    }

    private function dispatch(TemplatedEmail $email): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec d’envoi e-mail SoukExpat', [
                'subject' => $email->getSubject(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
