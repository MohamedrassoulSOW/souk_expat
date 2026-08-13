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
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Tous les e-mails du site partent de contact@soukexpat.com (SiteContact::EMAIL).
 */
final class PlatformMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TransportInterface $transport,
        private readonly LoggerInterface $logger,
        private readonly SiteSettingsService $siteSettings,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** Adresse unique d’envoi / contact plateforme. */
    public function contactEmail(): string
    {
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
        return new Address(SiteContact::EMAIL, $this->fromName());
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
        return $this->sendAnnonceStatus($annonce, true);
    }

    public function sendAnnonceRejected(Annonce $annonce): bool
    {
        return $this->sendAnnonceStatus($annonce, false);
    }

    private function sendAnnonceStatus(Annonce $annonce, bool $approved): bool
    {
        $user = $annonce->getUser();
        $to = $user?->getEmail();
        if ($user === null || $to === null || trim($to) === '') {
            $this->logger->warning('E-mail statut annonce ignoré : annonceur sans e-mail', [
                'annonceId' => $annonce->getId(),
            ]);

            return false;
        }

        $subject = $approved
            ? $this->fromName() . ' — Votre annonce a été validée'
            : $this->fromName() . ' — Votre annonce a été refusée';

        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->replyTo($this->fromAddress())
            ->to(new Address($to, (string) ($user->getFirstName() ?: $to)))
            ->subject($subject)
            ->htmlTemplate('emails/annonce_status.html.twig')
            ->textTemplate('emails/annonce_status.txt.twig')
            ->context([
                'annonce' => $annonce,
                'user' => $user,
                'approved' => $approved,
                'siteContactEmail' => $this->contactEmail(),
                'annonceUrl' => $this->absoluteUrl('app_annonce_show', ['id' => $annonce->getId()]),
                'mesAnnoncesUrl' => $this->absoluteUrl('app_mes_annonces'),
            ]);

        return $this->dispatch($email, $to);
    }

    private function absoluteUrl(string $route, array $params = []): string
    {
        try {
            return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable $e) {
            $this->logger->warning('URL e-mail non générée', [
                'route' => $route,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function dispatch(TemplatedEmail $email, ?string $to = null): bool
    {
        if ($this->transport instanceof NullTransport) {
            $this->logger->error('Échec d’envoi e-mail SoukExpat : MAILER_DSN non configuré (null://)', [
                'subject' => $email->getSubject(),
                'from' => SiteContact::EMAIL,
                'to' => $to,
            ]);

            return false;
        }

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->logger->error('Échec d’envoi e-mail SoukExpat', [
                'subject' => $email->getSubject(),
                'from' => SiteContact::EMAIL,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
