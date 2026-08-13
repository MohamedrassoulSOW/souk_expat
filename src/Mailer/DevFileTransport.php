<?php

declare(strict_types=1);

namespace App\Mailer;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

/**
 * Dev only — écrit les e-mails dans var/mail/ (pas de SMTP requis).
 */
final class DevFileTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $directory,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de créer le dossier mail : ' . $this->directory);
        }

        $original = $message->getOriginalMessage();
        $raw = $original instanceof RawMessage
            ? $original->toString()
            : MessageConverter::toEmail($original)->toString();

        $filename = sprintf(
            '%s_%s.eml',
            date('Ymd-His'),
            bin2hex(random_bytes(4)),
        );

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $raw) === false) {
            throw new \RuntimeException('Impossible d’écrire l’e-mail : ' . $path);
        }
    }

    public function __toString(): string
    {
        return 'devfile://local';
    }
}
