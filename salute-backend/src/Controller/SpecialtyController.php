<?php

namespace App\Controller;

use App\Entity\Specialty;
use App\Repository\SpecialtyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/specialties')]
class SpecialtyController extends AbstractController
{
    #[Route('', name: 'specialties_index', methods: ['GET'])]
    public function index(SpecialtyRepository $repo): JsonResponse
    {
        $specialties = $repo->findAll();

        $data = array_map(fn(Specialty $s) => [
            'id' => $s->getId(),
            'name' => $s->getName(),
            'description' => $s->getDescription(),
            'isActive' => $s->isActive(),
        ], $specialties);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'specialties_show', methods: ['GET'])]
    public function show(Specialty $specialty): JsonResponse
    {
        return $this->json([
            'id' => $specialty->getId(),
            'name' => $specialty->getName(),
            'description' => $specialty->getDescription(),
            'isActive' => $specialty->isActive(),
        ]);
    }

    #[Route('', name: 'specialties_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['name'])) {
            return $this->json(['message' => 'Name is required'], 400);
        }

        $specialty = new Specialty();
        $specialty->setName($data['name']);
        $specialty->setDescription($data['description'] ?? null);
        $specialty->setIsActive($data['isActive'] ?? true);

        $em->persist($specialty);
        $em->flush();

        return $this->json([
            'id' => $specialty->getId(),
            'name' => $specialty->getName(),
            'description' => $specialty->getDescription(),
            'isActive' => $specialty->isActive(),
        ], 201);
    }

    #[Route('/{id}', name: 'specialties_update', methods: ['PUT'])]
    public function update(Specialty $specialty, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $specialty->setName($data['name']);
        if (isset($data['description'])) $specialty->setDescription($data['description']);
        if (isset($data['isActive'])) $specialty->setIsActive($data['isActive']);

        $em->flush();

        return $this->json([
            'id' => $specialty->getId(),
            'name' => $specialty->getName(),
            'description' => $specialty->getDescription(),
            'isActive' => $specialty->isActive(),
        ]);
    }

    #[Route('/{id}', name: 'specialties_delete', methods: ['DELETE'])]
    public function delete(Specialty $specialty, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($specialty);
        $em->flush();

        return $this->json(['message' => 'Specialty deleted'], 200);
    }
}