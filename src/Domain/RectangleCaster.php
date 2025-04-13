<?php

namespace App\Domain;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\Stub;

class RectangleCaster
{
    public static function castRectangle(Rectangle $rect, array $a, Stub $stub, bool $isNested): array
    {
        return [
            "id" => $rect->getId(),
            "position" => sprintf("%d; %d", $rect->getPosition()->getX()->getValue(), $rect->getPosition()->getY()->getValue()),
            "dimensions" => sprintf("%d x %d", $rect->getDimensions()->getWidth(), $rect->getDimensions()->getHeight()),
            "children" => $rect->getChildren(),
        ];
    }
}