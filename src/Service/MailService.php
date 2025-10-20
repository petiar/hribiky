<?php

namespace App\Service;

use App\Entity\Mushroom;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use function PHPUnit\Framework\throwException;

class MailService
{

    private string $template;

    private string $subject;

    private string $recipient;

    private array $context;

    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $emailFrom,
        private string $emailFromName,
        private string $emailToMe,
    ) {
    }

    public function send(): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->emailFrom, $this->emailFromName))
            ->to($this->recipient)
            ->subject($this->subject)
            ->htmlTemplate($this->template)
            ->context($this->context);

        $this->mailer->send($email);
    }

    public function sendMushroomThankYou(Mushroom $mushroom): void
    {
        $this->setSubject('Poďakovanie z Hríbiky.sk');
        $this->setRecipient($mushroom->getEmail());
        $this->setTemplate('emails/thank_you.html.twig');
        $this->setContext(['mushroom' => $mushroom]);
        $this->send();
    }

    public function sendMushroomAdmin(Mushroom $mushroom): void
    {
        $this->setSubject(
            sprintf('Nový hríbik (%s) na Hríbiky.sk!', $mushroom->getTitle())
        );
        $this->setRecipient($this->emailToMe);
        $this->setTemplate('emails/new_mushroom.html.twig');
        $this->setContext(['mushroom' => $mushroom]);
        $this->send();
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): MailService
    {
        $this->template = $template;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): MailService
    {
        $this->subject = $subject;

        return $this;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): MailService
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate(
            $recipient,
            new \Symfony\Component\Validator\Constraints\Email()
        );
        if (count($violations) === 0) {
            $this->recipient = $recipient;
            return $this;
        }
        throw new InvalidArgumentException('Neplatná e-mailová adresa.');
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): MailService
    {
        $this->context = $context;

        return $this;
    }


}
