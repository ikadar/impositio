<?php

namespace App\Domain\Action;

class ActionTreeNode extends ActionPathNode implements Interfaces\ActionTreeNodeInterface
{
    public array $prevActions = [];

    public function getPrevActions(): array
    {
        return $this->prevActions;
    }

    public function setPrevActions(array $prevActions): ActionTreeNode
    {
        $this->prevActions = $prevActions;
        return $this;
    }


}