<?php

namespace App\Domain\Action\Pipeline;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Part\PartProductionContext;

/**
 * Context object passed through the pipeline.
 * Each processor can read and modify this context.
 */
class ActionPathContext
{
    /**
     * @param ActionPathNodeInterface[] $nodes The current list of action path nodes
     * @param float $cutSheetCount Current sheet count in the process
     * @param PartProductionContext $jobContext Part production parameters (immutable). Named 'jobContext' for backward compatibility.
     * @param ActionTreeNodeInterface[] $originalPath Original flat path for reference
     */
    public function __construct(
        public array $nodes,
        public float $cutSheetCount,
        public readonly PartProductionContext $jobContext,
        public readonly array $originalPath,
    ) {}

    /**
     * @deprecated Use $jobContext instead
     */
    public function __get(string $name): mixed
    {
        if ($name === 'params') {
            return $this->jobContext;
        }
        throw new \InvalidArgumentException("Property {$name} does not exist");
    }

    /**
     * Create a new context with updated nodes.
     */
    public function withNodes(array $nodes): self
    {
        return new self(
            $nodes,
            $this->cutSheetCount,
            $this->jobContext,
            $this->originalPath
        );
    }

    /**
     * Create a new context with updated cutSheetCount.
     */
    public function withCutSheetCount(float $cutSheetCount): self
    {
        return new self(
            $this->nodes,
            $cutSheetCount,
            $this->jobContext,
            $this->originalPath
        );
    }

    /**
     * Add a node to the list.
     */
    public function addNode(ActionPathNodeInterface $node): self
    {
        $nodes = $this->nodes;
        $nodes[] = $node;
        return $this->withNodes($nodes);
    }

    /**
     * Insert a node at a specific position.
     */
    public function insertNodeAt(int $position, ActionPathNodeInterface $node): self
    {
        $nodes = $this->nodes;
        array_splice($nodes, $position, 0, [$node]);
        return $this->withNodes($nodes);
    }
}
