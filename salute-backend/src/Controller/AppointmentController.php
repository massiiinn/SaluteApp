<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/appointments')]
class AppointmentController extends AbstractController
{
    private function serializeAppointment(Appointment $a): array
    {
        return [
            'id'              => $a->getId(),
            'appointmentDate' => $a->getAppointmentDate()?->format('Y-m-d H:i:s'),
            'status'          => $a->getStatus(),
            'reason'          => $a->getReason(),
            'notes'           => $a->getNotes(),
            'createdAt'       => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
            'patient' => [
                'id'        => $a->getPatient()->getId(),
                'firstName' => $a->getPatient()->getUser()->getFirstName(),
                'lastName'  => $a->getPatient()->getUser()->getLastName(),
            ],
            'doctor' => [
                'id'        => $a->getDoctor()->getId(),
                'firstName' => $a->getDoctor()->getUser()->getFirstName(),
                'lastName'  => $a->getDoctor()->getUser()->getLastName(),
                'specialty' => $a->getDoctor()->getSpecialty()?->getName(),
            ],
        ];
    }

    private function isSlotTaken(
        AppointmentRepository $repo,
        int $doctorId,
        \DateTimeImmutable $date,
        ?int $excludeId = null
    ): bool {
        $from = $date->modify('-29 minutes');
        $to   = $date->modify('+29 minutes');

        $qb = $repo->createQueryBuilder('a')
            ->join('a.doctor', 'd')
            ->where('d.id = :doctorId')
            ->andWhere('a.appointmentDate >= :from')
            ->andWhere('a.appointmentDate <= :to')
            ->andWhere('a.status NOT IN (:excluded)')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('excluded', ['cancelled']);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }

    #[Route('', name: 'appointments_index', methods: ['GET'])]
    public function index(
        AppointmentRepository $repo,
        PatientRepository $patientRepo,
        DoctorRepository $doctorRepo
    ): JsonResponse {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        $roles       = $currentUser->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $appointments = $repo->findAll();
        } elseif (in_array('ROLE_DOCTOR', $roles)) {
            $doctor       = $doctorRepo->findOneBy(['user' => $currentUser]);
            $appointments = $doctor ? $repo->findBy(['doctor' => $doctor]) : [];
        } else {
            $patient      = $patientRepo->findOneBy(['user' => $currentUser]);
            $appointments = $patient ? $repo->findBy(['patient' => $patient]) : [];
        }

        return $this->json(array_map(fn($a) => $this->serializeAppointment($a), $appointments));
    }

    #[Route('/{id}', name: 'appointments_show', methods: ['GET'])]
    public function show(Appointment $appointment): JsonResponse
    {
        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('', name: 'appointments_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AppointmentRepository $repo,
        PatientRepository $patientRepo,
        DoctorRepository $doctorRepo,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['appointmentDate'])) {
            return $this->json(['message' => 'appointmentDate is required'], 400);
        }

        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        $roles       = $currentUser->getRoles();

        if (in_array('ROLE_DOCTOR', $roles)) {
            $doctor = $doctorRepo->findOneBy(['user' => $currentUser]);
            if (!$doctor) return $this->json(['message' => 'Doctor profile not found'], 404);
        } else {
            if (empty($data['doctorId'])) return $this->json(['message' => 'doctorId is required'], 400);
            $doctor = $doctorRepo->find($data['doctorId']);
            if (!$doctor) return $this->json(['message' => 'Doctor not found'], 404);
        }

        if (!empty($data['patientId'])) {
            $patient = $patientRepo->find($data['patientId']);
            if (!$patient) return $this->json(['message' => 'Patient not found'], 404);
        } else {
            $patient = $patientRepo->findOneBy(['user' => $currentUser]);
            if (!$patient) return $this->json(['message' => 'No patient profile found'], 404);
        }

        $appointmentDate = new \DateTimeImmutable($data['appointmentDate']);

        if ($this->isSlotTaken($repo, $doctor->getId(), $appointmentDate)) {
            return $this->json(['message' => 'El médico ya tiene una cita en esa franja horaria. Por favor, elige otro horario.'], 409);
        }

        $appointment = new Appointment();
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setAppointmentDate($appointmentDate);
        $appointment->setStatus('pending');
        $appointment->setReason($data['reason'] ?? null);
        $appointment->setNotes($data['notes'] ?? null);

        $em->persist($appointment);
        $em->flush();

        try {
            $patientEmail = $patient->getUser()->getEmail();
            $patientName  = $patient->getUser()->getFirstName() . ' ' . $patient->getUser()->getLastName();
            $doctorName   = $doctor->getUser()->getFirstName() . ' ' . $doctor->getUser()->getLastName();
            $specialty    = $doctor->getSpecialty()?->getName() ?? 'Medicina General';
            $date         = $appointment->getAppointmentDate()->format('d/m/Y');
            $time         = $appointment->getAppointmentDate()->format('H:i');

            $html = $this->renderView('emails/appointment_confirmation.html.twig', [
                'patientName' => $patientName,
                'doctorName'  => $doctorName,
                'specialty'   => $specialty,
                'date'        => $date,
                'time'        => $time,
                'reason'      => $appointment->getReason(),
            ]);

            $email = (new Email())
                ->from(new \Symfony\Component\Mime\Address('massin11ouchen@gmail.com', 'Salute App'))
                ->to($patientEmail)
                ->subject('✅ Cita confirmada — ' . $date . ' a las ' . $time)
                ->html($html);

            $mailer->send($email);
        } catch (\Exception $e) {}

        return $this->json($this->serializeAppointment($appointment), 201);
    }

    #[Route('/{id}', name: 'appointments_update', methods: ['PUT'])]
    public function update(
        Appointment $appointment,
        Request $request,
        EntityManagerInterface $em,
        AppointmentRepository $repo,
        DoctorRepository $doctorRepo,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $previousStatus = $appointment->getStatus();
        $dateChanged    = !empty($data['appointmentDate']);
        $doctorChanged  = !empty($data['doctorId']);

        // Cambiar médico
        if ($doctorChanged) {
            $newDoctor = $doctorRepo->find($data['doctorId']);
            if (!$newDoctor) {
                return $this->json(['message' => 'Doctor not found'], 404);
            }
            // Validar disponibilidad con el nuevo médico en la fecha actual
            if ($this->isSlotTaken($repo, $newDoctor->getId(), $appointment->getAppointmentDate(), $appointment->getId())) {
                return $this->json(['message' => 'El médico ya tiene una cita en esa franja horaria. Por favor, elige otro horario.'], 409);
            }
            $appointment->setDoctor($newDoctor);
        }

        // Cambiar fecha
        if ($dateChanged) {
            $newDate = new \DateTimeImmutable($data['appointmentDate']);
            $doctorId = $appointment->getDoctor()->getId();
            if ($this->isSlotTaken($repo, $doctorId, $newDate, $appointment->getId())) {
                return $this->json(['message' => 'El médico ya tiene una cita en esa franja horaria. Por favor, elige otro horario.'], 409);
            }
            $appointment->setAppointmentDate($newDate);
        }

        if (isset($data['status'])) $appointment->setStatus($data['status']);
        if (isset($data['reason'])) $appointment->setReason($data['reason']);
        if (isset($data['notes']))  $appointment->setNotes($data['notes']);

        $em->flush();

        // Email de reprogramación (fecha o médico cambiado, no cancelada)
        if (($dateChanged || $doctorChanged) && $appointment->getStatus() !== 'cancelled') {
            try {
                $patient      = $appointment->getPatient();
                $doctor       = $appointment->getDoctor();
                $patientEmail = $patient->getUser()->getEmail();
                $patientName  = $patient->getUser()->getFirstName() . ' ' . $patient->getUser()->getLastName();
                $doctorName   = $doctor->getUser()->getFirstName() . ' ' . $doctor->getUser()->getLastName();
                $specialty    = $doctor->getSpecialty()?->getName() ?? 'Medicina General';
                $date         = $appointment->getAppointmentDate()->format('d/m/Y');
                $time         = $appointment->getAppointmentDate()->format('H:i');

                $html = $this->renderView('emails/appointment_confirmation.html.twig', [
                    'patientName' => $patientName,
                    'doctorName'  => $doctorName,
                    'specialty'   => $specialty,
                    'date'        => $date,
                    'time'        => $time,
                    'reason'      => $appointment->getReason(),
                ]);

                $subject = $doctorChanged && !$dateChanged
                    ? '👨‍⚕️ Médico actualizado — ' . $date . ' a las ' . $time
                    : '📅 Cita reprogramada — ' . $date . ' a las ' . $time;

                $email = (new Email())
                    ->from(new \Symfony\Component\Mime\Address('massin11ouchen@gmail.com', 'Salute App'))
                    ->to($patientEmail)
                    ->subject($subject)
                    ->html($html);

                $mailer->send($email);
            } catch (\Exception $e) {}
        }

        // Email de cancelación
        if ($previousStatus !== 'cancelled' && $appointment->getStatus() === 'cancelled') {
            try {
                $patient      = $appointment->getPatient();
                $doctor       = $appointment->getDoctor();
                $patientEmail = $patient->getUser()->getEmail();
                $patientName  = $patient->getUser()->getFirstName() . ' ' . $patient->getUser()->getLastName();
                $doctorName   = $doctor->getUser()->getFirstName() . ' ' . $doctor->getUser()->getLastName();
                $specialty    = $doctor->getSpecialty()?->getName() ?? 'Medicina General';
                $date         = $appointment->getAppointmentDate()->format('d/m/Y');
                $time         = $appointment->getAppointmentDate()->format('H:i');

                $html = $this->renderView('emails/appointment_cancellation.html.twig', [
                    'patientName' => $patientName,
                    'doctorName'  => $doctorName,
                    'specialty'   => $specialty,
                    'date'        => $date,
                    'time'        => $time,
                    'reason'      => $appointment->getReason(),
                ]);

                $email = (new Email())
                    ->from(new \Symfony\Component\Mime\Address('massin11ouchen@gmail.com', 'Salute App'))
                    ->to($patientEmail)
                    ->subject('❌ Cita cancelada — ' . $date . ' a las ' . $time)
                    ->html($html);

                $mailer->send($email);
            } catch (\Exception $e) {}
        }

        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/{id}', name: 'appointments_delete', methods: ['DELETE'])]
    public function delete(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($appointment);
        $em->flush();

        return $this->json(['message' => 'Appointment deleted']);
    }
}