<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'profile_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json([
            'id'        => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'email'     => $user->getEmail(),
            'phone'     => $user->getPhone(),
            'avatar'    => $user->getAvatar(),
            'roles'     => $user->getRoles(),
            'isActive'  => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'profile_update', methods: ['PUT'])]
    public function update(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }

        if (!empty($data['firstName'])) $user->setFirstName($data['firstName']);
        if (!empty($data['lastName']))  $user->setLastName($data['lastName']);
        if (!empty($data['email']))     $user->setEmail($data['email']);
        if (isset($data['phone']))      $user->setPhone($data['phone'] ?: null);

        $em->flush();

        return $this->json([
            'id'        => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'email'     => $user->getEmail(),
            'phone'     => $user->getPhone(),
            'avatar'    => $user->getAvatar(),
            'roles'     => $user->getRoles(),
        ]);
    }

    #[Route('/avatar', name: 'profile_avatar', methods: ['POST'])]
    public function uploadAvatar(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $file = $request->files->get('avatar');

        if (!$file) {
            return $this->json(['message' => 'No se ha enviado ningún archivo'], 400);
        }

        // Validar tipo
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['message' => 'Formato no permitido. Usa JPG, PNG, WEBP o GIF'], 400);
        }

        // Validar tamaño (2MB máx)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['message' => 'La imagen no puede superar 2MB'], 400);
        }

        // Borrar avatar anterior si existe
        if ($user->getAvatar()) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $user->getAvatar();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Guardar nuevo avatar
        $filename  = uniqid('avatar_') . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
        $file->move($uploadDir, $filename);

        $avatarUrl = '/uploads/avatars/' . $filename;
        $user->setAvatar($avatarUrl);
        $em->flush();

        return $this->json([
            'avatar' => $avatarUrl,
            'message' => 'Avatar actualizado correctamente'
        ]);
    }

    #[Route('/password', name: 'profile_password', methods: ['PUT'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['currentPassword']) || empty($data['newPassword'])) {
            return $this->json(['message' => 'currentPassword y newPassword son obligatorios'], 400);
        }

        if (!$passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['message' => 'La contraseña actual no es correcta'], 400);
        }

        if (strlen($data['newPassword']) < 6) {
            return $this->json(['message' => 'La nueva contraseña debe tener al menos 6 caracteres'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $data['newPassword']));
        $em->flush();

        return $this->json(['message' => 'Contraseña actualizada correctamente']);
    }
}