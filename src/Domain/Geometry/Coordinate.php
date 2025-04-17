<?php

namespace App\Domain\Geometry;

use App\Domain\Geometry\Interfaces\CoordinateInterface;

class Coordinate implements CoordinateInterface
{
    public function __construct(
        protected ?float $value = null,
    )
    {}
    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(CoordinateInterface $coordinate): CoordinateInterface
    {
        $this->value = $coordinate->getValue();
        return $this;
    }

    public function offset(CoordinateInterface $offset): CoordinateInterface
    {
        if ($this->getValue() === null) {
            throw new \Exception("Can't offset an uninitialised coordinate");
        }

        if ($offset->getValue() === null) {
            throw new \Exception("Can't offset a coordinate by an uninitialised offset");
        }

        $value = $this->getValue();
        $offsetValue  = $offset->getValue();

        return $this->setValue(new Coordinate($value + $offsetValue));
    }
}