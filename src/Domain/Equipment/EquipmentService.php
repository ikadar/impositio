<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

class EquipmentService implements EquipmentServiceInterface
{
    protected string $configFilePath;

    public function __construct(
        protected KernelInterface $kernel,
    )
    {
        $this->configFilePath = sprintf("%s/%s",
            $this->kernel->getProjectDir(),
            'resources/equipment.yaml'
        );
    }

    public function load(): array
    {
        return Yaml::parseFile($this->configFilePath);
    }

}