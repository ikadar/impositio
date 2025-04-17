<?php

namespace App\Domain\Geometry;

enum AlignmentPoint
{
    case Top;
    case Middle;
    case Bottom;
    case Left;
    case Center;
    case Right;

    case TopLeft;
    case TopCenter;
    case TopRight;
    case MiddleLeft;
    case MiddleCenter;
    case MiddleRight;
    case BottomLeft;
    case BottomCenter;
    case BottomRight;

    public function xFactor(): ?float
    {
        return match ($this) {
            self::Left, self::TopLeft, self::MiddleLeft, self::BottomLeft => 0.0,
            self::Center, self::TopCenter, self::MiddleCenter, self::BottomCenter => 0.5,
            self::Right, self::TopRight, self::MiddleRight, self::BottomRight => 1.0,
            self::Top, self::Middle, self::Bottom => null,
        };
    }

    public function yFactor(): ?float
    {
        return match ($this) {
            self::Top, self::TopLeft, self::TopCenter, self::TopRight => 0.0,
            self::Middle, self::MiddleLeft, self::MiddleCenter, self::MiddleRight => 0.5,
            self::Bottom, self::BottomLeft, self::BottomCenter, self::BottomRight => 1.0,
            self::Left, self::Center, self::Right => null,
        };
    }
}
