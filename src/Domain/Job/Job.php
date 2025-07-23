<?php

namespace App\Domain\Job;

use App\Domain\Job\Interfaces\JobInterface;
use App\Domain\Part\Interfaces\PartFactoryInterface;
use App\Domain\Part\Interfaces\PartInterface;

class Job implements Interfaces\JobInterface
{
    protected array $parsed;
    protected array $parts;

    public function __construct(
        protected PartFactoryInterface $partFactory,
    )
    {
        $this->parts = [];
    }

    public function getParsed(): array
    {
        return $this->parsed;
    }

    public function setParsed(array $parsed): Job
    {
        $this->parsed = $parsed;
        return $this;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function setParts(array $parts): Job
    {
        $this->parts = $parts;
        return $this;
    }

    public function addPart(PartInterface $part): Job
    {
        $this->parts[] = $part;
        return $this;
    }

    public function flatten(array $parts): array
    {
        $flatPartList = [];
        foreach ($parts as $loop => $part) {
            if (is_array($part)) {
                if (array_key_exists("type", $part)) {
                    $flatPartList[] = $part;
                } else {
                    $flatPartList = array_merge($flatPartList, $this->flatten($part));
                }
            } elseif (is_string($part) && $part == ">") {
                if (is_array($parts[$loop + 1])) {
                    if (array_key_exists("type", $parts[$loop + 1])) {
                        $requirings = $this->flatten([$parts[$loop + 1]]);
                    } else {
                        $requirings = $this->flatten($parts[$loop + 1]);
                    }
                }
                foreach ($requirings as $requiring) {
                    foreach ($flatPartList as $requiredLoop => $required) {
                        $flatPartList[$requiredLoop]["requiredBy"][] = $requiring["uuid"];
                    }
                }
            }
        }
        return $flatPartList;
    }
}