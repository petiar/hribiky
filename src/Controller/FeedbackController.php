<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Form\FeedbackType;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FeedbackController extends AbstractController
{
    #[Route('/feedback', name: 'feedback')]
    public function feedback(Request $request, EntityManagerInterface $em, MailService $mailService): Response
    {
        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($feedback);
            $em->flush();

            $this->addFlash('success', 'Ďakujeme za vašu spätnú väzbu!');

            $subject = 'Nový feedback na Hríbiky.sk';
            $mailService->send('emails/new_feedback.html.twig', $subject, 'petiar@gmail.com', [
                'feedback' => $feedback,
            ]);
            return $this->redirectToRoute('feedback');
        }

        return $this->render('feedback/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/status', name: 'status')]
    public function status(EntityManagerInterface $em): Response
    {
        $feedbacks = $em->getRepository(Feedback::class)->findBy(['published' => 1], ['createdAt' => 'DESC']);

        return $this->render('feedback/status.html.twig', [
            'feedbacks' => $feedbacks,
        ]);
    }
}
