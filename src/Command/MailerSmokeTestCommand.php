<?php

declare(strict_types=1);

namespace App\Command;

use App\Mail\SiteContact;
use App\Service\PlatformMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mailer:smoke-test',
    description: 'Envoie un e-mail de test via le transport SMTP (sans file Messenger)',
)]
final class MailerSmokeTestCommand extends Command
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly PlatformMailer $platformMailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::OPTIONAL, 'Destinataire', 'test@example.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');
        $from = $this->platformMailer->fromAddress();

        $io->title('Smoke test Mailer SoukExpat');
        $io->listing([
            'DSN: ' . $this->redactDsn($this->mailerDsn),
            'From (forcé): ' . SiteContact::EMAIL,
            'From utilisé: ' . $from->getAddress() . ' (' . $from->getName() . ')',
            'To: ' . $to,
        ]);

        if ($from->getAddress() !== SiteContact::EMAIL) {
            $io->error('From incorrect — attendu ' . SiteContact::EMAIL);

            return Command::FAILURE;
        }

        if ($this->looksLikeMailtrap($this->mailerDsn)) {
            $io->warning('MAILER_DSN pointe vers Mailtrap (sandbox). En production Hostinger, utilisez smtp.hostinger.com.');
        }

        if (trim($this->mailerDsn) === '' || str_starts_with($this->mailerDsn, 'null://')) {
            if (!$this->transport instanceof \App\Mailer\DevFileTransport) {
                $io->error('MAILER_DSN vide ou null:// — aucun e-mail ne partira.');

                return Command::FAILURE;
            }

            $io->note('Transport dev (var/mail/) actif — l’e-mail sera écrit localement.');
        }

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('SoukExpat — smoke test ' . date('Y-m-d H:i:s'))
            ->text("Test SMTP SoukExpat\nExpéditeur: " . SiteContact::EMAIL)
            ->html('<p><strong>Test SMTP SoukExpat</strong></p><p>Expéditeur: ' . SiteContact::EMAIL . '</p>');

        try {
            $sentMessage = $this->transport->send($email);
            $io->success('E-mail accepté par le transport SMTP (From: ' . SiteContact::EMAIL . ').');
            if ($sentMessage !== null && $sentMessage->getMessageId()) {
                $io->writeln('Message-Id: ' . $sentMessage->getMessageId());
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->note([
                'Vérifiez :',
                '1) Boîte ' . SiteContact::EMAIL . ' créée sur Hostinger',
                '2) MAILER_DSN authentifié avec ce compte SMTP',
                '3) Login URL-encodé : contact%40soukexpat.com',
            ]);

            return Command::FAILURE;
        }
    }

    private function redactDsn(string $dsn): string
    {
        return (string) preg_replace('#^(smtp|smtps|null)://([^:]+):([^@]+)@#i', '$1://$2:***@', $dsn);
    }

    private function looksLikeMailtrap(string $dsn): bool
    {
        return str_contains(strtolower($dsn), 'mailtrap');
    }
}
