<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use function PHPUnit\Framework\throwException;

class EquipmentService implements EquipmentServiceInterface
{
    protected string $configFilePath;
    static protected ?array $equipments = null;

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
        if (static::$equipments === null) {
            static::$equipments = Yaml::parseFile($this->configFilePath);
        }
        return static::$equipments;
    }

    public function loadById($id): array
    {
        $equipments = $this->load();
        if (array_key_exists($id, $equipments) === false) {
            throw new \Exception(sprintf("Equipment with id '%s' not found", $id));
        }

        return $equipments[$id];
    }

    public function loadByType(MachineType $type): array
    {
        $equipments = $this->load();
        return array_filter($equipments, function (array $equipment) use ($type) {
            return $equipment["type"] === $type->value;
        });
    }

}