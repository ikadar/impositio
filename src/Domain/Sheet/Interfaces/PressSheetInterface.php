<?php

namespace App\Domain\Sheet\Interfaces;

interface PressSheetInterface extends SheetInterface
{
    public function getPrice(): float;
}