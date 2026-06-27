<?php

namespace App\Http\User\Controller;

use App\Domain\Equipment\Entity\Equipment;
use App\Domain\Equipment\Enum\EquipmentStatus;
use App\Domain\Equipment\Form\EquipmentType;
use App\Domain\Equipment\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/user", name: "user_")]
final class EquipmentController extends AbstractController
{
    private const CSRF_ID = "equipment";

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Predefined camping/caravan packing lists, by locale.
     */
    private const PRESET_LISTS = [
        "fr" => [
            "Tente",
            "Sac de couchage",
            "Matelas gonflable",
            "Réchaud de camping",
            "Bouteille de gaz",
            "Glacière",
            "Lampe frontale",
            "Table pliante",
            "Chaises pliantes",
            "Trousse de premiers secours",
            "Kit de vaisselle",
            "Couteau multifonction",
            "Bâche de sol",
            "Câble électrique caravane",
            "Cales de roue",
            "Tuyau d'eau potable",
            "Produit vaisselle biodégradable",
            "Sacs poubelle",
            "Anti-moustiques",
            "Chargeur portable",
        ],
        "en" => [
            "Tent",
            "Sleeping bag",
            "Air mattress",
            "Camping stove",
            "Gas bottle",
            "Cooler",
            "Headlamp",
            "Folding table",
            "Folding chairs",
            "First aid kit",
            "Cookware set",
            "Multi-tool knife",
            "Ground sheet",
            "Caravan power cable",
            "Wheel chocks",
            "Fresh water hose",
            "Biodegradable dish soap",
            "Bin bags",
            "Insect repellent",
            "Power bank",
        ],
    ];

    #[Route("/equipment", name: "equipment_index", methods: ["GET"])]
    public function index(EquipmentRepository $repository): Response
    {
        return $this->render("user/equipment/index.html.twig", [
            "equipments" => $repository->findOrdered(),
            "form" => $this->createForm(EquipmentType::class, new Equipment()),
        ]);
    }

    #[Route("/equipment", name: "equipment_create", methods: ["POST"])]
    public function create(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $equipment = new Equipment();
        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        $now = new \DateTimeImmutable();
        $equipment
            ->setStatus(EquipmentStatus::InProgress)
            ->setOrdre($repository->getTopOrdre())
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $em->persist($equipment);
        $em->flush();

        return $this->rowResponse($equipment, Response::HTTP_CREATED);
    }

    #[Route("/equipment/generate", name: "equipment_generate", methods: ["POST"])]
    public function generate(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $names = self::PRESET_LISTS[$request->getLocale()] ?? self::PRESET_LISTS["fr"];
        $ordre = $repository->getTopOrdre();

        $rows = [];
        // Reverse so the first preset item ends up at the very top of the list.
        foreach (array_reverse($names) as $name) {
            $equipment = $this->newEquipment($name, $ordre--);
            $em->persist($equipment);
            $rows[] = $equipment;
        }
        $em->flush();

        // Return rows in display order (top first).
        $html = "";
        foreach (array_reverse($rows) as $equipment) {
            $html .= $this->renderView("user/equipment/_row.html.twig", ["equipment" => $equipment]);
        }

        return new JsonResponse(["html" => $html, "count" => \count($rows)], Response::HTTP_CREATED);
    }

    #[Route("/equipment/reorder", name: "equipment_reorder", methods: ["POST"])]
    public function reorder(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $index = 0;
        foreach ($ids as $id) {
            $equipment = $repository->find($id);
            if ($equipment) {
                $equipment->setOrdre($index++);
                $equipment->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/equipment/status", name: "equipment_status", methods: ["POST"])]
    public function status(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        $statusKey = (string) $request->request->get("status", "");
        try {
            $status = EquipmentStatus::fromKey($statusKey);
        } catch (\ValueError) {
            return new JsonResponse(["error" => "invalid_status"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($ids as $id) {
            $equipment = $repository->find($id);
            if ($equipment) {
                $equipment->setStatus($status);
                $equipment->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true, "status" => $status->key()]);
    }

    #[Route("/equipment/bulk-delete", name: "equipment_bulk_delete", methods: ["POST"])]
    public function bulkDelete(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($ids as $id) {
            $equipment = $repository->find($id);
            if ($equipment) {
                $em->remove($equipment);
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/equipment/{id}", name: "equipment_update", methods: ["POST"], requirements: ["id" => "\\d+"])]
    public function update(int $id, Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $equipment = $repository->find($id);
        if (!$equipment) {
            return new JsonResponse(["error" => "not_found"], Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        $equipment->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->rowResponse($equipment);
    }

    #[Route("/equipment/{id}", name: "equipment_delete", methods: ["DELETE"], requirements: ["id" => "\\d+"])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $equipment = $repository->find($id);
        if ($equipment) {
            $em->remove($equipment);
            $em->flush();
        }

        return new JsonResponse(["ok" => true]);
    }

    private function newEquipment(string $name, int $ordre): Equipment
    {
        $now = new \DateTimeImmutable();

        return (new Equipment())
            ->setName($name)
            ->setStatus(EquipmentStatus::InProgress)
            ->setOrdre($ordre)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
    }

    private function rowResponse(Equipment $equipment, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            "html" => $this->renderView("user/equipment/_row.html.twig", ["equipment" => $equipment]),
        ], $status);
    }

    private function formErrorsResponse(FormInterface $form): JsonResponse
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $this->translator->trans(
                $error->getMessage(),
                $error->getMessageParameters(),
                "validators",
            );
        }

        return new JsonResponse(["errors" => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return int[]
     */
    private function idsFromRequest(Request $request): array
    {
        return array_values(array_filter(array_map(
            "intval",
            (array) $request->request->all("ids"),
        )));
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get("X-CSRF-Token", "");
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $token)) {
            throw $this->createAccessDeniedException("Invalid CSRF token.");
        }
    }
}
