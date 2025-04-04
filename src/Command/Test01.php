<?php

namespace App\Command;

use App\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test',
    description: 'description'
)]
class Test01 extends Command
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel, ?string $name = null)
    {
        parent::__construct($name);
        $this->kernel = $kernel;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = [
            "sheet" => [
                "width" => 1000,
                "height" => 500,
            ],
            "zone" => [
                "width" => 120,
                "height" => 79,
            ],
            "cutSpacing" => [
                "horizontal" => 10, // mm
                "vertical" => 10,   // mm
            ]
        ];


        $gridFittings = $this->calculateExhaustiveGridFitting($data["sheet"], $data["zone"], $data["cutSpacing"]);

        dump(count($gridFittings));

        dump($data);

        dump(json_encode($gridFittings, JSON_PRETTY_PRINT));

        $dataDirectory = sprintf("%s/public/data", $this->kernel->getProjectDir());
        $dataFile = sprintf("%s/test-placement.json", $dataDirectory);

        file_put_contents($dataFile, json_encode($gridFittings, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    public function calculateExhaustiveGridFitting(array $boundingArea, array $tileRect, $cutSpacing): array
    {
        $maxCols = floor(($boundingArea["width"]) / ($tileRect["width"] + (2 * $cutSpacing["horizontal"])));
        $maxRows = floor(($boundingArea["height"]) / ($tileRect["height"] + (2 * $cutSpacing["vertical"])));

        dump($maxCols, $maxRows);

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {
                $gridFittings[] = [
                    "size" => sprintf("%dx%d", $colIndex, $rowIndex),
                    "placements" => $this->calculateGridFitting($colIndex, $rowIndex, $tileRect, $cutSpacing)
                ];
            }
        }

        return $gridFittings;
    }

    public function calculateGridFitting($cols, $rows, array $tileRect, array $cutSpacing): array
    {
        $placements = [];
        for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
                $placements[] = [
                    "mmPositions" => [
                        "x" =>
                            (($colIndex + 1) * $cutSpacing["horizontal"]) +
                            (($colIndex) * $cutSpacing["horizontal"]) +
                            $colIndex * ($tileRect["width"]),
                        "y" =>
                            (($rowIndex + 1) * $cutSpacing["vertical"]) +
                            (($rowIndex) * $cutSpacing["vertical"]) +
                            $rowIndex * ($tileRect["height"]),
                        "width" => $tileRect["width"],
                        "height" => $tileRect["height"],
                    ],
                    "mmCutBufferPositions" => [
                        "x" => $colIndex * ($tileRect["width"] + (2 * $cutSpacing["horizontal"])),
                        "y" => $rowIndex * ($tileRect["height"] + (2 * $cutSpacing["vertical"])),
                        "width" => $tileRect["width"] + (2 * $cutSpacing["horizontal"]),
                        "height" => $tileRect["height"] + (2 * $cutSpacing["vertical"]),
                    ],
                    "gridPosition" => [
                        "x" => $colIndex,
                        "y" => $rowIndex,
                    ]
                ];
            }
        }

        return $placements;
    }

}