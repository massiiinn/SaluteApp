<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['message' => 'El email es obligatorio'], 400);
        }

        $user = $userRepo->findOneBy(['email' => $email]);

        // Siempre devolvemos 200 por seguridad (no revelar si el email existe)
        if (!$user) {
            return $this->json(['message' => 'Si el email existe, recibirás un enlace en breve.']);
        }

        // Generar token único
        $token  = bin2hex(random_bytes(32));
        $expiry = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($token);
        $user->setResetTokenExpiry($expiry);
        $em->flush();

        // Enlace de reset (frontend)
        $resetUrl = "http://grup11.infla.cat/reset-password?token={$token}";

        try {
            $html = $this->renderView('emails/password_reset.html.twig', [
                'name'     => $user->getFirstName(),
                'resetUrl' => $resetUrl,
            ]);

            $message = (new Email())
                ->from(new \Symfony\Component\Mime\Address('massin11ouchen@gmail.com', 'Salute App'))
                ->to($user->getEmail())
                ->subject('🔐 Restablecer contraseña — Salute')
                ->html($html);

            $mailer->send($message);
        } catch (\Exception $e) {
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json(['message' => 'Si el email existe, recibirás un enlace en breve.']);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $token    = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return $this->json(['message' => 'Token y contraseña son obligatorios'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json(['message' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }

        $user = $userRepo->findOneBy(['resetToken' => $token]);

        if (!$user) {
            return $this->json(['message' => 'Token inválido o expirado'], 400);
        }

        if ($user->getResetTokenExpiry() < new \DateTimeImmutable()) {
            return $this->json(['message' => 'El enlace ha expirado. Solicita uno nuevo.'], 400);
        }

        // Actualizar contraseña y limpiar token
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiry(null);
        $em->flush();

        return $this->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}