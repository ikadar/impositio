<?php

namespace App\Domain\Geometry\Interfaces;

interface CoordinateInterface
{
    public function getValue(): ?float;
    public function setValue(CoordinateInterface $coordinate): CoordinateInterface;

    public function offset(CoordinateInterface $offset): CoordinateInterface;
}