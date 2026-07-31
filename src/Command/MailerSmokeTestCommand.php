<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PlatformMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
            'From: ' . $from->getAddress() . ' (' . $from->getName() . ')',
            'Contact settings: ' . $this->platformMailer->contactEmail(),
            'To: ' . $to,
        ]);

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('SoukExpat — smoke test ' . date('Y-m-d H:i:s'))
            ->text("Test SMTP SoukExpat\nSi ce message arrive, le transport fonctionne.")
            ->html('<p><strong>Test SMTP SoukExpat</strong></p><p>Si ce message arrive, le transport fonctionne.</p>');

        try {
            $sentMessage = $this->transport->send($email);
            $io->success('E-mail accepté par le transport SMTP.');
            if ($sentMessage !== null && $sentMessage->getMessageId()) {
                $io->writeln('Message-Id: ' . $sentMessage->getMessageId());
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
