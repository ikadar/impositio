<?php

namespace App\Domain\Job;

use App\Domain\Job\Interfaces\JobInterface;
use App\Domain\Part\Interfaces\PartFactoryInterface;

class Job implements Interfaces\JobInterface
{
    protected array $parsed;
    public function __construct(
        protected PartFactoryInterface $partFactory,
    )
    {
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
        $partsData = $this->flatten($this->parsed["jobData"]["parts"]);

        $parts = [];
        foreach ($partsData as $loop => $partData) {
            $part = $this->partFactory->create($partData);
            $part->setId(sprintf("PART%04d", $loop+1));
            $parts[] = $part;
        }

        return $parts;
    }

    public function flatten(array $parts): array
    {
        $flatPartList = [];
        foreach ($parts as $part) {
            if (is_array($part)) {
                if (array_key_exists("type", $part)) {
                    $flatPartList[] = $part;
                } else {
                    $flatPartList = array_merge($flatPartList, $this->flatten($part));
                }
            }
        }
        return $flatPartList;
    }
}