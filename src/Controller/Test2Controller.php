<?php

namespace App\Controller;

use App\Domain\AlignmentMode;
use App\Domain\Dimensions;
use App\Domain\Direction;
use App\Domain\Interfaces\GeometryFactoryInterface;
use App\Domain\Plane;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Test2Controller extends AbstractController
{
    public function __construct(
        protected Kernel                   $kernel,
        protected GeometryFactoryInterface $geometryFactory,
        protected Plane                    $plane,
    )
    {
        $this->plane->setId("plane");
    }

    #[Route(path: '/test2', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $r1 = $this->geometryFactory->newRectangle("r1", 0, 0, 350, 350);
        $r1->placeOnto($this->plane, $this->geometryFactory->newPosition(100, 100));

        $cr1 = $this->geometryFactory->newRectangle("cr1", 0, 0, 100, 30);
        $r1->placeChild($cr1, $this->geometryFactory->newPosition(0, 0));
        $cr1->stretchXTo($r1, 10);
        $cr1->alignTo($r1, AlignmentMode::TopCenterToTopCenter);
        $cr1->offset(0, 5);

        $cr2 = $this->geometryFactory->newRectangle("cr2", 0, 0, 100, 100);
        $r1->placeChild($cr2, $this->geometryFactory->newPosition(0, 100));
        $cr2->stretchXTo($r1, 10);
        $cr2->stretchY($cr1->getAbsoluteBottom(), $r1->getAbsoluteBottom() - 10);
        $cr2->alignTo($cr1, AlignmentMode::TopCenterToBottomCenter);
        $cr2->offset(0, 5);

        // -----

        $r1b = $this->geometryFactory->newRectangle("r1b", 0, 0, 350, 350);
        $r1b->placeOnto($this->plane, $this->geometryFactory->newPosition(100, 500));

        $cr1b = $this->geometryFactory->newRectangle("cr1", 0, 0, 30, 100);
        $r1b->placeChild($cr1b, $this->geometryFactory->newPosition(0, 0));
        $cr1b->stretchYTo($r1b);
        $cr1b->alignTo($r1b, AlignmentMode::MiddleLeftToMiddleLeft);

        $cr2b = $this->geometryFactory->newRectangle("cr2", 0, 0, 100, 100);
        $r1b->placeChild($cr2b, $this->geometryFactory->newPosition(0, 100));
        $cr2b->stretchX($cr1b->getAbsoluteRight(), $r1b->getAbsoluteRight());
        $cr2b->stretchYTo($r1b);
        $cr2b->alignTo($cr1b, AlignmentMode::MiddleLeftToMiddleRight);





//        $cr1->resize(new Dimensions(150, 150), Direction::BottomRight);
//        $cr1->resize(new Dimensions(150, 150), Direction::BottomLeft);
//        $cr1->resize(new Dimensions(150, 150), Direction::BottomCenter);

//        $cr1->resize(new Dimensions(150, 150), Direction::MiddleRight);
//        $cr1->resize(new Dimensions(150, 150), Direction::MiddleLeft);
//        $cr1->resize(new Dimensions(150, 150), Direction::MiddleCenter);

//        $cr1->resize(new Dimensions(150, 150), Direction::TopRight);
//        $cr1->resize(new Dimensions(150, 150), Direction::TopLeft);
//        $cr1->resize(new Dimensions(150, 150), Direction::TopCenter);

        return new JsonResponse(
            json_decode($this->plane->toJson(), true),
            JsonResponse::HTTP_OK
        );

    }
}