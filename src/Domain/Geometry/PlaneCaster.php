<?php

namespace App\Domain\Geometry;

use Symfony\Component\VarDumper\Cloner\Stub;

class PlaneCaster
{
    public static function castPlane(Plane $plane, array $a, Stub $stub, bool $isNested): array
    {
        return [
            "id" => $plane->getId(),
            "children" => $plane->getChildren(),
        ];
    }
}