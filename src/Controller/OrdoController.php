<?php

namespace App\Controller;

use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\MachineType;
use App\Entity\ActionPath;
use App\Entity\Job;
use App\Service\Interfaces\FtpUploaderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class OrdoController extends AbstractController
{
    protected array $payload;

    public function __construct(
        protected KernelInterface $kernel,
        protected SluggerInterface $slugger,
        protected PropertyAccessorInterface $propertyAccessor,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected FtpUploaderInterface $ftpUploader,
        protected EntityManagerInterface $em,
    )
    {
    }

    #[Route(path: '/ordo', requirements: [], methods: ['POST'])]
    public function ordo(
    ): JsonResponse
    {

        $this->processPayload();

        $deadline = $this->payload["metaData"]["deadline"];
        $deadline = substr($deadline, 1);
        $deadline = explode(" ", $deadline);

        $deadlineOffset = array_pop($deadline);
        $deadline = implode(" ", $deadline);
        $deadline = str_replace("/", "-", $deadline);

        $ordoPayload = [
            "id" => $this->payload["metaData"]["jobNumber"],
            "designation" => "Brochure Alternance - 12+4 - A4 - pelli brillant - 2500ex", // this is probably the meta part of the joblang
            "technique"  => [ // "technique" here is redundant, and it varies in parts
                "type_de_papier"  => "couché brillant",
                "grammage"  => 250,
                "format_feuillesr"  => "70x102",
                "format_ouvert"  => "A4",
                "format_fermé"  => "A5",
                "pagination/volets"  => 96
            ],
            "client" => $this->payload["metaData"]["client"],
            "deadline" => $deadline, // format: "20/09/2024 13h00",
            "deadline_imperative" => true, // needs parse change to extract it, perhaps an exclamation mark means it
            "deadline_BAT" => "15/09/2024 13h00", // ask about it
            "BAT" => true, // ask about it
            "required_jobs" => [], // this is not implemented yet
            "parts" => []
        ];

        foreach ($this->payload["paths"] as $partId => $path) {
            $mediumType = null;
            $mediumProps = $this->propertyAccessor->getValue($path, "[medium][name]");
            if (is_array($mediumProps)) {
                $mediumType = implode(" ", $mediumProps);
            }

            $mediumWeight = $this->propertyAccessor->getValue($path, "[medium][weight]");

            $part = [
                "part_id" => $partId,
                "designation" => "cahier 8 pages", // it is not present in the joblang
                "technique"  => [
                    "type_de_papier"  => $mediumType,
                    "grammage"  => floatval($mediumWeight),
                    "format_feuillesr"  => "70x102", // what are the different formats? open, closed, etc?
                    "format_ouvert"  => $path["openPoseDimensions"], // "A4",
                    "format_fermé"  => $path["closedPoseDimensions"], // "A5",
                    "pagination/volets"  => 96
                ],
                "actions" => [],
                "required_parts" => $path["requiredParts"],
            ];

            foreach ($path["nodes"] as $loop => $node) {

                $machine = $this->equipmentFactory->fromId($node["machine"]);

                if ($machine->getType() === MachineType::PrintingPress) {
                    $dryTimeBetweenSequences = $this->propertyAccessor->getValue($node, "[todo][dryTimeBetweenSequences]");
                    if ($dryTimeBetweenSequences !== null) {
                        $part["actions"][count($part["actions"])-1]["sequences"] = ["recto", "verso"]; // what is there is only recto?
                        $part["actions"][count($part["actions"])-1]["dry_time_between_sequences"] = $dryTimeBetweenSequences;

                        $action = [
                            "machine" => "sechage",
                            "setup_time" => 0,
                            "run" => 240,
                        ];
                        $part["actions"][] = $action;

                        continue;
                    }
                }

                $action = [
                    "machine" => $node["machine"],
                    "setup_time" => round($node["setupDuration"]),
                    "run" => round($node["runDuration"]),
                ];
                $part["actions"][] = $action;
            }

            $ordoPayload["parts"][] = $part;
        }

        $this->ftpUploader->uploadFtpFiles($ordoPayload);

        $response = [
            "ordo" => $ordoPayload,
            "payload" => $this->payload,
        ];

        return new JsonResponse(
            $response,
            JsonResponse::HTTP_OK
        );
    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $repo = $this->em->getRepository(Job::class);
        $job = $repo->find($data["jobId2"]);
        $metaData = $job->getJoblangLine()->getParsed()["metaData"];
        $metaData["jobId"] = $job->getId();

        $this->payload = [
            "metaData" => $metaData,
            "paths" => $this->loadSelectedPaths($data)
        ];
    }

    protected function loadSelectedPaths($payload): array
    {
        $selectedPaths = [];
        foreach ($payload["selectedUuids"] as $selected) {
            $selectedPaths[$selected["partId"]] = $this->em->getRepository(ActionPath::class)
                ->find($selected["value"])
                ->getJson()
            ;
        }
        return $selectedPaths;
    }

}