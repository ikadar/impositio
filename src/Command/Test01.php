<?php

namespace App\Command;

use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Interfaces\GeometryFactoryInterface;
use App\Domain\Geometry\MyCliDumper;
use App\Domain\Geometry\Plane;
use App\Domain\Sheet\Interfaces\PrintFactoryInterface;
use App\Domain\Sheet\PrintFactory;
use App\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

#[AsCommand(
    name: 'app:test',
    description: 'description'
)]
class Test01 extends Command
{
    private Kernel $kernel;
    private PrintFactory $printFactory;
    private Plane $plane;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        Kernel                   $kernel,
        PrintFactory             $printFactory,
        Plane                    $plane,
        PropertyAccessorInterface $propertyAccessor,
        MyCliDumper              $dumper,
        ?string                  $name = null
    )
    {
        parent::__construct($name);
        $this->plane = $plane;
        $this->plane->setId("plane");
        $this->printFactory = $printFactory;
        $this->kernel = $kernel;
        $this->propertyAccessor = $propertyAccessor;
        $this->dumper = $dumper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $sheet = $this->printFactory->newInputSheet("inputSheet", 0, 0, 400, 350);
        $sheet->setGripMarginSize(20);

        dump($sheet->toJson());

        die();

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