<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Processor that inserts verso (back side) printing action after recto printing.
 * Verso printing uses the same machine but with different inking.
 */
class VersoPrintingProcessor implements ActionPathProcessorInterface
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

    public function process(ActionPathContext $context): ActionPathContext
    {
        $newNodes = [];

        foreach ($context->nodes as $node) {
            /** @var ActionPathNodeInterface $node */
            $newNodes[] = $node;

            // Check if this is a printing press action with verso inking
            if ($node->getMachine()->getType()->value === 'printing press') {
                $versoAction = $this->createVersoActionIfNeeded($node, $context);
                if ($versoAction !== null) {
                    $newNodes[] = $versoAction;
                }
            }
        }

        return new ActionPathContext(
            $newNodes,
            $context->cutSheetCount,
            $context->params,
            $context->originalPath
        );
    }

    private function createVersoActionIfNeeded(
        ActionPathNodeInterface $rectoNode,
        ActionPathContext $context
    ): ?ActionPathNode {
        // Check if there's verso inking
        $versoInking = $this->propertyAccessor->getValue($context->params->inking, '[verso]');

        if (!is_array($versoInking) || $versoInking === []) {
            return null;
        }

        // Create verso printing action with the same machine
        $todo = [
            'numberOfCopies' => $context->params->numberOfCopies,
            'numberOfColors' => $context->params->numberOfColors,
            'paperWeight' => $context->params->paperWeight,
            'cutSheetCount' => $context->cutSheetCount,
            'dryTimeBetweenSequences' => 0,
        ];

        return new ActionPathNode(
            $rectoNode->getMachine(),
            $rectoNode->getPressSheet(),
            $rectoNode->getZone(),
            clone $rectoNode->getGridFitting(),
            $todo
        );
    }

    public function getPriority(): int
    {
        return 30;
    }
}
