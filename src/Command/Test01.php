<?php

namespace App\Command;

use App\Domain\AlignmentMode;
use App\Domain\AlignmentPoint;
use App\Domain\Coordinate;
use App\Domain\Dimensions;
use App\Domain\Interfaces\GeometryFactoryInterface;
use App\Domain\MyCliDumper;
use App\Domain\Plane;
use App\Domain\PlaneCaster;
use App\Domain\Position;
use App\Domain\PositionedRectangle;
use App\Domain\AbstractRectangle;
use App\Domain\Rectangle;
use App\Domain\RectangleCaster;
use App\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

#[AsCommand(
    name: 'app:test',
    description: 'description'
)]
class Test01 extends Command
{
    private Kernel $kernel;
    private GeometryFactoryInterface $geometryFactory;
    private Plane $plane;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        Kernel                   $kernel,
        GeometryFactoryInterface $geometryFactory,
        Plane                    $plane,
        PropertyAccessorInterface $propertyAccessor,
        MyCliDumper              $dumper,
        ?string                  $name = null
    )
    {
        parent::__construct($name);
        $this->plane = $plane;
        $this->plane->setId("plane");
        $this->geometryFactory = $geometryFactory;
        $this->kernel = $kernel;
        $this->propertyAccessor = $propertyAccessor;
        $this->dumper = $dumper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $r1 = $this->geometryFactory->newRectangle("r1", 0, 0, 350, 350);

        $cr1 = $this->geometryFactory->newRectangle("cr1", 0, 0, 100, 100);
        $r1->placeChild($cr1, $this->geometryFactory->newPosition(0, 0));

//        $cr2 = $this->geometryFactory->newRectangle("cr2", 0, 0, 20, 20);
//        $cr2->placeOnto($r1, $this->geometryFactory->newPosition(50, 50));

//        $gcr1 = $this->geometryFactory->newRectangle("gcr1", 20, 25, 20, 20);

//        $gcr1->placeOnto($cr2, $this->geometryFactory->newPosition(0, 0));
//        $gcr1->placeOnto($cr1, $this->geometryFactory->newPosition(55, 80));

        $r1->placeOnto($this->plane, $this->geometryFactory->newPosition(100, 100));

//        $r1 = $this->plane->getChildById("r1");
//        $cr1r1 = $this->plane->getChildById("r1.cr1");
//        $gcr1 = $this->plane->getChildById("r1.cr1.gcr1");

        $cr1->alignTo($r1, AlignmentMode::MiddleCenterToMiddleCenter);

        $this->plane->dump();



        return Command::SUCCESS;
    }
}