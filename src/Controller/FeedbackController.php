<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Form\FeedbackType;
use App\Service\MailService;
use App\Service\SpamGuard;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class FeedbackController extends AbstractController
{
    /**
     * Spätná väzba / podpora — na túto adresu odkazuje aj App Store.
     *
     * Ochrana proti spamu bez captchy: skryté pole + minimálny čas vyplnenia (SpamGuard)
     * a najviac 5 správ za hodinu z jednej IP (rate_limiter.yaml). Spam sa zahodí potichu —
     * robot dostane rovnakú odpoveď ako človek, aby nevedel, že bol odhalený.
     */
    #[Route('/feedback', name: 'feedback', methods: ['GET', 'POST'])]
    public function feedback(
        Request $request,
        EntityManagerInterface $em,
        MailService $mailService,
        SpamGuard $spamGuard,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.feedback')]
        RateLimiterFactoryInterface $feedbackLimiter,
    ): Response {
        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->get(FeedbackType::TOKEN_FIELD)->setData($spamGuard->issueToken());
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$feedbackLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                // Flash správy vypisuje base.html.twig bez prekladu — prekladáme tu.
                $this->addFlash('danger', $translator->trans('feedback.too_many'));

                return $this->render('feedback/form.html.twig', ['form' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }

            if ($form->isValid()) {
                $spamReason = $spamGuard->check(
                    $form->get(FeedbackType::TOKEN_FIELD)->getData(),
                    $form->get(FeedbackType::HONEYPOT_FIELD)->getData(),
                );

                if ($spamReason === null) {
                    $em->persist($feedback);
                    $em->flush();
                    $mailService->sendFeedbackAdmin($feedback);
                } else {
                    $logger->info('Feedback zahodený ako spam', ['reason' => $spamReason, 'ip' => $request->getClientIp()]);
                }

                $this->addFlash('success', $translator->trans('feedback.thanks'));

                return $this->redirectToRoute('feedback');
            }
        }

        return $this->render('feedback/form.html.twig', ['form' => $form]);
    }

    // Verejný prehľad návrhov — zatiaľ vypnutý (stačí odkomentovať Route atribút).
    // #[Route('/status', name: 'status')]
    public function status(EntityManagerInterface $em): Response
    {
        $feedbacks = $em->getRepository(Feedback::class)->findBy(['published' => 1], ['createdAt' => 'DESC']);

        return $this->render('feedback/status.html.twig', [
            'feedbacks' => $feedbacks,
        ]);
    }
}
