<?php

namespace App\Domain\Joblang;

use App\Domain\Joblang\Interfaces\JoblangServiceInterface;
use App\Entity\Job;
use App\Entity\JoblangLine;
use App\Entity\JoblangScript;
use App\Infrastructure\Mapper\JobMapper;
use App\Infrastructure\Mapper\PartMapper;
use Doctrine\ORM\EntityManagerInterface;

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

    public function parseAndPersistScript(string $scriptText): JoblangScript
    {
        $script = new JoblangScript();
        $script->setScript($scriptText);
        $lines = $this->explodeScript($script);
        foreach ($lines as $line) {
            $this->em->persist($line);
            $job = new Job();
            $job->setCode($line->getParsed()["metaData"]["jobNumber"]);
            $job->setJoblangLine($line);
            $this->em->persist($job);
            $script->addLine($line);

            $jobDomain = $this->jobMapper->toDomain($script->getLines()[0]->getJob());
            foreach ($jobDomain->getParts() as $part) {
                $partEntity = $this->partMapper->toEntity($part);
                $partEntity->setJob($job);
                $this->em->persist($partEntity);
            }

        }
        $this->em->persist($script);
        $this->em->flush();

        return $script;
    }

    public function explodeScript(JoblangScript $script): array
    {
        $lines = [];

        $line = new JoblangLine();
        $line->setJoblangScript($script);
        $line->setSource($script->getScript());
        $line->setParsed($this->parseJobLine($line->getSource()));

        $lines[] = $line;
        return $lines;
    }

    public function parseJobLine($jobLine): array
    {

        $cmd = sprintf("node %s $'%s'", $this->jisonParserPath, $jobLine);

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
}