<?php

namespace App\Domain\Part;

/**
 * @deprecated Part types are no longer used. Actions are now explicit in the /process endpoint payload.
 * @see \App\Controller\ProcessController
 */
enum PartType: string
{
    case Feuillet = "feuillet";
    case Leaflet = "leaflet";

}
