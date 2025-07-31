<?php

namespace App\Domain\Part;

use App\Domain\Geometry\Dimensions;
use App\Domain\Part\Interfaces\PartInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Part implements PartInterface
{
    static protected PartType $type;
    protected string $id;
    protected int $entityId;

    protected Dimensions $openDimensions;
    protected Dimensions $closedDimensions;

    protected array $mediumData;

    protected array $actions;

    protected array $requiredParts;

    public function __construct($partData)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $this->setId($accessor->getValue($partData, "[partId]"));
        $this->setEntityId($accessor->getValue($partData, "[id]") ?: null);
        $this->setDimensions($accessor->getValue($partData["properties"], "[dimensions]"));
        $this->setMediumData($accessor->getValue($partData["properties"], "[medium]"));
        $this->setRequiredParts($accessor->getValue($partData, "[required_parts]") ?: []);
    }

    public static function getType(): PartType
    {
        return self::$type;
    }

    public function getOpenDimensions(): Dimensions
    {
        return $this->openDimensions;
    }

    public function setOpenDimensions(Dimensions $openDimensions): Part
    {
        $this->openDimensions = $openDimensions;
        return $this;
    }

    public function getClosedDimensions(): Dimensions
    {
        return $this->closedDimensions;
    }

    public function setClosedDimensions(Dimensions $closedDimensions): Part
    {
        $this->closedDimensions = $closedDimensions;
        return $this;
    }

    public function getMediumData(): array
    {
        return $this->mediumData;
    }

    public function setMediumData(?array $mediumData): Part
    {
        if ($mediumData === null) {
            throw new \InvalidArgumentException("Medium must not be null");
        }
        $this->mediumData = $mediumData;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): Part
    {
        $this->id = $id;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): Part
    {
        $this->entityId = $entityId;
        return $this;
    }



    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): Part
    {
        $this->actions = $actions;
        return $this;
    }

    public function getRequiredParts(): array
    {
        return $this->requiredParts;
    }

    public function setRequiredParts(array $requiredParts): Part
    {
        $this->requiredParts = $requiredParts;
        return $this;
    }



    protected function setDimensions(?string $dimensionString): void
    {
        if ($dimensionString === null) {
            throw new \InvalidArgumentException("Dimensions must not be null");
        }

        [$open, $closed] = explode('/', $dimensionString);
        [$closedWidth, $closedHeight] = explode('x', $closed);
        [$openWidth, $openHeight] = explode('x', $open);
        $size = [
            'closed' => [
                'width' => (float) $closedWidth,
                'height' => (float) $closedHeight,
            ],
            'open' => [
                'width' => (float) $openWidth,
                'height' => (float) $openHeight,
            ],
        ];

        $this->setOpenDimensions(new Dimensions(
            $size["open"]["width"],
            $size["open"]["height"]
        ));

        $this->setClosedDimensions(new Dimensions(
            $size["closed"]["width"],
            $size["closed"]["height"]
        ));
    }

    public function getDimensions(): string
    {
        return sprintf(
            "%dx%d/%dx%d",
            $this->getOpenDimensions()->getWidth(),
            $this->getOpenDimensions()->getHeight(),
            $this->getClosedDimensions()->getWidth(),
            $this->getClosedDimensions()->getHeight()
        );
    }
}