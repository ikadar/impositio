<?php

namespace App\Domain\Action\Pipeline;

/**
 * Interface for action path processors.
 * Each processor handles a specific aspect of extending an action path.
 */
interface ActionPathProcessorInterface
{
    /**
     * Process the context and return the modified context.
     */
    public function process(ActionPathContext $context): ActionPathContext;

    /**
     * Get the priority of this processor.
     * Lower priority = runs earlier in the pipeline.
     *
     * Suggested priority ranges:
     * - 10-19: Todo preparation
     * - 20-29: Pre-action insertion (CTP)
     * - 30-39: Post-action insertion (verso printing)
     * - 40-49: Layout-based insertion (cutting)
     * - 50-59: State tracking (cutSheetCount)
     */
    public function getPriority(): int;
}
