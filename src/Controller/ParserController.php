<?php

namespace App\Controller;

use App\Domain\Action\AbstractAction;
use App\Domain\Action\Action;
use App\Domain\Action\ActionPathNode;
use App\Domain\Action\ActionTreeNode;
use App\Domain\Action\ActionType;
use App\Domain\Action\Interfaces\ActionInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Part\Interfaces\PartFactoryInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use App\Domain\Sheet\PrintFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class ParserController extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;
    protected array $pose;
    protected array $abstractActionData;

    public function __construct(
        protected KernelInterface $kernel,
        protected PartFactoryInterface $partFactory,
    )
    {
    }

    #[Route(path: '/parse', requirements: [], methods: ['POST'])]
    public function display(): JsonResponse
    {
        $jobLine = $this->processPayload();
        $parsedJobLine = $this->parseJobLine($jobLine);

        $metadata = $parsedJobLine[0]["metaData"];

        $jobId = $metadata["jobNumber"];
        $numberOfCopies = $metadata["quantity"];

        $parts = $this->flattenJob($parsedJobLine);

        $response = [
            "metaData" => $metadata,
            "parts" => $this->partsToArray($parts, $numberOfCopies)
        ];

        return new JsonResponse(
            $response,
            JsonResponse::HTTP_OK
        );
    }

    public function processPayload(): string
    {
        $request = Request::createFromGlobals();
        $data = $request->getContent();

        return trim($data);
    }

    public function parseJobLine($jobLine): array
    {
        $jisonParserPath = "/Users/istvan/Code/jison-test/index.js";

        $cmd = sprintf("node %s $'%s'", $jisonParserPath, $jobLine);

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // don't need to write to stdin

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            if ($returnCode === 0) {
                return json_decode($stdout, true);
            }

            throw new \Exception($stderr);
        }

        return [];
    }

    public function flattenJob(array $job): array
    {
        $partsData = $this->flatten($job[0]["jobData"]["parts"]);

        $parts = [];
        foreach ($partsData as $loop => $partData) {
            $part = $this->partFactory->create($partData);
            $part->setId(sprintf("PART%04d", $loop+1));
            $parts[] = $part;
        }

        return $parts;
    }

    public function flatten(array $parts): array
    {
        $flatPartList = [];
        foreach ($parts as $part) {
            if (is_array($part)) {
                if (array_key_exists("type", $part)) {
                    $flatPartList[] = $part;
                } else {
                    $flatPartList = array_merge($flatPartList, $this->flatten($part));
                }
            }
        }
        return $flatPartList;
    }

    public function partsToArray(array $parts, $numberOfCopies): array
    {
        $array = [];
        foreach ($parts as $part) {

            $zone = [
                "width" => $part->getClosedDimensions()->getWidth(),
                "height" => $part->getClosedDimensions()->getHeight(),
                "type" => "Zone",
                "gripMargin" => [
                    "size" => 0,
                    "position" => null,
                    "x" => 0,
                    "y" => 0,
                    "width" => 0,
                    "height" => 0
                ]
            ];

            $partArray = [
                "partId" => $part->getId(),
                "numberOfCopies" => $numberOfCopies,
                "actions" => $part->getActions(),
                "size" => $part->getDimensions(),
                "medium" => $part->getMediumData(),
                "zone" => $zone
            ];
            $array[] = $partArray;
        }
        return $array;
    }

}