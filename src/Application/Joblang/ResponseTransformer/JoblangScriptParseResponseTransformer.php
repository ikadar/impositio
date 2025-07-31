<?php

namespace App\Application\Joblang\ResponseTransformer;

use App\Application\Joblang\UseCase\ParseJoblangScript\JoblangScriptParseResponseModel;
use App\Domain\Part\Interfaces\PartInterface;

class JoblangScriptParseResponseTransformer
{
    public function __construct(
    ) {
    }

    public function transform(JoblangScriptParseResponseModel $responseModel): array
    {
        $metadata = $responseModel->metaData;
        $parts = $responseModel->parts;
        $numberOfCopies = $metadata['quantity'];

        return [
            'scriptId' => $responseModel->scriptId,
            'metaData' => $metadata,
            'parts' => $this->partsToArray($parts, $numberOfCopies)
        ];

    }

    protected function partsToArray(array $parts, int $numberOfCopies): array
    {
        $array = [];
        foreach ($parts as $part) {
            $partArray = $this->toArray($part);
            $partArray["numberOfCopies"] = $numberOfCopies;
            $array[] = $partArray;
        }
        return $array;
    }

    protected function toArray(PartInterface $part): array
    {
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

        return [
            "id" => $part->getEntityId(),
            "partId" => $part->getId(),
            "requiredParts" => $part->getRequiredParts(),
            "actions" => $part->getActions(),
            "size" => $part->getDimensions(),
            "medium" => $part->getMediumData(),
            "zone" => $zone
        ];
    }
}