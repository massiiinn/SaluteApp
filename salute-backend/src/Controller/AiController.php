<?php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/ai')]
class AiController extends AbstractController
{
    #[Route('/suggest-specialty', name: 'ai_suggest_specialty', methods: ['POST'])]
    public function suggestSpecialty(
        Request $request,
        #[Autowire('%env(GROQ_API_KEY)%')] string $apiKey
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['symptoms'])) {
            return $this->json(['message' => 'symptoms is required'], 400);
        }

        try {
            $client = HttpClient::create();

            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'Eres un asistente médico de una clínica española. El usuario describe sus síntomas y tú sugieres qué especialidad médica debería consultar. Responde siempre en español, de forma breve y clara. Primero indica la especialidad recomendada en negrita usando **, luego una explicación corta de 1-2 frases. Especialidades disponibles: Medicina General, Cardiología, Dermatología, Pediatría, Traumatología, Neurología, Ginecología. Si no encaja con ninguna, recomienda Medicina General. Nunca des diagnósticos, solo sugiere la especialidad.'
                        ],
                        [
                            'role'    => 'user',
                            'content' => 'Mis síntomas son: ' . $data['symptoms']
                        ]
                    ],
                    'max_tokens'  => 200,
                    'temperature' => 0.3,
                ]
            ]);

            $result = json_decode($response->getContent(false), true);
            if ($response->getStatusCode() !== 200) {
                return $this->json(['message' => $result['error']['message'] ?? 'Error de la IA'], 500);
            }

            return $this->json(['suggestion' => $result['choices'][0]['message']['content'] ?? '']);

        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al contactar con la IA: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/chat', name: 'ai_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        AppointmentRepository $appointmentRepo,
        DoctorRepository $doctorRepo,
        PatientRepository $patientRepo,
        #[Autowire('%env(GROQ_API_KEY)%')] string $apiKey
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['messages'])) {
            return $this->json(['message' => 'messages is required'], 400);
        }

        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        $roles       = $currentUser->getRoles();
        $isDoctor    = in_array('ROLE_DOCTOR', $roles);
        $isAdmin     = in_array('ROLE_ADMIN', $roles);

        $context  = "Usuario: {$currentUser->getFirstName()} {$currentUser->getLastName()}\n";
        $context .= "Rol: " . ($isAdmin ? 'Administrador' : ($isDoctor ? 'Médico' : 'Paciente')) . "\n";
        $context .= "Fecha y hora actual: " . (new \DateTime())->format('d/m/Y H:i') . "\n\n";

        if ($isAdmin) {
            $appointments = $appointmentRepo->findAll();
        } elseif ($isDoctor) {
            $doctor       = $doctorRepo->findOneBy(['user' => $currentUser]);
            $appointments = $doctor ? $appointmentRepo->findBy(['doctor' => $doctor]) : [];
        } else {
            $patient      = $patientRepo->findOneBy(['user' => $currentUser]);
            $appointments = $patient ? $appointmentRepo->findBy(['patient' => $patient]) : [];
        }

        $context .= "CITAS DEL USUARIO:\n";
        if (empty($appointments)) {
            $context .= "- No tiene citas registradas\n";
        } else {
            foreach (array_slice($appointments, 0, 15) as $a) {
                $context .= sprintf(
                    "- ID:%d | %s | Dr. %s %s (%s) | Estado: %s | Motivo: %s\n",
                    $a->getId(),
                    $a->getAppointmentDate()->format('d/m/Y H:i'),
                    $a->getDoctor()->getUser()->getFirstName(),
                    $a->getDoctor()->getUser()->getLastName(),
                    $a->getDoctor()->getSpecialty()?->getName() ?? 'General',
                    $a->getStatus(),
                    $a->getReason() ?? 'Sin motivo'
                );
            }
        }

        $doctors  = $doctorRepo->findAll();
        $context .= "\nMÉDICOS DISPONIBLES:\n";
        foreach ($doctors as $d) {
            $context .= sprintf(
                "- ID:%d | Dr. %s %s | %s\n",
                $d->getId(),
                $d->getUser()->getFirstName(),
                $d->getUser()->getLastName(),
                $d->getSpecialty()?->getName() ?? 'General'
            );
        }

        $systemPrompt = <<<PROMPT
Eres Salute Assistant, el asistente inteligente de la clínica Salute. Ayudas a los usuarios a gestionar sus citas médicas.

CONTEXTO DEL USUARIO:
{$context}

CAPACIDADES — solo puedes hacer estas 4 acciones:
1. CREAR una nueva cita
2. CANCELAR una cita existente
3. REPROGRAMAR una cita (cambiar fecha y/u hora)
4. CAMBIAR EL MÉDICO de una cita existente

REGLAS ESTRICTAS PARA GENERAR ACCIONES:
- SOLO genera una ACTION cuando el usuario haya confirmado EXPLÍCITAMENTE con "sí", "confirmar", "adelante" o similar.
- NUNCA generes una ACTION si el usuario no ha confirmado explícitamente.
- NUNCA generes una ACTION para preguntas, recordatorios o consultas de información.
- Pide confirmación UNA SOLA VEZ. Cuando el usuario confirme, genera la ACTION directamente.
- Si falta algún dato, pregúntalo antes de generar la acción.

MANEJO DE FECHAS:
- NUNCA calcules fechas relativas como "mañana", "el jueves que viene", etc.
- Si el usuario dice una fecha relativa, pregúntale la fecha exacta en formato DD/MM/YYYY y la hora.
- Solo genera la ACTION cuando tengas la fecha exacta en formato YYYY-MM-DD HH:MM:00.

IMPORTANTE SOBRE LAS ACCIONES:
- La línea ACTION debe ir AL FINAL del mensaje, sola, sin texto después.
- El sistema muestra la confirmación automáticamente, no la repitas.
- Antes de la ACTION escribe solo una frase corta confirmando que procedes.

SUGERENCIA DE ESPECIALIDAD:
- Si el usuario describe síntomas y elige un médico de especialidad no relacionada, adviértele amablemente y sugiere el especialista más adecuado. Respeta su decisión si insiste.

FORMATO DE ACCIONES:

Para CREAR una cita:
ACTION:CREATE_APPOINTMENT:{"doctorId":ID,"date":"YYYY-MM-DD HH:MM:00","reason":"motivo"}

Para CANCELAR una cita:
ACTION:CANCEL_APPOINTMENT:{"appointmentId":ID}

Para REPROGRAMAR una cita (solo cambia fecha/hora):
ACTION:RESCHEDULE_APPOINTMENT:{"appointmentId":ID,"date":"YYYY-MM-DD HH:MM:00"}

Para CAMBIAR EL MÉDICO de una cita (solo cambia el médico):
ACTION:CHANGE_DOCTOR:{"appointmentId":ID,"doctorId":ID}

ESTILO:
- Responde siempre en español, amigable y conciso.
- Para consultas de información, responde sin ACTION.
- NUNCA menciones precios, pagos ni costes.
- No inventes funcionalidades que no existen.
- NUNCA menciones el ID de una cita que aún no existe. Solo menciona IDs de citas que ya están en el contexto.
PROMPT;

        try {
            $client = HttpClient::create();

            $messages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $data['messages']
            );

            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'llama-3.1-8b-instant',
                    'messages'    => $messages,
                    'max_tokens'  => 400,
                    'temperature' => 0.2,
                ]
            ]);

            $content = $response->getContent(false);
            $result  = json_decode($content, true);

            if ($response->getStatusCode() !== 200) {
                return $this->json(['message' => $result['error']['message'] ?? 'Error de la IA'], 500);
            }

            $text = $result['choices'][0]['message']['content'] ?? '';

            $action = null;
            if (preg_match('/ACTION:([A-Z_]+):(\{.*?\})/s', $text, $matches)) {
                $action = [
                    'type' => $matches[1],
                    'data' => json_decode($matches[2], true)
                ];
                $text = trim(preg_replace('/\*{0,2}ACTION:[A-Z_]+:\{.*?\}\*{0,2}/s', '', $text));
            }

            return $this->json(['message' => $text, 'action' => $action]);

        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al contactar con la IA: ' . $e->getMessage()], 500);
        }
    }
}