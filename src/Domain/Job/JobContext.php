<?php

namespace App\Domain\Job;

use App\Domain\Part\PartProductionContext;

/**
 * @deprecated Use PartProductionContext instead. This class is an alias for backward compatibility.
 *
 * The name "JobContext" was misleading - it actually contains part-level data,
 * not job-level data. Use PartProductionContext for new code.
 */
readonly class JobContext extends PartProductionContext
{
    // All functionality inherited from PartProductionContext
}
