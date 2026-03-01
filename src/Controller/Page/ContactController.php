<?php

namespace App\Controller\Page;

use App\Dto\Form\Contact\ContactFormDto;
use App\Form\Contact\ContactFormType;
use App\Service\MailerService;
use App\Service\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly RateLimiterService $rateLimiter,
        private readonly MailerService $mailerService,
        #[Autowire('%env(FROM_EMAIL)%')]
        private readonly string $adminEmail,
    ) {
    }

    #[Route('/contact', name: 'app.contact')]
    public function index(Request $request): Response
    {
        if (!$this->rateLimiter->checkContact()) {
            $retryAfter = $this->rateLimiter->getRetryAfter();
            $this->addFlash('danger', "Trop de tentatives. Réessayez dans $retryAfter secondes.");

            return $this->redirectToRoute('app.contact');
        }

        $contact = new ContactFormDto();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $contact->email;
            $name = $contact->name;
            $subject = $contact->subject;
            $message = $contact->message;

            try {
                $this->mailerService->sendTemplatedEmail(
                    $this->adminEmail,
                    '[CONTACT] '.$name,
                    'emails/contact/contact.html.twig',
                    [
                        'name' => $name,
                        'contactEmail' => $email,
                        'subject' => $subject,
                        'message' => $message,
                        'timestamp' => new \DateTime(),
                    ],
                    $email
                );
            } catch (\Exception $exception) {
                $msg = $exception->getMessage();
                $this->addFlash('danger', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer plus tard. '.$msg);

                return $this->redirectToRoute('app.contact');
            }

            $this->addFlash('success', 'Votre message a été envoyé avec succès !');

            return $this->redirectToRoute('app.contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
