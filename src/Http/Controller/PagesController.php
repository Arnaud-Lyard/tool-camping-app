<?php

namespace App\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route("/", name: "home")]
    public function index(): Response
    {
        return $this->render("pages/index.html.twig", [
            "controller_name" => "PagesController",
        ]);
    }
}
