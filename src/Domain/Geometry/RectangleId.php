<?php

namespace App\Domain\Geometry;

class RectangleId
{
    protected ?string $value;
    protected bool $initialized = false;
    public function __construct()
    {
    }

    public function __toString(): string
    {
        if (!$this->initialized) {
            return "";
        }
        return $this->value;
    }

    public function setValue(?string $value): RectangleId
    {
        $this->initialized = true;
        $this->value = $value;
        return $this;
    }



}