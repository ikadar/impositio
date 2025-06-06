<?php

namespace App\Domain\Part;

use App\Domain\Part\Interfaces\PartFactoryInterface;

class PartFactory implements PartFactoryInterface
{
    public function create($partData)
    {
        $partType = PartType::tryFrom($partData["type"]);
        if ($partType === null) {
            throw new \Exception("Part type '{$partData["type"]}' not found");
        }

        return match ($partType) {
            PartType::Leaflet => $this->createLeaflet($partData),
            PartType::Feuillet => $this->createFeuillet($partData),
        };
    }

    protected function createLeaflet(array $data): Leaflet
    {
        return new Leaflet($data);
    }

    protected function createFeuillet(array $data): Feuillet
    {
        return new Feuillet($data);
    }


}