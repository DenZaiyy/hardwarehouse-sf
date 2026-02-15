<?php

namespace App\Controller;

use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/configurations', name: 'configuration.')]
final class ConfigurationController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('configuration/index.html.twig');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->render('configuration/new.html.twig', [
            'form' => '',
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $configuration = $this->apiService->getData('configurations');

        return $this->render('configuration/show.html.twig', [
            'configuration' => $configuration,
        ]);
    }
}
