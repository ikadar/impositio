<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EquipmentFactory implements Interfaces\EquipmentFactoryInterface
{
    public function __construct(
        protected EquipmentService $equipmentService,
        protected PrintFactory $printFactory,
    )
    {
    }

    public function fromId(string $id): MachineInterface
    {
        $machineData = $this->equipmentService->loadById($id);
        $machineType = MachineType::tryFrom($machineData["type"]);
        if ($machineType === null) {
            dump($machineData["type"]); die();
            throw new BadRequestHttpException();
        }

        switch ($machineType) {
            case MachineType::PrintingPress:
                return $this->createPrintingPress($id, $machineData);
            case MachineType::StitchingMachine:
                return $this->createStitchingMachine($id, $machineData);
            case MachineType::Folder:
                return $this->createFolder($id, $machineData);
            case MachineType::CuttingMachine:
                return $this->createCuttingMachine($id, $machineData);
            case MachineType::CTPMachine:
                return $this->createCTPMachine($id, $machineData);
        }

        throw new BadRequestHttpException();
    }

    public function fromType(MachineType $type): array
    {
        $machines = [];
        $machineConfigs = $this->equipmentService->loadByType($type);
        foreach ($machineConfigs as $machineConfig) {
            $machines[] = $this->create($machineConfig);
        }
        return $machines;
    }

    protected function create($data): MachineInterface
    {
        $machineType = MachineType::tryFrom($data["type"]);
        if ($machineType === null) {
            throw new BadRequestHttpException();
        }
        switch ($machineType) {
            case MachineType::PrintingPress:
                return $this->createPrintingPress($data["id"], $data);
            case MachineType::StitchingMachine:
                return $this->createStitchingMachine($data["id"], $data);
            case MachineType::Folder:
                return $this->createFolder($data["id"], $data);
            case "cutting machine":
            case "ctp machine":
                break;
        }

    }

    protected function createFolder($id, $data)
    {
        return new Folder(
            $id,
            MachineType::Folder,
            $data["gripMargin"],
            new Dimensions(
                $data["input-dimensions"]["min"]["width"],
                $data["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["input-dimensions"]["max"]["width"],
                $data["input-dimensions"]["max"]["height"]
            ),
            $this->printFactory,
            $this->equipmentService
        );
    }

    protected function createCuttingMachine($id, $data)
    {
        return new CuttingMachine(
            $id,
            MachineType::CuttingMachine,
            $data["gripMargin"],
            new Dimensions(
                $data["input-dimensions"]["min"]["width"],
                $data["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["input-dimensions"]["max"]["width"],
                $data["input-dimensions"]["max"]["height"]
            ),
            $this->printFactory,
            $this->equipmentService
        );
    }

    protected function createCTPMachine($id, $data)
    {
        return new CTPMachine(
            $id,
            MachineType::CTPMachine,
            $data["gripMargin"],
            new Dimensions(
                $data["input-dimensions"]["min"]["width"],
                $data["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["input-dimensions"]["max"]["width"],
                $data["input-dimensions"]["max"]["height"]
            ),
            $this->printFactory,
            $this->equipmentService
        );
    }

    protected function createPrintingPress($id, $data)
    {
        return new OffsetPrintingPress(
            $id,
            MachineType::PrintingPress,
            $data["gripMargin"],
            new Dimensions(
                $data["input-dimensions"]["min"]["width"],
                $data["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["input-dimensions"]["max"]["width"],
                $data["input-dimensions"]["max"]["height"]
            ),
//            $data["base-setup-duration"],
//            $data["setup-duration-per-color"],
//            $data["max-input-stack-height"],
//            $data["stack-replenishment-duration"],
//            $data["sheets-per-hour"],
            $this->printFactory,
            $this->equipmentService
        );
    }

    protected function createStitchingMachine($id, $data)
    {
        return new StitchingMachine(
            $id,
            MachineType::StitchingMachine,
            $data["gripMargin"],
            new Dimensions(
                $data["input-dimensions"]["min"]["width"],
                $data["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["input-dimensions"]["max"]["width"],
                $data["input-dimensions"]["max"]["height"]
            ),
            $this->printFactory,
            $this->equipmentService
        );
    }

}