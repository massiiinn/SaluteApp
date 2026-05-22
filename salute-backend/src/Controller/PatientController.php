<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\User;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/patients')]
class PatientController extends AbstractController
{
    #[Route('', name: 'patients_index', methods: ['GET'])]
    public function index(PatientRepository $repo): JsonResponse
    {
        $patients = $repo->findAll();

        $data = array_map(fn(Patient $p) => [
            'id'          => $p->getId(),
            'dateOfBirth' => $p->getDateOfBirth()?->format('Y-m-d'),
            'bloodType'   => $p->getBloodType(),
            'allergies'   => $p->getAllergies(),
            'user' => [
                'id'        => $p->getUser()->getId(),
                'firstName' => $p->getUser()->getFirstName(),
                'lastName'  => $p->getUser()->getLastName(),
                'email'     => $p->getUser()->getEmail(),
                'phone'     => $p->getUser()->getPhone(),
            ],
        ], $patients);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'patients_show', methods: ['GET'])]
    public function show(Patient $patient): JsonResponse
    {
        return $this->json([
            'id'          => $patient->getId(),
            'dateOfBirth' => $patient->getDateOfBirth()?->format('Y-m-d'),
            'bloodType'   => $patient->getBloodType(),
            'allergies'   => $patient->getAllergies(),
            'user' => [
                'id'        => $patient->getUser()->getId(),
                'firstName' => $patient->getUser()->getFirstName(),
                'lastName'  => $patient->getUser()->getLastName(),
                'email'     => $patient->getUser()->getEmail(),
                'phone'     => $patient->getUser()->getPhone(),
            ],
        ]);
    }

    #[Route('', name: 'patients_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }
    
        $required = ['firstName', 'lastName', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['message' => "El campo '$field' es obligatorio"], 400);
            }
        }
    
        $user = new User();
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setEmail($data['email']);
        $user->setPhone($data['phone'] ?? null);
        $user->setRoles(['ROLE_PATIENT']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
    
        $em->persist($user);
    
        $patient = new Patient();
        $patient->setUser($user);
        $patient->setBloodType($data['bloodType'] ?? null);
        $patient->setAllergies($data['allergies'] ?? null);
    
        if (!empty($data['dateOfBirth'])) {
            $patient->setDateOfBirth(new \DateTime($data['dateOfBirth']));
        }
    
        $em->persist($patient);
    
        try {
            $em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            return $this->json(['message' => 'Ya existe un usuario con este email'], 409);
        }
    
        return $this->json([
            'id'          => $patient->getId(),
            'dateOfBirth' => $patient->getDateOfBirth()?->format('Y-m-d'),
            'bloodType'   => $patient->getBloodType(),
            'allergies'   => $patient->getAllergies(),
            'user' => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'phone'     => $user->getPhone(),
            ],
        ], 201);
    }

    #[Route('/{id}', name: 'patients_update', methods: ['PUT'])]
    public function update(
        Patient $patient,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

    // Datos médicos
    if (isset($data['bloodType']))    $patient->setBloodType($data['bloodType']);
    if (isset($data['allergies']))    $patient->setAllergies($data['allergies']);
    if (!empty($data['dateOfBirth'])) $patient->setDateOfBirth(new \DateTime($data['dateOfBirth']));

    // Datos del usuario
    $user = $patient->getUser();
    if (!empty($data['firstName'])) $user->setFirstName($data['firstName']);
    if (!empty($data['lastName']))  $user->setLastName($data['lastName']);
    if (!empty($data['email'])) {
        if ($data['email'] !== $user->getEmail()) {
            $existing = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existing) {
                return $this->json(['message' => 'Este email ya está registrado'], 409);
            }
        }
        $user->setEmail($data['email']);
    }
    if (isset($data['phone']))      $user->setPhone($data['phone'] ?: null);

    $em->flush();

        return $this->json([
            'id'          => $patient->getId(),
            'dateOfBirth' => $patient->getDateOfBirth()?->format('Y-m-d'),
            'bloodType'   => $patient->getBloodType(),
            'allergies'   => $patient->getAllergies(),
            'user' => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'phone'     => $user->getPhone(),
            ],
        ]);
    }

    #[Route('/{id}', name: 'patients_delete', methods: ['DELETE'])]
    public function delete(Patient $patient, EntityManagerInterface $em): JsonResponse
    {
        foreach ($patient->getAppointments() as $appointment) {
            $em->remove($appointment);
        }
    
        $user = $patient->getUser();
        $em->remove($patient);
        $em->remove($user);
        $em->flush();
    
        return $this->json(['message' => 'Patient deleted']);
    }
}