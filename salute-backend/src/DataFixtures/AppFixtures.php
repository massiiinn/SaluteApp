<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Specialty;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    private function createUser(string $first, string $last, string $email, string $phone, array $roles, string $password): User
    {
        $user = new User();
        $user->setFirstName($first);
        $user->setLastName($last);
        $user->setEmail($email);
        $user->setPhone($phone);
        $user->setRoles($roles);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        return $user;
    }

    public function load(ObjectManager $manager): void
    {
        // =============================================
        // ESPECIALIDADES
        // =============================================
        $specialtyData = [
            ['Medicina General',  'Atención primaria y seguimiento integral de la salud del paciente.'],
            ['Cardiología',       'Diagnóstico y tratamiento de enfermedades del corazón y sistema cardiovascular.'],
            ['Dermatología',      'Cuidado y tratamiento de enfermedades de la piel, cabello y uñas.'],
            ['Pediatría',         'Atención médica especializada en la salud de niños y adolescentes.'],
            ['Traumatología',     'Tratamiento de lesiones del aparato locomotor, huesos y articulaciones.'],
            ['Neurología',        'Diagnóstico y tratamiento de enfermedades del sistema nervioso central y periférico.'],
            ['Ginecología',       'Atención a la salud del aparato reproductor femenino y seguimiento obstétrico.'],
            ['Oncología',         'Diagnóstico y tratamiento del cáncer mediante terapias especializadas.'],
            ['Psiquiatría',       'Diagnóstico y tratamiento de trastornos mentales y del comportamiento.'],
            ['Endocrinología',    'Tratamiento de enfermedades hormonales como diabetes y problemas de tiroides.'],
            ['Oftalmología',      'Diagnóstico y tratamiento de enfermedades de los ojos y la visión.'],
            ['Urología',          'Tratamiento de enfermedades del aparato urinario y reproductor masculino.'],
        ];

        $specialties = [];
        foreach ($specialtyData as [$name, $description]) {
            $s = new Specialty();
            $s->setName($name);
            $s->setDescription($description);
            $s->setIsActive(true);
            $manager->persist($s);
            $specialties[$name] = $s;
        }

        // =============================================
        // ADMIN
        // =============================================
        $admin = $this->createUser('Admin', 'Salute', 'admin@salute.com', '600000000', ['ROLE_ADMIN'], 'admin123');
        $manager->persist($admin);

        // =============================================
        // MÉDICOS — uno por especialidad + algunos extra
        // =============================================
        $doctorsData = [
            // Nombre,       Apellido,     Email,                    Teléfono,     Especialidad,       Colegiado,   Precio, Bio
            ['Carlos',    'Martínez',   'carlos@salute.com',    '611111111', 'Medicina General',  '2841001', 50.00,  'Médico de cabecera con 15 años de experiencia en atención primaria.'],
            ['Laura',     'Sánchez',    'laura@salute.com',     '622222222', 'Cardiología',       '2841002', 80.00,  'Especialista en enfermedades cardiovasculares y arritmias.'],
            ['Miguel',    'López',      'miguel@salute.com',    '633333333', 'Dermatología',      '2841003', 70.00,  'Experto en dermatología clínica y dermatología estética.'],
            ['Sofía',     'García',     'sofia@salute.com',     '644444444', 'Pediatría',         '2841004', 60.00,  'Pediatra con enfoque en desarrollo infantil y vacunación.'],
            ['Andrés',    'Fernández',  'andres@salute.com',    '655555555', 'Traumatología',     '2841005', 75.00,  'Traumatólogo especializado en lesiones deportivas y cirugía ortopédica.'],
            ['Elena',     'Romero',     'elena@salute.com',     '666666666', 'Neurología',        '2841006', 90.00,  'Neuróloga con experiencia en migraña, epilepsia y esclerosis múltiple.'],
            ['Patricia',  'Vega',       'patricia@salute.com',  '677000001', 'Ginecología',       '2841007', 75.00,  'Ginecóloga especializada en salud reproductiva y obstetricia de alto riesgo.'],
            ['Roberto',   'Castillo',   'roberto@salute.com',   '677000002', 'Oncología',         '2841008', 95.00,  'Oncólogo clínico con especialización en tumores sólidos y quimioterapia.'],
            ['Marta',     'Herrera',    'marta@salute.com',     '677000003', 'Psiquiatría',       '2841009', 85.00,  'Psiquiatra especializada en trastornos del ánimo y ansiedad.'],
            ['Javier',    'Molina',     'javier@salute.com',    '677000004', 'Endocrinología',    '2841010', 80.00,  'Endocrinólogo especializado en diabetes tipo 1 y 2, y enfermedades tiroideas.'],
            ['Cristina',  'Navarro',    'cristina@salute.com',  '677000005', 'Oftalmología',      '2841011', 70.00,  'Oftalmóloga especializada en cataratas, glaucoma y cirugía refractiva.'],
            ['Diego',     'Serrano',    'diego@salute.com',     '677000006', 'Urología',          '2841012', 80.00,  'Urólogo con experiencia en litiasis renal y oncología urológica.'],
            // Médicos extra para especialidades con más demanda
            ['Ana',       'Blanco',     'ana.blanco@salute.com','677000007', 'Medicina General',  '2841013', 50.00,  'Médica de familia con amplia experiencia en medicina preventiva.'],
            ['Pablo',     'Ruiz',       'pablo@salute.com',     '677000008', 'Cardiología',       '2841014', 85.00,  'Cardiólogo intervencionista especializado en cateterismo y stents.'],
            ['Lucía',     'Morales',    'lucia@salute.com',     '677000009', 'Pediatría',         '2841015', 60.00,  'Pediatra con especialización en neonatología y cuidados intensivos pediátricos.'],
        ];

        $doctors = [];
        foreach ($doctorsData as [$first, $last, $email, $phone, $specialty, $license, $price, $bio]) {
            $user = $this->createUser($first, $last, $email, $phone, ['ROLE_DOCTOR'], 'doctor123');
            $manager->persist($user);

            $doctor = new Doctor();
            $doctor->setUser($user);
            $doctor->setSpecialty($specialties[$specialty]);
            $doctor->setLicenseNumber($license);
            $doctor->setConsultationPrice($price);
            $doctor->setBio($bio);
            $manager->persist($doctor);
            $doctors[] = $doctor;
        }

        // =============================================
        // PACIENTES
        // =============================================
        $patientsData = [
            ['Juan',      'Pérez',      'juan@email.com',      '677777771', '1985-03-15', 'A+',  'Penicilina'],
            ['María',     'González',   'maria@email.com',     '677777772', '1990-07-22', 'O-',  null],
            ['Pedro',     'Rodríguez',  'pedro@email.com',     '677777773', '1978-11-08', 'B+',  'Aspirina, Ibuprofeno'],
            ['Ana',       'Jiménez',    'ana@email.com',       '677777774', '1995-01-30', 'AB+', null],
            ['Luis',      'Torres',     'luis@email.com',      '677777775', '1982-06-14', 'A-',  'Látex'],
            ['Carmen',    'Díaz',       'carmen@email.com',    '677777776', '2000-09-03', 'O+',  null],
            ['Fernando',  'Moreno',     'fernando@email.com',  '677777777', '1970-12-25', 'B-',  'Polen, Ácaros'],
            ['Isabel',    'Álvarez',    'isabel@email.com',    '677777778', '1988-04-17', 'A+',  null],
            ['Raúl',      'Jiménez',    'raul@email.com',      '677777779', '1975-08-20', 'O+',  'Sulfamidas'],
            ['Natalia',   'Castro',     'natalia@email.com',   '677777780', '1993-02-11', 'B+',  null],
        ];

        $patients = [];
        foreach ($patientsData as [$first, $last, $email, $phone, $dob, $blood, $allergies]) {
            $user = $this->createUser($first, $last, $email, $phone, ['ROLE_PATIENT'], 'patient123');
            $manager->persist($user);

            $patient = new Patient();
            $patient->setUser($user);
            $patient->setDateOfBirth(new \DateTime($dob));
            $patient->setBloodType($blood);
            $patient->setAllergies($allergies);
            $manager->persist($patient);
            $patients[] = $patient;
        }

        // =============================================
        // CITAS
        // =============================================
        $appointmentsData = [
            // [paciente, médico, fecha, estado, motivo, notas]
            [0,  0,  '+0 days 09:00',   'confirmed', 'Revisión anual',          null],
            [1,  1,  '+0 days 11:00',   'confirmed', 'Dolor en el pecho',       null],
            [2,  2,  '+1 days 10:00',   'pending',   'Erupción cutánea',        null],
            [3,  3,  '+1 days 12:00',   'pending',   'Fiebre persistente',      null],
            [4,  4,  '+2 days 09:30',   'confirmed', 'Dolor de rodilla',        null],
            [5,  5,  '+2 days 16:00',   'pending',   'Dolores de cabeza',       null],
            [6,  0,  '+3 days 10:00',   'confirmed', 'Control tensión',         null],
            [7,  1,  '+4 days 11:30',   'pending',   'Palpitaciones',           null],
            [8,  6,  '+1 days 10:30',   'pending',   'Revisión ginecológica',   null],
            [9,  7,  '+3 days 15:00',   'confirmed', 'Control oncológico',      null],
            [0,  8,  '+5 days 09:00',   'pending',   'Ansiedad y estrés',       null],
            [1,  9,  '+2 days 12:00',   'confirmed', 'Control diabetes',        null],
            [2, 10,  '+4 days 11:00',   'pending',   'Revisión visión',         null],
            [3, 11,  '+6 days 10:00',   'pending',   'Cálculos renales',        null],
            // Pasadas completadas
            [0,  2,  '-7 days 09:00',   'completed', 'Revisión piel',           'Dermatitis leve. Crema corticoide prescrita.'],
            [1,  0,  '-5 days 10:00',   'completed', 'Gripe',                   'Reposo 3 días. Paracetamol 1g cada 8h.'],
            [2,  3,  '-3 days 11:00',   'completed', 'Vacuna gripe',            'Vacunado correctamente. Próxima dosis en 1 año.'],
            [3,  4,  '-10 days 09:30',  'completed', 'Esguince tobillo',        'Vendaje compresivo. Reposo 1 semana.'],
            [4,  5,  '-14 days 16:00',  'completed', 'Migraña crónica',         'Tratamiento preventivo iniciado con topiramato.'],
            [5,  6,  '-6 days 10:00',   'completed', 'Revisión embarazo',       'Embarazo semana 12. Todo correcto.'],
            [6,  9,  '-4 days 11:00',   'completed', 'Analítica glucosa',       'HbA1c 6.8%. Ajuste de medicación.'],
            // Canceladas
            [5,  0,  '-2 days 10:00',   'cancelled', 'Revisión general',        null],
            [6,  1,  '-1 days 11:00',   'cancelled', 'Control corazón',         null],
        ];

        foreach ($appointmentsData as [$pIdx, $dIdx, $dateStr, $status, $reason, $notes]) {
            $appointment = new Appointment();
            $appointment->setPatient($patients[$pIdx]);
            $appointment->setDoctor($doctors[$dIdx]);
            $appointment->setAppointmentDate(new \DateTimeImmutable($dateStr));
            $appointment->setStatus($status);
            $appointment->setReason($reason);
            $appointment->setNotes($notes);
            $manager->persist($appointment);
        }

        $manager->flush();

        echo "\n✅ Fixtures cargadas correctamente:\n";
        echo "   👤 admin@salute.com / admin123\n";
        echo "   🩺 carlos@salute.com ... / doctor123  (" . count($doctorsData) . " médicos)\n";
        echo "   🏥 " . count($specialtyData) . " especialidades\n";
        echo "   👥 juan@email.com ... / patient123  (" . count($patientsData) . " pacientes)\n\n";
    }
}