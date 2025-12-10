# Refactoring: Todo → Action Enrichment

## Státusz

| Fázis | Feladat | Státusz |
|-------|---------|---------|
| **0** | Regressziós tesztek | ✅ KÉSZ |
| **1** | JobContext bevezetése | ✅ KÉSZ |
| **2** | ActionEnrichmentInterface | ⏳ Következő |
| **3** | Machine.calculateEnrichment() | ⏳ Várakozik |
| **4** | ActionPathNode módosítása | ⏳ Várakozik |
| **5** | Pipeline processzorok | ⏳ Várakozik |
| **6** | Cleanup | ⏳ Várakozik |

## Összefoglaló

A jelenlegi `todo` array helyett tipizált, machine-specifikus enrichment objektumokat vezetünk be. A job-szintű paramétereket külön kontextusba szervezzük.

## Motiváció

### Jelenlegi problémák

1. **Rossz elnevezés**: A "todo" félrevezető - nem tennivaló lista, hanem kalkulált adatok
2. **Típusbiztonság hiánya**: Array helyett objektum kellene
3. **Adatok keveredése**: Job-szintű és action-szintű adatok egy helyen
4. **Duplikáció**: `numberOfCopies`, `paperWeight`, stb. minden node-ban ismétlődik
5. **Machine-specifikus adatok kezelése**: Különböző machine-eknek különböző adatokra van szükségük

### Jelenlegi struktúra

```
ActionPathNode
├── machine
├── pressSheet
├── gridFitting
└── todo: array  ◄── Problémás: típusolatlan, kevert tartalom
    ├── numberOfCopies (job-szintű, duplikált)
    ├── numberOfColors (job-szintű, duplikált)
    ├── paperWeight (job-szintű, duplikált)
    ├── inking (job-szintű, duplikált)
    ├── cutSheetCount (kalkulált)
    └── cost (kalkulált, machine-specifikus breakdown-nal)
```

### Cél struktúra

```
JobContext (egyszer tárolva)
├── numberOfCopies
├── numberOfColors
├── paperWeight
├── inking
├── openPoseDimensions
└── closedPoseDimensions

ActionPathNode
├── machine
├── pressSheet
├── gridFitting
└── enrichment: ActionEnrichmentInterface  ◄── Tipizált, machine-specifikus
    ├── cost (közös)
    ├── cutSheetCount (közös)
    └── [machine-specifikus mezők]
```

---

## Fázis 0: Regressziós Tesztek (KRITIKUS) ✅ KÉSZ

**Cél**: A jelenlegi működés rögzítése tesztekkel, mielőtt bármit változtatunk.

### Elkészült tesztek

- `tests/Integration/ActionPathEnrichmentTest.php` - 17 integrációs teszt
- `tests/Unit/Domain/Equipment/MachinePrepareTodoTest.php` - 10 unit teszt
- `tests/Integration/ActionPathValueSnapshotTest.php` - 5 value snapshot teszt
- `tests/snapshots/action-path-values/*.json` - Snapshot fájlok

**Összesen**: 132 teszt, 1048 assertion ✅

### 0.1 End-to-end tesztek az action path generálásra

```php
// tests/Integration/ActionPath/ActionPathGenerationTest.php

class ActionPathGenerationTest extends TestCase
{
    /**
     * Rögzíti a jelenlegi működést különböző DSL inputokra.
     * Snapshot-szerű teszt: az output struktúrát és értékeket ellenőrzi.
     */

    public function test_simple_cmyk_print_generates_expected_paths(): void
    {
        // Input: egyszerű CMYK nyomtatás
        $input = [
            'parts' => [[
                'partId' => 'TEST001',
                'properties' => ['copies' => 1000],
                'actions' => [[
                    'name' => 'print',
                    'params' => [
                        'dimensions' => [
                            'open' => ['width' => 300, 'height' => 200],
                            'closed' => ['width' => 300, 'height' => 200],
                        ],
                        'zone' => ['width' => 300, 'height' => 200, 'gripMargin' => 10],
                        'inking' => [
                            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                            'verso' => [],
                        ],
                    ],
                ]],
                'required_parts' => [],
            ]],
        ];

        $response = $this->processRequest($input);

        // Több action path kell hogy legyen
        $this->assertGreaterThan(1, count($response['parts']['TEST001']['actionPaths']));

        // Minden path-nak kell legyen cost és duration
        foreach ($response['parts']['TEST001']['actionPaths'] as $path) {
            $this->assertArrayHasKey('cost', $path);
            $this->assertArrayHasKey('duration', $path);
            $this->assertGreaterThan(0, $path['cost']);

            // Minden node-nak kell legyen machine, cost, todo
            foreach ($path['nodes'] as $node) {
                $this->assertArrayHasKey('machine', $node);
                $this->assertArrayHasKey('cost', $node);
                $this->assertArrayHasKey('todo', $node);
            }
        }
    }

    public function test_print_generates_ctp_node(): void
    {
        // Ellenőrzi, hogy nyomtatáshoz CTP node is generálódik
        $input = $this->createSimplePrintInput();
        $response = $this->processRequest($input);

        $firstPath = $response['parts']['TEST001']['actionPaths'][0];
        $machines = array_column($firstPath['nodes'], 'machine');

        $this->assertContains('ctp-machine', $machines);
    }

    public function test_path_costs_are_different(): void
    {
        // Különböző path-oknak különböző cost-ja kell legyen
        $input = $this->createSimplePrintInput();
        $response = $this->processRequest($input);

        $costs = array_column($response['parts']['TEST001']['actionPaths'], 'cost');
        $uniqueCosts = array_unique($costs);

        $this->assertCount(count($costs), $uniqueCosts, 'Minden path-nak egyedi cost-ja kell legyen');
    }

    public function test_paths_are_sorted_by_cost_ascending(): void
    {
        $input = $this->createSimplePrintInput();
        $response = $this->processRequest($input);

        $costs = array_column($response['parts']['TEST001']['actionPaths'], 'cost');
        $sortedCosts = $costs;
        sort($sortedCosts);

        $this->assertEquals($sortedCosts, $costs, 'Path-ok cost szerint növekvő sorrendben');
    }
}
```

### 0.2 Unit tesztek a todo/enrichment értékekre

```php
// tests/Unit/Domain/Equipment/OffsetPrintingPressEnrichmentTest.php

class OffsetPrintingPressEnrichmentTest extends TestCase
{
    /**
     * Rögzíti a jelenlegi cost számítás eredményeit.
     */

    public function test_prepareTodo_calculates_correct_cost(): void
    {
        $press = $this->createOffsetPress();
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $todo = $press->prepareTodo($context);

        // Rögzített értékek a jelenlegi implementációból
        $this->assertArrayHasKey('cost', $todo);
        $this->assertArrayHasKey('cost', $todo['cost']);
        $this->assertArrayHasKey('paperCost', $todo['cost']);

        // A pontos értékeket a jelenlegi implementációból kell kinyerni
        $this->assertEqualsWithDelta(51.87, $todo['cost']['cost'], 0.1);
    }

    public function test_prepareTodo_calculates_correct_cutSheetCount(): void
    {
        $press = $this->createOffsetPress();
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2), // 10 pose/sheet
        );

        $todo = $press->prepareTodo($context);

        // 1000 copies / 10 poses = 100 sheets
        $this->assertEquals(100, $todo['cutSheetCount']);
    }
}

// tests/Unit/Domain/Equipment/CTPMachineEnrichmentTest.php

class CTPMachineEnrichmentTest extends TestCase
{
    public function test_prepareTodo_calculates_aluSheetsCost(): void
    {
        $ctp = $this->createCTPMachine();
        $context = $this->createTodoContext(
            numberOfColors: 4,
            pressSheet: $this->createPressSheet(1020, 720),
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );

        $todo = $ctp->prepareTodo($context);

        $this->assertArrayHasKey('cost', $todo);
        $this->assertArrayHasKey('aluSheetsCost', $todo['cost']);

        // sqm = 1020 * 720 / 1_000_000 = 0.7344
        // aluSheetsCost = 11.42 * 0.7344 * 4 = 33.55
        $this->assertEqualsWithDelta(33.55, $todo['cost']['aluSheetsCost'], 0.1);
    }
}
```

### 0.3 Snapshot tesztek a teljes response-ra

```php
// tests/Integration/Snapshot/ResponseSnapshotTest.php

class ResponseSnapshotTest extends TestCase
{
    /**
     * Snapshot teszt: a teljes response struktúrát menti és összehasonlítja.
     * Ha változik a struktúra, a tesztet manuálisan kell frissíteni.
     */

    public function test_simple_print_response_matches_snapshot(): void
    {
        $input = $this->loadFixture('simple-print-input.json');
        $response = $this->processRequest($input);

        // Nem determinisztikus mezők eltávolítása (id, stb.)
        $normalized = $this->normalizeResponse($response);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    private function normalizeResponse(array $response): array
    {
        // UUID-k és egyéb nem determinisztikus értékek helyettesítése
        array_walk_recursive($response, function (&$value, $key) {
            if ($key === 'id' && is_string($value) && strlen($value) === 36) {
                $value = '__UUID__';
            }
        });
        return $response;
    }
}
```

### 0.4 ActionPathRanker tesztek bővítése

```php
// tests/Unit/Application/Process/Service/ActionPathRankerTest.php

// Már létezik, de bővíteni kell:

public function test_calculateCost_handles_nested_cost_array(): void
{
    $node = $this->createMockNode([
        'cost' => [
            'cost' => 100,
            'paperCost' => 20,
        ],
    ]);

    $cost = $this->ranker->calculateCost([$node]);

    $this->assertEquals(100, $cost); // Csak a 'cost' kulcsot használja
}

public function test_calculateCost_sums_multiple_nodes(): void
{
    $node1 = $this->createMockNode(['cost' => ['cost' => 50]]);
    $node2 = $this->createMockNode(['cost' => ['cost' => 30]]);

    $cost = $this->ranker->calculateCost([$node1, $node2]);

    $this->assertEquals(80, $cost);
}
```

---

## Fázis 1: JobContext Bevezetése ✅ KÉSZ

**Cél**: Job-szintű paraméterek kiemelése külön osztályba.

### Elkészült változtatások

1. **JobContext osztály létrehozva**: `src/Domain/Job/JobContext.php`
   - `numberOfCopies`, `numberOfColors`, `paperWeight`, `inking`, `openPoseDimensions`, `closedPoseDimensions`
   - Helper metódusok: `getTotalInkCount()`, `hasVerso()`, `getRectoInkCount()`, `getVersoInkCount()`

2. **ExtensionParams módosítva**: Extends `JobContext` (marked `@deprecated`)

3. **ActionPathContext módosítva**: `$params` → `$jobContext` (backward compatible `__get`)

4. **Pipeline processzorok frissítve** (`$context->jobContext`):
   - `TodoPreparationProcessor`
   - `CtpInsertionProcessor`
   - `VersoPrintingProcessor`
   - `CuttingInsertionProcessor`
   - `CutSheetCountProcessor`

5. **ActionTreeProcessor** frissítve: `JobContext` használata `ExtensionParams` helyett

**Tesztek**: 132 teszt ✅ (mind zöld)

### 1.1 JobContext osztály létrehozása

```php
// src/Domain/Job/JobContext.php

namespace App\Domain\Job;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Job-szintű kontextus, amely a teljes megrendelésre vonatkozó paramétereket tartalmazza.
 * Egyszer jön létre, nem duplikálódik action-önként.
 */
readonly class JobContext
{
    public function __construct(
        public int $numberOfCopies,
        public int $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
    ) {}
}
```

### 1.2 ActionPathParams átnevezése/módosítása

A jelenlegi `ActionPathParams` már tartalmazza ezeket az adatokat. Refaktorálás:

```php
// A meglévő ActionPathParams-ból JobContext lesz, vagy JobContext-et használ
```

### 1.3 Pipeline módosítása JobContext használatára

```php
// ActionPathContext módosítása
class ActionPathContext
{
    public function __construct(
        public array $nodes,
        public float $cutSheetCount,
        public JobContext $jobContext,  // params helyett
        public array $originalPath,
    ) {}
}
```

---

## Fázis 2: ActionEnrichmentInterface Bevezetése

**Cél**: Közös interface a machine-specifikus enrichment-ekhez.

### 2.1 Interface definiálása

```php
// src/Domain/Equipment/Enrichment/ActionEnrichmentInterface.php

namespace App\Domain\Equipment\Enrichment;

/**
 * Interface minden action enrichment számára.
 * Közös mezők, amelyek az ActionPathRanker-nek és az output generálásnak kellenek.
 */
interface ActionEnrichmentInterface
{
    /**
     * Teljes költség ehhez az action-höz.
     */
    public function getCost(): float;

    /**
     * Hány nyomtatóív/vágóív szükséges.
     */
    public function getCutSheetCount(): float;

    /**
     * Költség breakdown (machine-specifikus részletezés).
     * @return array<string, float>
     */
    public function getCostBreakdown(): array;

    /**
     * Array reprezentáció (JSON output-hoz).
     */
    public function toArray(): array;
}
```

### 2.2 Machine-specifikus enrichment osztályok

```php
// src/Domain/Equipment/Enrichment/OffsetPressEnrichment.php

namespace App\Domain\Equipment\Enrichment;

readonly class OffsetPressEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $paperCost,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getPaperCost(): float
    {
        return $this->paperCost;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
            'paperCost' => $this->paperCost,
        ];
    }

    public function toArray(): array
    {
        return [
            'cost' => $this->cost,
            'cutSheetCount' => $this->cutSheetCount,
            'paperCost' => $this->paperCost,
        ];
    }
}
```

```php
// src/Domain/Equipment/Enrichment/CTPEnrichment.php

namespace App\Domain\Equipment\Enrichment;

readonly class CTPEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $aluSheetsCost,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getAluSheetsCost(): float
    {
        return $this->aluSheetsCost;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
            'aluSheetsCost' => $this->aluSheetsCost,
        ];
    }

    public function toArray(): array
    {
        return [
            'cost' => $this->cost,
            'cutSheetCount' => $this->cutSheetCount,
            'aluSheetsCost' => $this->aluSheetsCost,
        ];
    }
}
```

```php
// src/Domain/Equipment/Enrichment/CuttingEnrichment.php

namespace App\Domain\Equipment\Enrichment;

readonly class CuttingEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private int $numberOfCuts,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getNumberOfCuts(): int
    {
        return $this->numberOfCuts;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
        ];
    }

    public function toArray(): array
    {
        return [
            'cost' => $this->cost,
            'cutSheetCount' => $this->cutSheetCount,
            'numberOfCuts' => $this->numberOfCuts,
        ];
    }
}
```

```php
// src/Domain/Equipment/Enrichment/FolderEnrichment.php

namespace App\Domain\Equipment\Enrichment;

readonly class FolderEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private int $numberOfFolds,
        private float $inputSheetLength,
    ) {}

    // ... implementáció
}
```

---

## Fázis 3: Machine Interface Bővítése

**Cél**: A machine-ek enrichment-et adjanak vissza todo helyett.

### 3.1 MachineInterface bővítése

```php
// src/Domain/Equipment/Interfaces/MachineInterface.php

interface MachineInterface
{
    // ... meglévő metódusok ...

    /**
     * Kalkulálja és visszaadja az action enrichment-et.
     * Felváltja a prepareTodo() metódust.
     */
    public function calculateEnrichment(
        JobContext $jobContext,
        GridFittingInterface $gridFitting,
        PressSheetInterface $pressSheet,
        float $cutSheetCount,
    ): ActionEnrichmentInterface;
}
```

### 3.2 Machine implementációk módosítása

```php
// src/Domain/Equipment/OffsetPrintingPress.php

class OffsetPrintingPress extends PrintingPress
{
    public function calculateEnrichment(
        JobContext $jobContext,
        GridFittingInterface $gridFitting,
        PressSheetInterface $pressSheet,
        float $cutSheetCount,
    ): OffsetPressEnrichment {
        $productsPerSheet = count($gridFitting->getTiles());
        $paperCostPerProduct = $pressSheet->getPrice() / $productsPerSheet;
        $paperCost = round($jobContext->numberOfCopies * $paperCostPerProduct, 2);

        $numberOfPrintingSheets = ceil($jobContext->numberOfCopies / $productsPerSheet);

        $setupDuration = $this->getBaseSetupDuration()
            + ($jobContext->numberOfColors * $this->getSetupDurationPerColor());

        $numberOfStackReplenishments = (($numberOfPrintingSheets * ($jobContext->paperWeight / 115) / 100))
            / $this->getMaxInputStackHeight();

        $runDuration = ($numberOfStackReplenishments * $this->getStackReplenishmentDuration())
            + (($numberOfPrintingSheets / $this->getSheetsPerHour()) * 60);

        $duration = $setupDuration + $runDuration;
        $cost = round(($duration / 60) * $this->getCostPerHour(), 2);

        return new OffsetPressEnrichment(
            cost: $cost,
            cutSheetCount: $numberOfPrintingSheets,
            paperCost: $paperCost,
        );
    }

    /**
     * @deprecated Use calculateEnrichment() instead
     */
    public function prepareTodo(TodoContext $context): array
    {
        // Backward compatibility - delegates to new method
        // ...
    }
}
```

---

## Fázis 4: ActionPathNode Módosítása

**Cél**: Todo helyett enrichment tárolása.

### 4.1 ActionPathNode módosítása

```php
// src/Domain/Action/ActionPathNode.php

class ActionPathNode implements ActionPathNodeInterface
{
    public function __construct(
        protected MachineInterface $machine,
        protected PressSheetInterface $pressSheet,
        protected InputSheetInterface $zone,
        protected GridFittingInterface $gridFitting,
        protected ActionEnrichmentInterface $enrichment,  // todo helyett
    ) {}

    public function getEnrichment(): ActionEnrichmentInterface
    {
        return $this->enrichment;
    }

    /**
     * @deprecated Use getEnrichment() instead
     */
    public function getTodo(): array
    {
        // Backward compatibility
        return $this->enrichment->toArray();
    }

    public function calculateCost(): float
    {
        return $this->enrichment->getCost();
    }

    public function toArray($machine, $pressSheet, $pose): array
    {
        return [
            "machine" => $this->getMachine()->getId(),
            "zone" => [...],
            "pressSheet" => [...],
            "gridFitting" => [...],
            "trimLines" => [...],
            "setupDuration" => $this->calculateSetupDuration(),
            "runDuration" => $this->calculateRunDuration(),
            "cost" => $this->enrichment->getCost(),
            "enrichment" => $this->enrichment->toArray(),  // todo helyett
            // Backward compatibility:
            "todo" => $this->enrichment->toArray(),
        ];
    }
}
```

---

## Fázis 5: Pipeline Processzorok Módosítása

**Cél**: A processzorok az új struktúrát használják.

### 5.1 TodoPreparationProcessor → EnrichmentProcessor

```php
// src/Domain/Action/Pipeline/Processor/EnrichmentProcessor.php

class EnrichmentProcessor implements ActionPathProcessorInterface
{
    public function process(ActionPathContext $context): ActionPathContext
    {
        $nodes = [];
        $cutSheetCount = $context->cutSheetCount;

        foreach ($context->originalPath as $originalNode) {
            $node = clone $originalNode;

            // Enrichment kalkulálása
            $enrichment = $node->getMachine()->calculateEnrichment(
                $context->jobContext,
                $node->getGridFitting(),
                $node->getPressSheet(),
                $cutSheetCount,
            );

            $actionPathNode = new ActionPathNode(
                $node->getMachine(),
                $node->getPressSheet(),
                $node->getZone(),
                $node->getGridFitting(),
                $enrichment,  // todo helyett
            );

            $nodes[] = $actionPathNode;
        }

        return new ActionPathContext(
            $nodes,
            $cutSheetCount,
            $context->jobContext,
            $context->originalPath,
        );
    }

    public function getPriority(): int
    {
        return 10;
    }
}
```

### 5.2 ActionPathRanker módosítása

```php
// src/Application/Process/Service/ActionPathRanker.php

public function calculateCost(array $actionPath): float
{
    $cost = 0.0;

    foreach ($actionPath as $node) {
        // Új módszer:
        if ($node instanceof ActionPathNodeInterface) {
            $cost += $node->getEnrichment()->getCost();
        }
    }

    return $cost;
}
```

---

## Fázis 6: Cleanup és Deprecation Eltávolítása

**Cél**: Régi kód eltávolítása, ha minden teszt zöld.

### 6.1 Törlendő fájlok/osztályok

- `TodoContext.php` (ha már nem használt)
- `prepareTodo()` metódusok (ha deprecation idő lejárt)

### 6.2 Törlendő backward compatibility kód

- `getTodo()` metódus az ActionPathNode-ból
- `"todo"` kulcs a toArray() output-ból

---

## Implementációs Sorrend (Összefoglaló)

| Fázis | Feladat | Kockázat | Tesztek | Státusz |
|-------|---------|----------|---------|---------|
| **0** | Regressziós tesztek létrehozása | Nincs | Új tesztek | ✅ KÉSZ |
| **1** | JobContext bevezetése | Alacsony | Unit tesztek | ✅ KÉSZ |
| **2** | ActionEnrichmentInterface + implementációk | Alacsony | Unit tesztek | ⏳ |
| **3** | Machine.calculateEnrichment() | Közepes | Unit + Integration | ⏳ |
| **4** | ActionPathNode módosítása | Közepes | Integration | ⏳ |
| **5** | Pipeline processzorok módosítása | Magas | Full regression | ⏳ |
| **6** | Cleanup | Alacsony | Full regression | ⏳ |

---

## Rollback Terv

Ha bármelyik fázisban probléma van:

1. A backward compatibility (`getTodo()`, `prepareTodo()`) megmarad, amíg minden teszt zöld
2. Feature flag-gel lehet az új/régi implementáció között váltani
3. Git branch-enként dolgozunk, main-re csak zöld tesztek után merge

---

## Megjegyzések

- A refactoring NEM érinti az ActionTree core logikáját (fa bejárás, kombinációk generálása)
- Minden változtatás a pipeline-ban és utána történik
- A tesztek a jelenlegi viselkedést rögzítik - ha változik az output, azt explicit jóvá kell hagyni

## Ismert Problémák

### ActionPathRanker sorting bug

A Phase 1 során felfedeztük, hogy az `ActionPathRanker::selectBest()` nem mindig rendezi helyesen a path-okat cost szerint. A `test_action_paths_have_valid_costs` teszt dokumentálja ezt a problémát.

**Tünet**: A visszaadott action path-ok nem mindig növekvő cost sorrendben vannak.

**Lokáció**: `src/Application/Process/Service/ActionPathRanker.php`

**@todo**: Külön task-ként javítandó.
