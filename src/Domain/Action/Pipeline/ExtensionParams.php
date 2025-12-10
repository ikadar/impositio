<?php

namespace App\Domain\Action\Pipeline;

use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Job\JobContext;

/**
 * Parameters needed for extending an action path.
 *
 * @deprecated Use JobContext directly. This class extends JobContext for backward compatibility.
 */
readonly class ExtensionParams extends JobContext
{
    // All properties and methods are inherited from JobContext.
    // This class exists only for backward compatibility.
}
