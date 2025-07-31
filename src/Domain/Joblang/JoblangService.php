<?php

namespace App\Domain\Joblang;

use App\Domain\Joblang\Interfaces\JoblangServiceInterface;
use App\Entity\Job;
use App\Entity\JoblangLine;
use App\Entity\JoblangScript;
use App\Entity\Part as PartEntity;
use App\Infrastructure\Mapper\JobMapper;
use App\Infrastructure\Mapper\PartMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Uid\Uuid;

class JoblangService implements JoblangServiceInterface
{
    protected $jisonParserPath;
    public function __construct(
        protected EntityManagerInterface $em,
        protected JobMapper $jobMapper,
        protected PartMapper $partMapper,
    )
    {
        $this->jisonParserPath = $_ENV["JISON_PARSER_PATH"];
    }

    public function parseScript(string $scriptText): array
    {
        $sourceLines = array_filter(explode("[", $scriptText), function ($item) {
            return trim($item) !== "";
        });

        $sourceLines = array_map(function ($item) {
            return "[" . trim($item);
        }, $sourceLines);

        $lines = [];

        foreach ($sourceLines as $sourceLine) {

            $parsed = $this->parseJobLine($sourceLine);
            $parts = $parsed["jobData"]["parts"];
            $this->addUuid($parts);
            $partsData = $this->flatten($parts);

            foreach ($partsData as $loop => $partData) {
                $partsData[$loop]["partId"] = sprintf("PART%04d", $loop + 1);
            }

            foreach ($partsData as $loop => $partData) {
                if (array_key_exists("requiredBy", $partData)) {
                    $requiredBys = $partData["requiredBy"];
                    $requirings = array_filter($partsData, function ($item) use ($requiredBys) {
                        return in_array($item["uuid"], $requiredBys);
                    });

                    foreach ($requirings as $requiring) {
                        foreach ($partsData as $loop2 => $partData2) {
                            if ($partData2["uuid"] == $requiring["uuid"]) {
                                $partsData[$loop2]["required_parts"][] = $partData["uuid"];
                            }
                        }
                    }
                }
            }


            $accessor = PropertyAccess::createPropertyAccessor();
            foreach ($partsData as $loop => $part) {

                $requiredPartIds = [];
                $requiredParts = $accessor->getValue($part, "[required_parts]") ?: [];
                foreach ($requiredParts as $requiredPartUuid) {
                    $requiredPartIds[] = $this->getPartIdByUUid($partsData, $requiredPartUuid);
                }
                $partsData[$loop]["required_parts"] = $requiredPartIds;
            }

            foreach ($partsData as $loop => $partData) {
                unset($partsData[$loop]["uuid"]);
                unset($partsData[$loop]["requiredBy"]);
            }

            $parsed["jobData"]["parts"] = $partsData;

            $lines[] = [
                "source" => $sourceLine,
                "parsed" => $parsed
            ];
        }

        return [
            "source" => $scriptText,
            "lines" => $lines
        ];
    }

    public function persistScript(array $parsedScript): JoblangScript
    {
        $script = new JoblangScript();
        $script->setScript($parsedScript["source"]);

        foreach ($parsedScript["lines"] as $line) {

            $lineEntity = new JoblangLine();
            $lineEntity->setJoblangScript($script);
            $lineEntity->setSource($line["source"]);
            $lineEntity->setParsed($line["parsed"]);
            $this->em->persist($lineEntity);
            $script->addLine($lineEntity); // ???

            $job = new Job();
            $job->setCode($lineEntity->getParsed()["metaData"]["jobNumber"]);
            $job->setJoblangLine($lineEntity);
            $this->em->persist($job);

            foreach ($line["parsed"]["jobData"]["parts"] as $partLoop => $part) {
                $partEntity = new PartEntity();
                $partEntity->setpartId($part["partId"]);
                $partEntity->setJob($job);
                $partEntity->setJson($part);
                $this->em->persist($partEntity);
            }
        }

        $this->em->persist($script);
        $this->em->flush();

        return $script;
    }


    public function parseJobLine($jobLine): array
    {

//        $cmd = sprintf("node %s $'%s'", $this->jisonParserPath, $jobLine);
$cmd = sprintf("node %s '%s'", $this->jisonParserPath, $jobLine);

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // don't need to write to stdin

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            if ($returnCode === 0) {
                return json_decode($stdout, true)[0];
            }

            throw new \Exception($stderr);
        }

        return [];
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

    public function addUuid(&$parts)
    {
        foreach ($parts as $loop => $part) {
            if (is_array($part)) {
                if (array_key_exists("type", $part)) {
                    $parts[$loop]["uuid"] = Uuid::v4()->toString();
                } else {
                    $this->addUuid($parts[$loop]);
                }
            }
        }
    }

    public function getPartIdByUUid($parts, $uuid) {
        $part = array_values(array_filter($parts, function ($item) use ($uuid) {
            return $item["uuid"] == $uuid;
        }))[0];
        return $part["partId"];
    }

}