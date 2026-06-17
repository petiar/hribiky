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
    // Dočasne vypnuté – položka "Niečo nefunguje?" je odstránená z menu a route má hádzať 404.
    // Kód ponechávame, možno feedback ešte niekedy použijeme (stačí odkomentovať Route atribúty).
    // #[Route('/feedback', name: 'feedback')]
    public function feedback(Request $request, EntityManagerInterface $em, MailService $mailService): Response
    {
        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($feedback);
            $em->flush();

            $this->addFlash('success', 'Ďakujeme za vašu spätnú väzbu!');

            $mailService->sendFeedbackAdmin($feedback);
            return $this->redirectToRoute('feedback');
        }

        return $this->render('feedback/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Dočasne vypnuté spolu s feedback() – viď komentár vyššie.
    // #[Route('/status', name: 'status')]
    public function status(EntityManagerInterface $em): Response
    {
        $feedbacks = $em->getRepository(Feedback::class)->findBy(['published' => 1], ['createdAt' => 'DESC']);

        return $this->render('feedback/status.html.twig', [
            'feedbacks' => $feedbacks,
        ]);
    }
}
