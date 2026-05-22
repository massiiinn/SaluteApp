<?php

namespace App\Command;

use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:expire-appointments',
    description: 'Marca como completadas las citas pendientes cuya fecha ya ha pasado'
)]
class ExpireAppointmentsCommand extends Command
{
    public function __construct(
        private AppointmentRepository $appointmentRepo,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();

        // Buscar citas pendientes o confirmadas cuya fecha ya pasó
        $appointments = $this->appointmentRepo->createQueryBuilder('a')
            ->where('a.appointmentDate < :now')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('now', $now)
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($appointments as $appointment) {
            $appointment->setStatus('completed');
            $count++;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        $output->writeln("✅ Citas marcadas como completadas: $count");
        return Command::SUCCESS;
    }
}