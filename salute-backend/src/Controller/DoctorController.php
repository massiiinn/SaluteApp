<?php

namespace App\Controller;

use App\Entity\Doctor;
use App\Entity\User;
use App\Repository\DoctorRepository;
use App\Repository\SpecialtyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/doctors')]
class DoctorController extends AbstractController
{
    #[Route('', name: 'doctors_index', methods: ['GET'])]
    public function index(DoctorRepository $repo): JsonResponse
    {
        $doctors = $repo->findAll();

        $data = array_map(fn(Doctor $d) => [
            'id'                => $d->getId(),
            'licenseNumber'     => $d->getLicenseNumber(),
            'bio'               => $d->getBio(),
            'consultationPrice' => $d->getConsultationPrice(),
            'specialty' => $d->getSpecialty() ? [
                'id'   => $d->getSpecialty()->getId(),
                'name' => $d->getSpecialty()->getName(),
            ] : null,
            'user' => [
                'id'        => $d->getUser()->getId(),
                'firstName' => $d->getUser()->getFirstName(),
                'lastName'  => $d->getUser()->getLastName(),
                'email'     => $d->getUser()->getEmail(),
                'phone'     => $d->getUser()->getPhone(),
            ],
        ], $doctors);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'doctors_show', methods: ['GET'])]
    public function show(Doctor $doctor): JsonResponse
    {
        return $this->json([
            'id'                => $doctor->getId(),
            'licenseNumber'     => $doctor->getLicenseNumber(),
            'bio'               => $doctor->getBio(),
            'consultationPrice' => $doctor->getConsultationPrice(),
            'specialty' => $doctor->getSpecialty() ? [
                'id'   => $doctor->getSpecialty()->getId(),
                'name' => $doctor->getSpecialty()->getName(),
            ] : null,
            'user' => [
                'id'        => $doctor->getUser()->getId(),
                'firstName' => $doctor->getUser()->getFirstName(),
                'lastName'  => $doctor->getUser()->getLastName(),
                'email'     => $doctor->getUser()->getEmail(),
                'phone'     => $doctor->getUser()->getPhone(),
            ],
        ]);
    }

    #[Route('', name: 'doctors_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SpecialtyRepository $specialtyRepo,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }

        $required = ['firstName', 'lastName', 'email', 'password', 'licenseNumber'];
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
        $user->setRoles(['ROLE_DOCTOR']);
        $user->setIsActive(true);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $em->persist($user);

        $doctor = new Doctor();
        $doctor->setUser($user);
        $doctor->setLicenseNumber($data['licenseNumber']);
        $doctor->setBio($data['bio'] ?? null);
        $doctor->setConsultationPrice($data['consultationPrice'] ?? null);

        if (!empty($data['specialtyId'])) {
            $specialty = $specialtyRepo->find($data['specialtyId']);
            if ($specialty) $doctor->setSpecialty($specialty);
        }

        $em->persist($doctor);
        $em->flush();

        return $this->json([
            'id'                => $doctor->getId(),
            'licenseNumber'     => $doctor->getLicenseNumber(),
            'bio'               => $doctor->getBio(),
            'consultationPrice' => $doctor->getConsultationPrice(),
            'specialty' => $doctor->getSpecialty() ? [
                'id'   => $doctor->getSpecialty()->getId(),
                'name' => $doctor->getSpecialty()->getName(),
            ] : null,
            'user' => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'phone'     => $user->getPhone(),
            ],
        ], 201);
    }

    #[Route('/{id}', name: 'doctors_update', methods: ['PUT'])]
    public function update(
        Doctor $doctor,
        Request $request,
        EntityManagerInterface $em,
        SpecialtyRepository $specialtyRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['licenseNumber']))     $doctor->setLicenseNumber($data['licenseNumber']);
        if (isset($data['bio']))               $doctor->setBio($data['bio']);
        if (isset($data['consultationPrice'])) $doctor->setConsultationPrice($data['consultationPrice']);

        $user = $doctor->getUser();
        if (!empty($data['firstName'])) $user->setFirstName($data['firstName']);
        if (!empty($data['lastName']))  $user->setLastName($data['lastName']);
        if (!empty($data['email']))     $user->setEmail($data['email']);
        if (isset($data['phone']))      $user->setPhone($data['phone'] ?: null);

        if (!empty($data['specialtyId'])) {
            $specialty = $specialtyRepo->find($data['specialtyId']);
            if ($specialty) $doctor->setSpecialty($specialty);
        }

        $em->flush();

        return $this->json([
            'id'                => $doctor->getId(),
            'licenseNumber'     => $doctor->getLicenseNumber(),
            'bio'               => $doctor->getBio(),
            'consultationPrice' => $doctor->getConsultationPrice(),
            'specialty' => $doctor->getSpecialty() ? [
                'id'   => $doctor->getSpecialty()->getId(),
                'name' => $doctor->getSpecialty()->getName(),
            ] : null,
            'user' => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'phone'     => $user->getPhone(),
            ],
        ]);
    }

    #[Route('/{id}', name: 'doctors_delete', methods: ['DELETE'])]
    public function delete(Doctor $doctor, EntityManagerInterface $em): JsonResponse
    {
        // 1. Borrar todas las citas del médico con DQL (evita problemas de FK)
        $em->createQuery(
            'DELETE FROM App\Entity\Appointment a WHERE a.doctor = :doctor'
        )->setParameter('doctor', $doctor)->execute();

        // 2. Borrar schedules si los hay
        foreach ($doctor->getSchedules() as $schedule) {
            $em->remove($schedule);
        }
        $em->flush();

        // 3. Guardar referencia al user antes de borrar el doctor
        $user = $doctor->getUser();

        // 4. Borrar el doctor
        $em->remove($doctor);
        $em->flush();

        // 5. Borrar el user asociado
        $em->remove($user);
        $em->flush();

        return $this->json(['message' => 'Doctor eliminado correctamente']);
    }
}