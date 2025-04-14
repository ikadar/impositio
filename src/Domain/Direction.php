<?php

namespace App\Domain;

use App\Domain\Interfaces\PositionInterface;
use App\Domain\Interfaces\PositionedRectangleInterface;

enum Direction
{

    case TopLeft;
    case TopCenter;
    case TopRight;
    case MiddleLeft;
    case MiddleCenter;
    case MiddleRight;
    case BottomLeft;
    case BottomCenter;
    case BottomRight;

    public function xFactor(): float
    {
        return match ($this) {
            self::TopLeft, self::MiddleLeft, self::BottomLeft => -1,
            self::TopCenter, self::MiddleCenter, self::BottomCenter => -0.5,
            self::TopRight, self::MiddleRight, self::BottomRight => 0,
        };
    }

    public function yFactor(): float
    {
        return match ($this) {
            self::TopLeft, self::TopCenter, self::TopRight => -1,
            self::MiddleLeft, self::MiddleCenter, self::MiddleRight => -0.5,
            self::BottomLeft, self::BottomCenter, self::BottomRight => 0,
        };
    }

}
