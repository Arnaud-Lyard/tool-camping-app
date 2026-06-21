<?php

namespace App\Http\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Form\RegistrationFormType;
use App\Domain\Auth\Repository\UserRepository;
use App\Domain\Auth\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route("/inscription", name: "app_register")]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get("plainPassword")->getData();

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword),
            );

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                "app_verify_email",
                $user,
                new TemplatedEmail()
                    ->from(new Address("mailer@camping.fr", "Camping"))
                    ->to((string) $user->getEmail())
                    ->subject("Veuillez confirmer votre adresse e-mail")
                    ->htmlTemplate("registration/confirmation_email.html.twig"),
            );

            // do anything else you need here, like send an email

            return $this->redirectToRoute("app_login");
        }

        return $this->render("registration/register.html.twig", [
            "registrationForm" => $form,
        ]);
    }

    #[Route("/verify/email", name: "app_verify_email")]
    public function verifyUserEmail(
        Request $request,
        UserRepository $userRepository,
    ): Response {
        // validation anonyme : l'utilisateur n'a pas besoin d'être connecté.
        $id = $request->query->get("id");
        if (null === $id) {
            return $this->redirectToRoute("app_register");
        }

        $user = $userRepository->find($id);
        if (null === $user) {
            return $this->redirectToRoute("app_register");
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash("verify_email_error", $exception->getReason());

            return $this->redirectToRoute("app_register");
        }

        $this->addFlash(
            "success",
            "Votre adresse e-mail a été vérifiée. Vous pouvez maintenant vous connecter.",
        );

        // l'email étant vérifié, on envoie l'utilisateur se connecter
        return $this->redirectToRoute("app_login");
    }
}
