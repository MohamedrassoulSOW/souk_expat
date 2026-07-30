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
 * Tous les e-mails transactionnels partent de / répondent via contact@soukexpat.com.
 */
final class PlatformMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fromAddress(): Address
    {
        return new Address(SiteContact::EMAIL, SiteContact::FROM_NAME);
    }

    public function sendPasswordReset(User $user, ResetPasswordToken $resetToken): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to((string) $user->getEmail())
            ->subject('SoukExpat — Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->textTemplate('reset_password/email.txt.twig')
            ->context([
                'resetToken' => $resetToken,
                'siteContactEmail' => SiteContact::EMAIL,
            ]);

        return $this->dispatch($email);
    }

    /** Message du formulaire contact → boîte contact@soukexpat.com */
    public function sendContactToInbox(Contact $contact): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo(new Address((string) $contact->getEmail(), (string) $contact->getName()))
            ->to($this->fromAddress())
            ->subject('Contact SoukExpat — ' . (string) $contact->getSubject())
            ->htmlTemplate('emails/contact_inbox.html.twig')
            ->context([
                'contact' => $contact,
                'siteContactEmail' => SiteContact::EMAIL,
            ]);

        return $this->dispatch($email);
    }

    /** Réponse admin → e-mail de l’utilisateur */
    public function sendContactReply(string $toEmail, string $toName, string $originalSubject, string $replyBody): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to(new Address($toEmail, $toName !== '' ? $toName : $toEmail))
            ->subject('SoukExpat — Réponse : ' . $originalSubject)
            ->htmlTemplate('emails/contact_reply.html.twig')
            ->context([
                'toName' => $toName,
                'originalSubject' => $originalSubject,
                'replyBody' => $replyBody,
                'siteContactEmail' => SiteContact::EMAIL,
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
            ->subject('SoukExpat — Votre annonce a été approuvée')
            ->htmlTemplate('emails/annonce_status.html.twig')
            ->context([
                'annonce' => $annonce,
                'user' => $user,
                'approved' => true,
                'siteContactEmail' => SiteContact::EMAIL,
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
            ->subject('SoukExpat — Votre annonce n’a pas été validée')
            ->htmlTemplate('emails/annonce_status.html.twig')
            ->context([
                'annonce' => $annonce,
                'user' => $user,
                'approved' => false,
                'siteContactEmail' => SiteContact::EMAIL,
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
