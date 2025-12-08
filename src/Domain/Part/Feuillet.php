<?php

namespace App\Domain\Part;

use App\Domain\Part\Interfaces\FeuilletInterface;
use App\Domain\Part\Part;

/**
 * @deprecated Part type with hardcoded actions. Use explicit actions in /process endpoint instead.
 * @see \App\Controller\ProcessController
 */
class Feuillet extends Part implements Interfaces\FeuilletInterface
{

    public function __construct($partData)
    {
        self::$type = PartType::Feuillet;
        $this->actions = [
//            ["type" =>  "stitching"],
            ["type" =>  "folding"],
            ["type" =>  "printing"]
        ];
//        $this->actions = [
//            ["type" =>  "saddle-stitching"],
//            ["type" =>  "folding"],
//            ["type" =>  "trimming"], // tech
//            ["type" =>  "printing"]
//        ];
//        $this->actions = [
//            ["type" =>  "assembly-stitching"],
//            ["type" =>  "printing"]
//        ];
        parent::__construct($partData);
    }

}