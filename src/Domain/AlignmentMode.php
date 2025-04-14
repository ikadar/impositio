<?php

namespace App\Domain;

enum AlignmentMode
{
    case TopToTop;
    case TopToMiddle;
    case TopToBottom;
    case MiddleToTop;
    case MiddleToMiddle;
    case MiddleToBottom;
    case BottomToTop;
    case BottomToMiddle;
    case BottomToBottom;

    case LeftToLeft;
    case LeftToCenter;
    case LeftToRight;
    case CenterToLeft;
    case CenterToCenter;
    case CenterToRight;
    case RightToLeft;
    case RightToCenter;
    case RightToRight;

    case TopLeftToTopLeft;
    case TopLeftToTopCenter;
    case TopLeftToTopRight;
    case TopLeftToMiddleLeft;
    case TopLeftToMiddleCenter;
    case TopLeftToMiddleRight;
    case TopLeftToBottomLeft;
    case TopLeftToBottomCenter;
    case TopLeftToBottomRight;

    case TopCenterToTopLeft;
    case TopCenterToTopCenter;
    case TopCenterToTopRight;
    case TopCenterToMiddleLeft;
    case TopCenterToMiddleCenter;
    case TopCenterToMiddleRight;
    case TopCenterToBottomLeft;
    case TopCenterToBottomCenter;
    case TopCenterToBottomRight;

    case TopRightToTopLeft;
    case TopRightToTopCenter;
    case TopRightToTopRight;
    case TopRightToMiddleLeft;
    case TopRightToMiddleCenter;
    case TopRightToMiddleRight;
    case TopRightToBottomLeft;
    case TopRightToBottomCenter;
    case TopRightToBottomRight;

    case MiddleLeftToTopLeft;
    case MiddleLeftToTopCenter;
    case MiddleLeftToTopRight;
    case MiddleLeftToMiddleLeft;
    case MiddleLeftToMiddleCenter;
    case MiddleLeftToMiddleRight;
    case MiddleLeftToBottomLeft;
    case MiddleLeftToBottomCenter;
    case MiddleLeftToBottomRight;

    case MiddleCenterToTopLeft;
    case MiddleCenterToTopCenter;
    case MiddleCenterToTopRight;
    case MiddleCenterToMiddleLeft;
    case MiddleCenterToMiddleCenter;
    case MiddleCenterToMiddleRight;
    case MiddleCenterToBottomLeft;
    case MiddleCenterToBottomCenter;
    case MiddleCenterToBottomRight;

    case MiddleRightToTopLeft;
    case MiddleRightToTopCenter;
    case MiddleRightToTopRight;
    case MiddleRightToMiddleLeft;
    case MiddleRightToMiddleCenter;
    case MiddleRightToMiddleRight;
    case MiddleRightToBottomLeft;
    case MiddleRightToBottomCenter;
    case MiddleRightToBottomRight;

    case BottomLeftToTopLeft;
    case BottomLeftToTopCenter;
    case BottomLeftToTopRight;
    case BottomLeftToMiddleLeft;
    case BottomLeftToMiddleCenter;
    case BottomLeftToMiddleRight;
    case BottomLeftToBottomLeft;
    case BottomLeftToBottomCenter;
    case BottomLeftToBottomRight;

    case BottomCenterToTopLeft;
    case BottomCenterToTopCenter;
    case BottomCenterToTopRight;
    case BottomCenterToMiddleLeft;
    case BottomCenterToMiddleCenter;
    case BottomCenterToMiddleRight;
    case BottomCenterToBottomLeft;
    case BottomCenterToBottomCenter;
    case BottomCenterToBottomRight;

    case BottomRightToTopLeft;
    case BottomRightToTopCenter;
    case BottomRightToTopRight;
    case BottomRightToMiddleLeft;
    case BottomRightToMiddleCenter;
    case BottomRightToMiddleRight;
    case BottomRightToBottomLeft;
    case BottomRightToBottomCenter;
    case BottomRightToBottomRight;

    public function alignmentPoints(): array
    {
        return match ($this) {
            self::TopToTop => ["what" => AlignmentPoint::Top, "to" => AlignmentPoint::Top],
            self::TopToMiddle => ["what" => AlignmentPoint::Top, "to" => AlignmentPoint::Middle],
            self::TopToBottom => ["what" => AlignmentPoint::Top, "to" => AlignmentPoint::Bottom],
            self::MiddleToTop => ["what" => AlignmentPoint::Middle, "to" => AlignmentPoint::Top],
            self::MiddleToMiddle => ["what" => AlignmentPoint::Middle, "to" => AlignmentPoint::Middle],
            self::MiddleToBottom => ["what" => AlignmentPoint::Middle, "to" => AlignmentPoint::Bottom],
            self::BottomToTop => ["what" => AlignmentPoint::Bottom, "to" => AlignmentPoint::Top],
            self::BottomToMiddle => ["what" => AlignmentPoint::Bottom, "to" => AlignmentPoint::Middle],
            self::BottomToBottom => ["what" => AlignmentPoint::Bottom, "to" => AlignmentPoint::Bottom],

            self::LeftToLeft => ["what" => AlignmentPoint::Left, "to" => AlignmentPoint::Left],
            self::LeftToCenter => ["what" => AlignmentPoint::Left, "to" => AlignmentPoint::Center],
            self::LeftToRight => ["what" => AlignmentPoint::Left, "to" => AlignmentPoint::Right],
            self::CenterToLeft => ["what" => AlignmentPoint::Center, "to" => AlignmentPoint::Left],
            self::CenterToCenter => ["what" => AlignmentPoint::Center, "to" => AlignmentPoint::Center],
            self::CenterToRight => ["what" => AlignmentPoint::Center, "to" => AlignmentPoint::Right],
            self::RightToLeft => ["what" => AlignmentPoint::Right, "to" => AlignmentPoint::Left],
            self::RightToCenter => ["what" => AlignmentPoint::Right, "to" => AlignmentPoint::Center],
            self::RightToRight => ["what" => AlignmentPoint::Right, "to" => AlignmentPoint::Right],

            self::TopLeftToTopLeft => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::TopLeft],
            self::TopLeftToTopCenter => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::TopCenter],
            self::TopLeftToTopRight => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::TopRight],
            self::TopLeftToMiddleLeft => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::MiddleLeft],
            self::TopLeftToMiddleCenter => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::MiddleCenter],
            self::TopLeftToMiddleRight => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::MiddleRight],
            self::TopLeftToBottomLeft => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::BottomLeft],
            self::TopLeftToBottomCenter => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::BottomCenter],
            self::TopLeftToBottomRight => ["what" => AlignmentPoint::TopLeft, "to" => AlignmentPoint::BottomRight],

            self::TopCenterToTopLeft => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::TopLeft],
            self::TopCenterToTopCenter => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::TopCenter],
            self::TopCenterToTopRight => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::TopRight],
            self::TopCenterToMiddleLeft => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::MiddleLeft],
            self::TopCenterToMiddleCenter => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::MiddleCenter],
            self::TopCenterToMiddleRight => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::MiddleRight],
            self::TopCenterToBottomLeft => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::BottomLeft],
            self::TopCenterToBottomCenter => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::BottomCenter],
            self::TopCenterToBottomRight => ["what" => AlignmentPoint::TopCenter, "to" => AlignmentPoint::BottomRight],

            self::TopRightToTopLeft => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::TopLeft],
            self::TopRightToTopCenter => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::TopCenter],
            self::TopRightToTopRight => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::TopRight],
            self::TopRightToMiddleLeft => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::MiddleLeft],
            self::TopRightToMiddleCenter => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::MiddleCenter],
            self::TopRightToMiddleRight => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::MiddleRight],
            self::TopRightToBottomLeft => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::BottomLeft],
            self::TopRightToBottomCenter => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::BottomCenter],
            self::TopRightToBottomRight => ["what" => AlignmentPoint::TopRight, "to" => AlignmentPoint::BottomRight],

            self::MiddleLeftToTopLeft => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::TopLeft],
            self::MiddleLeftToTopCenter => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::TopCenter],
            self::MiddleLeftToTopRight => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::TopRight],
            self::MiddleLeftToMiddleLeft => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::MiddleLeft],
            self::MiddleLeftToMiddleCenter => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::MiddleCenter],
            self::MiddleLeftToMiddleRight => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::MiddleRight],
            self::MiddleLeftToBottomLeft => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::BottomLeft],
            self::MiddleLeftToBottomCenter => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::BottomCenter],
            self::MiddleLeftToBottomRight => ["what" => AlignmentPoint::MiddleLeft, "to" => AlignmentPoint::BottomRight],

            self::MiddleCenterToTopLeft => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::TopLeft],
            self::MiddleCenterToTopCenter => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::TopCenter],
            self::MiddleCenterToTopRight => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::TopRight],
            self::MiddleCenterToMiddleLeft => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::MiddleLeft],
            self::MiddleCenterToMiddleCenter => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::MiddleCenter],
            self::MiddleCenterToMiddleRight => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::MiddleRight],
            self::MiddleCenterToBottomLeft => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::BottomLeft],
            self::MiddleCenterToBottomCenter => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::BottomCenter],
            self::MiddleCenterToBottomRight => ["what" => AlignmentPoint::MiddleCenter, "to" => AlignmentPoint::BottomRight],

            self::MiddleRightToTopLeft => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::TopLeft],
            self::MiddleRightToTopCenter => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::TopCenter],
            self::MiddleRightToTopRight => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::TopRight],
            self::MiddleRightToMiddleLeft => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::MiddleLeft],
            self::MiddleRightToMiddleCenter => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::MiddleCenter],
            self::MiddleRightToMiddleRight => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::MiddleRight],
            self::MiddleRightToBottomLeft => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::BottomLeft],
            self::MiddleRightToBottomCenter => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::BottomCenter],
            self::MiddleRightToBottomRight => ["what" => AlignmentPoint::MiddleRight, "to" => AlignmentPoint::BottomRight],

            self::BottomLeftToTopLeft => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::TopLeft],
            self::BottomLeftToTopCenter => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::TopCenter],
            self::BottomLeftToTopRight => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::TopRight],
            self::BottomLeftToMiddleLeft => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::MiddleLeft],
            self::BottomLeftToMiddleCenter => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::MiddleCenter],
            self::BottomLeftToMiddleRight => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::MiddleRight],
            self::BottomLeftToBottomLeft => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::BottomLeft],
            self::BottomLeftToBottomCenter => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::BottomCenter],
            self::BottomLeftToBottomRight => ["what" => AlignmentPoint::BottomLeft, "to" => AlignmentPoint::BottomRight],

            self::BottomCenterToTopLeft => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::TopLeft],
            self::BottomCenterToTopCenter => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::TopCenter],
            self::BottomCenterToTopRight => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::TopRight],
            self::BottomCenterToMiddleLeft => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::MiddleLeft],
            self::BottomCenterToMiddleCenter => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::MiddleCenter],
            self::BottomCenterToMiddleRight => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::MiddleRight],
            self::BottomCenterToBottomLeft => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::BottomLeft],
            self::BottomCenterToBottomCenter => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::BottomCenter],
            self::BottomCenterToBottomRight => ["what" => AlignmentPoint::BottomCenter, "to" => AlignmentPoint::BottomRight],

            self::BottomRightToTopLeft => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::TopLeft],
            self::BottomRightToTopCenter => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::TopCenter],
            self::BottomRightToTopRight => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::TopRight],
            self::BottomRightToMiddleLeft => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::MiddleLeft],
            self::BottomRightToMiddleCenter => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::MiddleCenter],
            self::BottomRightToMiddleRight => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::MiddleRight],
            self::BottomRightToBottomLeft => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::BottomLeft],
            self::BottomRightToBottomCenter => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::BottomCenter],
            self::BottomRightToBottomRight => ["what" => AlignmentPoint::BottomRight, "to" => AlignmentPoint::BottomRight],
        };
    }

}
