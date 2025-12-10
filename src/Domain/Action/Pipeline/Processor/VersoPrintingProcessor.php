<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use App\Domain\Action\PrintActionParams;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Processor that inserts verso (back side) printing action after recto printing.
 * Verso printing uses the same machine but with different inking.
 *
 * Now uses PrintActionParams from the node instead of JobContext for inking info.
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

            // Check if this is a printing press action with verso inking
            // Note: The path is in reverse order (backtrace), so we insert verso BEFORE recto in the array,
            // which means verso will be AFTER recto in the actual production flow.
            if ($node->getMachine()->getType()->value === 'printing press') {
                $versoAction = $this->createVersoActionIfNeeded($node, $context);
                if ($versoAction !== null) {
                    $newNodes[] = $versoAction;
                }
            }

            $newNodes[] = $node;
        }

        return new ActionPathContext(
            $newNodes,
            $context->cutSheetCount,
            $context->jobContext,
            $context->originalPath
        );
    }

    private function createVersoActionIfNeeded(
        ActionPathNodeInterface $rectoNode,
        ActionPathContext $context
    ): ?ActionPathNode {
        // Check if there's verso inking - prefer action-level printParams, fallback to context
        $printParams = $rectoNode->getPrintParams();

        if ($printParams !== null) {
            // New way: use PrintActionParams from the node
            if (!$printParams->hasVerso()) {
                return null;
            }
            // Create verso-specific PrintActionParams with just verso inking
            $versoPrintParams = new PrintActionParams([
                'recto' => $printParams->getVersoInks(),
                'verso' => [],
            ]);
        } else {
            // Legacy fallback: use JobContext inking
            $versoInking = $this->propertyAccessor->getValue($context->jobContext->inking, '[verso]');

            if (!is_array($versoInking) || $versoInking === []) {
                return null;
            }
            // Create verso-specific PrintActionParams
            $versoPrintParams = new PrintActionParams([
                'recto' => $versoInking,
                'verso' => [],
            ]);
        }

        $gridFitting = clone $rectoNode->getGridFitting();

        // Calculate enrichment using the new method
        $enrichment = $rectoNode->getMachine()->calculateEnrichment(
            $context->jobContext,
            $gridFitting,
            $rectoNode->getPressSheet(),
            $context->cutSheetCount,
        );

        return new ActionPathNode(
            $rectoNode->getMachine(),
            $rectoNode->getPressSheet(),
            $rectoNode->getZone(),
            $gridFitting,
            $enrichment,
            $versoPrintParams  // Pass verso print params
        );
    }

    public function getPriority(): int
    {
        return 30;
    }
}
