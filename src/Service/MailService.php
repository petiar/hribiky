<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $emailFrom,
        private string $emailFromName,
        private string $emailToMe,
    ) {
    }

    public function send(
        string $template,
        string $to,
        array $context = []
    ): void {
        $templateContent = $this->twig->load($template);
        $subject = $templateContent->renderBlock(
            'subject',
            $context
        ) ?? '(no subject)';

        $email = (new TemplatedEmail())
            ->from(new Address($this->emailFrom, $this->emailFromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}
