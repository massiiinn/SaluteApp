<?php

namespace App\Command;

use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envía recordatorios de citas que son en las próximas 24 horas'
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private AppointmentRepository $appointmentRepo,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now   = new \DateTimeImmutable();
        $in24h = $now->modify('+24 hours');
        $in25h = $now->modify('+25 hours');

        // Buscar citas entre 24h y 25h que no estén canceladas y no hayan recibido recordatorio
        $appointments = $this->appointmentRepo->createQueryBuilder('a')
            ->where('a.appointmentDate >= :from')
            ->andWhere('a.appointmentDate <= :to')
            ->andWhere('a.status != :cancelled')
            ->andWhere('a.reminderSent = :notSent')
            ->setParameter('from', $in24h)
            ->setParameter('to', $in25h)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('notSent', false)
            ->getQuery()
            ->getResult();

        $sent = 0;
        foreach ($appointments as $appointment) {
            try {
                $patient    = $appointment->getPatient();
                $doctor     = $appointment->getDoctor();
                $email      = $patient->getUser()->getEmail();
                $name       = $patient->getUser()->getFirstName() . ' ' . $patient->getUser()->getLastName();
                $doctorName = $doctor->getUser()->getFirstName() . ' ' . $doctor->getUser()->getLastName();
                $specialty  = $doctor->getSpecialty()?->getName() ?? 'Medicina General';
                $date       = $appointment->getAppointmentDate()->format('d/m/Y');
                $time       = $appointment->getAppointmentDate()->format('H:i');

                $html = $this->twig->render('emails/appointment_reminder.html.twig', [
                    'patientName' => $name,
                    'doctorName'  => $doctorName,
                    'specialty'   => $specialty,
                    'date'        => $date,
                    'time'        => $time,
                    'reason'      => $appointment->getReason(),
                ]);

                $message = (new Email())
                    ->from(new \Symfony\Component\Mime\Address('massin11ouchen@gmail.com', 'Salute App'))
                    ->to($email)
                    ->subject('⏰ Recordatorio — Tienes una cita mañana a las ' . $time)
                    ->html($html);

                $this->mailer->send($message);

                // Marcar como enviado para no duplicar
                $appointment->setReminderSent(true);
                $sent++;

                $output->writeln("✅ Recordatorio enviado a: $email");

            } catch (\Exception $e) {
                $output->writeln("❌ Error enviando a {$appointment->getId()}: " . $e->getMessage());
            }
        }

        $this->em->flush();

        $output->writeln("Recordatorios enviados: $sent");
        return Command::SUCCESS;
    }
}