<?php

namespace App\Domain\Part;

use App\Domain\Part\Interfaces\LeafletInterface;

class Leaflet extends Part implements LeafletInterface
{
    public function __construct($partData)
    {
        self::$type = PartType::Leaflet;
        $this->actions = [
            ["type" =>  "stitching"],
            ["type" =>  "folding"],
            ["type" =>  "printing"]
        ];
//        $this->actions = [
//            ["type" =>  "folding"],
//            ["type" =>  "printing"]
//        ];
        parent::__construct($partData);
    }
}