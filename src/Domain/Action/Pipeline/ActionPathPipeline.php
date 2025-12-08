<?php

namespace App\Domain\Action\Pipeline;

/**
 * Pipeline that processes action paths through a series of processors.
 * Processors are executed in order of their priority (lower = earlier).
 */
class ActionPathPipeline
{
    /** @var ActionPathProcessorInterface[] */
    private array $processors = [];

    /**
     * @param iterable<ActionPathProcessorInterface> $processors
     */
    public function __construct(iterable $processors)
    {
        $this->processors = $processors instanceof \Traversable
            ? iterator_to_array($processors)
            : $processors;

        // Sort by priority (lower = earlier)
        usort(
            $this->processors,
            fn(ActionPathProcessorInterface $a, ActionPathProcessorInterface $b) =>
                $a->getPriority() <=> $b->getPriority()
        );
    }

    /**
     * Process an action path context through all processors.
     */
    public function process(ActionPathContext $context): ActionPathContext
    {
        foreach ($this->processors as $processor) {
            $context = $processor->process($context);
        }

        return $context;
    }

    /**
     * Get the list of processors in execution order.
     *
     * @return ActionPathProcessorInterface[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
