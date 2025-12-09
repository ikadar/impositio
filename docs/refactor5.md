# Refactor 5: ProcessUseCase Szétbontása

## 1. Probléma Leírása

### 1.1 Jelenlegi Helyzet

A `ProcessUseCase` osztály túl sok felelősséget tartalmaz:

```
ProcessUseCase (282 sor)
├── Entity létrehozás (ProcessRequest, ProcessPart)
├── ActionTree paraméter előkészítés
├── ActionTree feldolgozás
├── ActionPath kiválasztás/szűrés (top 10 by cost)
├── Költség számítás
├── Response összeállítás (designation, formázás)
├── Perzisztálás (EntityManager)
└── Error handling
```

### 1.2 Single Responsibility Principle Megsértése

| Metódus | Felelősség | Probléma |
|---------|------------|----------|
| `execute()` | Orchestráció + Perzisztálás | Túl sok lépés egy helyen |
| `processActionTree()` | ActionTree hívás + Entity létrehozás | Kevert domain/infra |
| `createActionPathEntities()` | Entity létrehozás + Szűrés | Kevert felelősség |
| `selectBestActionPaths()` | Üzleti logika (Top 10) | Rejtett üzleti szabály |
| `calculatePathCost()` | Költség aggregálás | Domain logika UseCase-ben |
| `buildPathData()` | Response formázás | Presentation logika |
| `extractPaperWeight()` | Paraméter kinyerés | Duplikáció az ActionParamsExtractor-ral |

### 1.3 Függőségi Problémák

```php
// Túl sok függőség = túl sok felelősség
public function __construct(
    private EntityManagerInterface $em,           // Infra
    private ActionTreeInterface $actionTree,       // Domain
    private ActionParamsExtractorInterface $paramsExtractor,  // Application
    private PressSheetProviderInterface $pressSheetProvider,  // Application
) {}
```

---

## 2. Célok

1. **Single Responsibility**: Minden osztály egy felelősséggel
2. **Tesztelhetőség**: Unit tesztek minden komponensre
3. **Tiszta Rétegek**: Domain ↔ Application ↔ Infrastructure elkülönítés
4. **Újrafelhasználhatóság**: Komponensek más kontextusban is használhatók

---

## 3. Javasolt Architektúra

### 3.1 Új Osztály Struktúra

```
Application/Process/
├── UseCase/
│   └── ProcessUseCase.php              # Egyszerűsített orchestrátor
├── Service/
│   ├── ProductionPlanningService.php   # ActionTree wrapper + path selection
│   ├── ActionPathRanker.php            # Best path selection logic
│   └── ActionPathTransformer.php       # Path → Response transformation
├── Repository/
│   └── ProcessRequestRepository.php    # Perzisztencia (interface)
└── DTO/
    ├── ProcessRequestModel.php         # (meglévő)
    ├── ProcessResponseModel.php        # (meglévő)
    ├── ActionTreeInput.php             # (meglévő)
    └── ActionPathResult.php            # Új: domain result DTO
```

### 3.2 Felelősség Elosztás

| Osztály | Felelősség |
|---------|------------|
| **ProcessUseCase** | Orchestráció (csak komponensek összekötése) |
| **ProductionPlanningService** | ActionTree hívás, input validálás |
| **ActionPathRanker** | Path szűrés, rendezés (top N by cost) |
| **ActionPathTransformer** | ActionPathNode[] → Response array |
| **ProcessRequestRepository** | Entity létrehozás és perzisztálás |

### 3.3 Osztály Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ProcessController                            │
│  - JSON parsing                                                     │
│  - Validation (ActionValidator)                                     │
│  - HTTP response                                                    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          ProcessUseCase                              │
│  - Orchestration only                                               │
│  - Calls: ProductionPlanningService                                 │
│  - Calls: ProcessPersistenceService                                 │
│  - Calls: ActionPathTransformer                                     │
└─────────────────────────────────────────────────────────────────────┘
          │                    │                    │
          ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐
│ ProductionPlan  │  │ ProcessPersist  │  │ ActionPathTransformer  │
│ ningService     │  │ enceService     │  │                         │
│                 │  │                 │  │ - toResponseArray()     │
│ - plan()        │  │ - persist()     │  │ - buildDesignation()    │
│ - ActionTree    │  │ - EntityManager │  │ - formatDimensions()    │
│ - PathRanker    │  │ - Entity CRUD   │  │                         │
└─────────────────┘  └─────────────────┘  └─────────────────────────┘
          │
          ▼
┌─────────────────┐
│ ActionPathRanker│
│                 │
│ - selectBest()  │
│ - calculateCost │
│ - sortByCost()  │
└─────────────────┘
```

---

## 4. Részletes Terv

### Phase 1: DTO-k és Interface-ek Létrehozása

**Cél**: Tiszta szerződések definiálása

#### 1.1 ActionPathResult DTO
```php
namespace App\Application\Process\DTO;

readonly class ActionPathResult
{
    public function __construct(
        public array $nodes,           // ActionPathNodeInterface[]
        public float $totalCost,
        public float $totalDuration,
        public string $pressSheetSize,
    ) {}
}
```

#### 1.2 ProductionPlanningServiceInterface
```php
namespace App\Application\Process\Service;

interface ProductionPlanningServiceInterface
{
    /**
     * Execute production planning for a part.
     *
     * @return ActionPathResult[] Ranked action paths
     */
    public function plan(PartPayload $part, array $pressSheets): array;
}
```

#### 1.3 ActionPathRankerInterface
```php
namespace App\Application\Process\Service;

interface ActionPathRankerInterface
{
    /**
     * Select and rank best action paths.
     *
     * @param array $actionPaths Raw paths from ActionTree
     * @param int $limit Maximum paths to return
     * @return array Sorted paths (best first)
     */
    public function selectBest(array $actionPaths, int $limit = 10): array;

    /**
     * Calculate total cost for a path.
     */
    public function calculateCost(array $actionPath): float;
}
```

#### 1.4 ActionPathTransformerInterface
```php
namespace App\Application\Process\Service;

interface ActionPathTransformerInterface
{
    /**
     * Transform ActionPathResult to response array.
     */
    public function toResponseArray(
        ActionPathResult $result,
        PartPayload $part,
        ActionTreeInput $input
    ): array;
}
```

#### 1.5 ProcessPersistenceServiceInterface
```php
namespace App\Application\Process\Service;

interface ProcessPersistenceServiceInterface
{
    /**
     * Persist process request with all parts and action paths.
     *
     * @return int Process request ID
     */
    public function persist(
        ProcessRequestModel $request,
        array $partsWithPaths // [partId => ActionPathResult[]]
    ): int;
}
```

### Phase 2: ActionPathRanker Implementálás

**Cél**: Üzleti logika kiemelése (Top N selection)

```php
namespace App\Application\Process\Service;

class ActionPathRanker implements ActionPathRankerInterface
{
    public function selectBest(array $actionPaths, int $limit = 10): array
    {
        $pathsWithCost = [];
        foreach ($actionPaths as $actionPath) {
            $cost = $this->calculateCost($actionPath);
            $pathsWithCost[] = ['path' => $actionPath, 'cost' => $cost];
        }

        usort($pathsWithCost, fn($a, $b) => $a['cost'] <=> $b['cost']);

        // Unique costs only
        $uniqueCosts = [];
        $result = [];
        foreach ($pathsWithCost as $item) {
            if (!in_array($item['cost'], $uniqueCosts, true)) {
                $uniqueCosts[] = $item['cost'];
                $result[] = $item['path'];
            }
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function calculateCost(array $actionPath): float
    {
        $cost = 0;
        foreach ($actionPath as $node) {
            $nodeCost = $node->getTodo()['cost'] ?? 0;
            if (is_array($nodeCost)) {
                $nodeCost = $nodeCost['cost'] ?? 0;
            }
            $cost += $nodeCost;
        }
        return $cost;
    }
}
```

### Phase 3: ActionPathTransformer Implementálás

**Cél**: Response formázás kiemelése

```php
namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;
use Symfony\Component\Uid\Uuid;

class ActionPathTransformer implements ActionPathTransformerInterface
{
    public function toResponseArray(
        ActionPathResult $result,
        PartPayload $part,
        ActionTreeInput $input
    ): array {
        $nodes = [];
        $designation = [];
        $cost = 0;
        $duration = 0;

        $pose = [
            'width' => $input->closedPoseDimensions->getWidth(),
            'height' => $input->closedPoseDimensions->getHeight(),
        ];

        foreach ($result->nodes as $node) {
            $nodeArray = $node->toArray($node->getMachine(), $node->getPressSheet(), $pose);
            $nodeArray = $this->normalizeNodeCost($nodeArray);

            $cost += $nodeArray['cost'];
            $duration += ($nodeArray['setupDuration'] ?? 0) + ($nodeArray['runDuration'] ?? 0);
            $designation[] = $nodeArray['machine'] ?? '';
            $nodes[] = $nodeArray;
        }

        return [
            'id' => Uuid::v4()->toString(),
            'designation' => $this->buildDesignation($result->pressSheetSize, $designation, $cost, $duration),
            'nodes' => $nodes,
            'cost' => $cost,
            'duration' => $duration,
            'pressSheet' => sprintf("%smm", $result->pressSheetSize),
            'openPoseDimensions' => $this->formatDimensions($input->openPoseDimensions),
            'closedPoseDimensions' => $this->formatDimensions($input->closedPoseDimensions),
            'requiredParts' => $part->requiredParts,
        ];
    }

    private function normalizeNodeCost(array $nodeArray): array
    {
        if (is_array($nodeArray['cost'])) {
            $additionalCosts = 0;
            foreach ($nodeArray['cost'] as $costName => $additionalCost) {
                if ($costName !== 'cost') {
                    $additionalCosts += $additionalCost;
                }
            }
            $nodeArray['cost'] = ($nodeArray['cost']['cost'] ?? 0) + $additionalCosts;
        }
        return $nodeArray;
    }

    private function buildDesignation(string $pressSheet, array $machines, float $cost, float $duration): string
    {
        return sprintf(
            "(%s) %s Cost: %s€; Duration: %smin",
            $pressSheet,
            implode(" > ", $machines),
            $cost,
            $duration
        );
    }

    private function formatDimensions($dimensions): string
    {
        return sprintf("%dx%d", $dimensions->getWidth(), $dimensions->getHeight());
    }
}
```

### Phase 4: ProductionPlanningService Implementálás

**Cél**: ActionTree hívás és eredmény feldolgozás

```php
namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Service\ActionParamsExtractorInterface;
use App\Service\PressSheetProviderInterface;

class ProductionPlanningService implements ProductionPlanningServiceInterface
{
    public function __construct(
        private ActionTreeInterface $actionTree,
        private ActionParamsExtractorInterface $paramsExtractor,
        private PressSheetProviderInterface $pressSheetProvider,
        private ActionPathRankerInterface $ranker,
    ) {}

    public function plan(PartPayload $part, array $pressSheets): array
    {
        // Extract ActionTree input
        $input = $this->paramsExtractor->extractForActionTree($part, $pressSheets);

        if ($input === null) {
            return [];
        }

        try {
            // Execute ActionTree
            $rawPaths = $this->actionTree->process(
                $input->abstractActions,
                $input->pressSheets,
                $input->zone,
                $input->openPoseDimensions,
                $input->closedPoseDimensions,
                $input->numberOfCopies,
                $input->numberOfColors,
                $input->paperWeight,
                $input->inking,
            );

            // Rank and select best paths
            $bestPaths = $this->ranker->selectBest($rawPaths);

            // Convert to ActionPathResult DTOs
            return $this->toResults($bestPaths, $input);
        } catch (\Throwable $e) {
            // Log but don't fail
            error_log("Production planning failed: " . $e->getMessage());
            return [];
        }
    }

    public function getInput(PartPayload $part, array $pressSheets): ?ActionTreeInput
    {
        return $this->paramsExtractor->extractForActionTree($part, $pressSheets);
    }

    private function toResults(array $paths, ActionTreeInput $input): array
    {
        $results = [];
        foreach ($paths as $path) {
            $results[] = new ActionPathResult(
                nodes: $path,
                totalCost: $this->ranker->calculateCost($path),
                totalDuration: $this->calculateDuration($path),
                pressSheetSize: $this->extractPressSheetSize($path),
            );
        }
        return $results;
    }

    private function calculateDuration(array $path): float
    {
        $duration = 0;
        foreach ($path as $node) {
            $duration += $node->calculateSetupDuration() + $node->calculateRunDuration();
        }
        return $duration;
    }

    private function extractPressSheetSize(array $path): string
    {
        if (empty($path)) {
            return '';
        }
        $firstNode = $path[0];
        $pressSheet = $firstNode->getPressSheet();
        return sprintf("%dx%d", $pressSheet->getWidth(), $pressSheet->getHeight());
    }
}
```

### Phase 5: ProcessPersistenceService Implementálás

**Cél**: Perzisztencia logika elkülönítése

```php
namespace App\Application\Process\Service;

use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Entity\ProcessActionPath;
use App\Entity\ProcessPart;
use App\Entity\ProcessRequest;
use Doctrine\ORM\EntityManagerInterface;

class ProcessPersistenceService implements ProcessPersistenceServiceInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function persist(ProcessRequestModel $request, array $partsWithPaths): int
    {
        $processRequest = new ProcessRequest();
        $processRequest->setPayload($this->buildPayloadArray($request));

        foreach ($request->parts as $partPayload) {
            $processPart = $this->createProcessPart($partPayload);
            $processRequest->addPart($processPart);

            // Add action paths if available
            $pathResults = $partsWithPaths[$partPayload->partId] ?? [];
            foreach ($pathResults as $pathData) {
                $actionPathEntity = new ProcessActionPath();
                $actionPathEntity->setProcessPart($processPart);
                $actionPathEntity->setJson($pathData);
                $processPart->addActionPath($actionPathEntity);
            }
        }

        $this->em->persist($processRequest);
        $this->em->flush();

        return $processRequest->getId();
    }

    private function createProcessPart(PartPayload $partPayload): ProcessPart
    {
        $processPart = new ProcessPart();
        $processPart->setPartId($partPayload->partId);
        $processPart->setActions(
            array_map(fn($action) => $action->toArray(), $partPayload->actions)
        );
        $processPart->setProperties($partPayload->properties);
        $processPart->setRequiredParts($partPayload->requiredParts);

        return $processPart;
    }

    private function buildPayloadArray(ProcessRequestModel $request): array
    {
        return [
            'parts' => array_map(fn($p) => $p->toArray(), $request->parts),
        ];
    }
}
```

### Phase 6: ProcessUseCase Egyszerűsítése

**Cél**: Tiszta orchestráció

```php
namespace App\Application\Process\UseCase;

use App\Application\Process\ProcessRequestModel;
use App\Application\Process\ProcessResponseModel;
use App\Application\Process\Service\ActionPathTransformerInterface;
use App\Application\Process\Service\ProcessPersistenceServiceInterface;
use App\Application\Process\Service\ProductionPlanningServiceInterface;
use App\Service\PressSheetProviderInterface;

class ProcessUseCase
{
    public function __construct(
        private ProductionPlanningServiceInterface $planningService,
        private ProcessPersistenceServiceInterface $persistenceService,
        private ActionPathTransformerInterface $transformer,
        private PressSheetProviderInterface $pressSheetProvider,
    ) {}

    public function execute(ProcessRequestModel $request): ProcessResponseModel
    {
        $partsResponse = [];
        $partsWithPaths = [];

        foreach ($request->parts as $partPayload) {
            // Get press sheets
            $paperWeight = $this->extractPaperWeight($partPayload);
            $pressSheets = $this->pressSheetProvider->getPressSheets($paperWeight);

            // Plan production
            $pathResults = $this->planningService->plan($partPayload, $pressSheets);
            $input = $this->planningService->getInput($partPayload, $pressSheets);

            // Transform to response format
            $actionPathsData = [];
            foreach ($pathResults as $result) {
                $actionPathsData[] = $this->transformer->toResponseArray($result, $partPayload, $input);
            }

            $partsResponse[$partPayload->partId] = ['actionPaths' => $actionPathsData];
            $partsWithPaths[$partPayload->partId] = $actionPathsData;
        }

        // Persist
        $requestId = $this->persistenceService->persist($request, $partsWithPaths);

        return new ProcessResponseModel(
            $requestId,
            $this->buildMetaData($requestId),
            $partsResponse
        );
    }

    private function extractPaperWeight($partPayload): float
    {
        foreach ($partPayload->actions as $action) {
            if ($action->name->value === 'print') {
                return (float) ($action->params['paper']['weight'] ?? 120);
            }
        }
        return 120;
    }

    private function buildMetaData(int $jobId): array
    {
        return [
            'jobNumber' => 'PROCESS-001',
            'quantity' => 0,
            'jobId' => $jobId,
        ];
    }
}
```

---

## 5. Implementációs Fázisok

### Phase 1: Előkészítés (Tesztek) ✅ KÉSZ
- [x] Unit tesztek írása a jelenlegi ProcessUseCase-re
- [x] Integration tesztek bővítése
- [x] Base test class létrehozása (`ProcessTestBase.php`)

### Phase 2: DTO és Interface Létrehozás ✅ KÉSZ
- [x] `ActionPathResult` DTO létrehozása → `src/Application/Process/DTO/ActionPathResult.php`
- [x] `ProductionPlanningServiceInterface` létrehozása → `src/Application/Process/Service/ProductionPlanningServiceInterface.php`
- [x] `ActionPathRankerInterface` létrehozása → `src/Application/Process/Service/ActionPathRankerInterface.php`
- [x] `ActionPathTransformerInterface` létrehozása → `src/Application/Process/Service/ActionPathTransformerInterface.php`
- [x] `ProcessPersistenceServiceInterface` létrehozása → `src/Application/Process/Service/ProcessPersistenceServiceInterface.php`

### Phase 3: ActionPathRanker Implementálás ✅ KÉSZ
- [x] `ActionPathRanker` osztály → `src/Application/Process/Service/ActionPathRanker.php`
- [x] `selectBest()` metódus
- [x] `calculateCost()` metódus
- [x] Unit tesztek (10 teszt) → `tests/Unit/Application/Process/Service/ActionPathRankerTest.php`

### Phase 4: ActionPathTransformer Implementálás ✅ KÉSZ
- [x] `ActionPathTransformer` osztály → `src/Application/Process/Service/ActionPathTransformer.php`
- [x] `toResponseArray()` metódus
- [x] Helper metódusok (`normalizeNodeCost`, `buildDesignation`, `formatDimensions`)
- [x] Unit tesztek (10 teszt) → `tests/Unit/Application/Process/Service/ActionPathTransformerTest.php`

### Phase 5: ProductionPlanningService Implementálás ✅ KÉSZ
- [x] `ProductionPlanningService` osztály → `src/Application/Process/Service/ProductionPlanningService.php`
- [x] `plan()` metódus
- [x] `getInput()` metódus
- [x] Unit tesztek (9 teszt) → `tests/Unit/Application/Process/Service/ProductionPlanningServiceTest.php`

### Phase 6: ProcessPersistenceService Implementálás ✅ KÉSZ
- [x] `ProcessPersistenceService` osztály → `src/Application/Process/Service/ProcessPersistenceService.php`
- [x] `persist()` metódus (returns `ProcessRequest` entity)
- [x] Entity létrehozás
- [x] Unit tesztek (9 teszt) → `tests/Unit/Application/Process/Service/ProcessPersistenceServiceTest.php`

### Phase 7: ProcessUseCase Refaktorálás ✅ KÉSZ
- [x] Régi kód eltávolítása (282 → 88 sor)
- [x] Új service-ek injektálása (`ProductionPlanningService`, `ProcessPersistenceService`, `ActionPathTransformer`)
- [x] Symfony service config frissítése (`services.yaml`)
- [x] ProcessUseCaseTest tesztek aktiválása (skip eltávolítása)
- [x] Összes teszt futtatása (98 teszt, mind zöld)

---

## 6. Kockázatok és Mitigáció

| Kockázat | Valószínűség | Hatás | Mitigáció |
|----------|--------------|-------|-----------|
| Response struktúra változás | Közepes | Magas | Snapshot tesztek, kompatibilitási ellenőrzés |
| Perzisztencia hiba | Alacsony | Magas | Tranzakciók, rollback tesztek |
| Teljesítmény csökkenés | Alacsony | Közepes | Benchmark tesztek |
| Service config hibák | Közepes | Közepes | Integration tesztek |

---

## 7. Elfogadási Kritériumok

1. **Minden meglévő teszt zölden fut** (ProcessControllerTest, ActionPathOrderTest)
2. **Új unit tesztek** minden új osztályra (80%+ coverage)
3. **Response struktúra változatlan** (backward compatibility)
4. **Nincs teljesítmény regresszió** (< 10% lassulás)
5. **Clean Code**: Minden osztály < 100 sor, max 3 dependency

---

## 8. Összefoglaló

| Jelenlegi | Új Struktúra |
|-----------|--------------|
| 1 nagy UseCase (282 sor) | 5 kis osztály (~50-80 sor/db) |
| 4 dependency | 4 dependency (jobban elkülönített) |
| Kevert felelősségek | SRP-kompatibilis |
| Nehéz tesztelés | Unit tesztelhető komponensek |

---

## 9. Implementált Fájlok

### DTOs
| Fájl | Sorok | Státusz |
|------|-------|---------|
| `src/Application/Process/DTO/ActionPathResult.php` | ~15 | ✅ Kész |

### Interfaces
| Fájl | Státusz |
|------|---------|
| `src/Application/Process/Service/ActionPathRankerInterface.php` | ✅ Kész |
| `src/Application/Process/Service/ActionPathTransformerInterface.php` | ✅ Kész |
| `src/Application/Process/Service/ProductionPlanningServiceInterface.php` | ✅ Kész |
| `src/Application/Process/Service/ProcessPersistenceServiceInterface.php` | ✅ Kész |

### Services
| Fájl | Sorok | Státusz |
|------|-------|---------|
| `src/Application/Process/Service/ActionPathRanker.php` | ~50 | ✅ Kész |
| `src/Application/Process/Service/ActionPathTransformer.php` | ~105 | ✅ Kész |
| `src/Application/Process/Service/ProductionPlanningService.php` | ~130 | ✅ Kész |
| `src/Application/Process/Service/ProcessPersistenceService.php` | ~80 | ✅ Kész |

### Tesztek
| Fájl | Tesztek | Státusz |
|------|---------|---------|
| `tests/Unit/Application/Process/ProcessTestBase.php` | - | ✅ Kész |
| `tests/Unit/Application/Process/Service/ActionPathRankerTest.php` | 10 | ✅ Kész |
| `tests/Unit/Application/Process/Service/ActionPathTransformerTest.php` | 10 | ✅ Kész |
| `tests/Unit/Application/Process/Service/ProductionPlanningServiceTest.php` | 9 | ✅ Kész |
| `tests/Unit/Application/Process/Service/ProcessPersistenceServiceTest.php` | 9 | ✅ Kész |
| `tests/Unit/Application/Process/UseCase/ProcessUseCaseTest.php` | 6 | ✅ Kész |

### Teszt Eredmények (Phase 7 után)
```
PHPUnit 9.6.22
Tests: 98, Assertions: 290
OK (98 tests, 290 assertions)
```

---

## 10. Refaktorálás Összegzése

### Előtte vs Utána

| Metrika | Előtte | Utána | Változás |
|---------|--------|-------|----------|
| ProcessUseCase sorok | 282 | 88 | -69% |
| Felelősségek | 8 | 2 | -75% |
| Dependencies | 4 | 4 | 0 |
| Unit tesztek | 0 | 44 | +44 |
| Összes teszt | 54 | 98 | +44 |

### Új Osztályok

| Osztály | Sorok | Felelősség |
|---------|-------|------------|
| ActionPathRanker | ~50 | Path szűrés, rendezés (top N by cost) |
| ActionPathTransformer | ~105 | ActionPathNode[] → Response array |
| ProductionPlanningService | ~130 | ActionTree hívás, input validálás |
| ProcessPersistenceService | ~80 | Entity létrehozás és perzisztálás |

### SRP Megfelelés

| Osztály | Felelősség | Tiszta? |
|---------|------------|---------|
| ProcessUseCase | Orchestráció | ✅ |
| ActionPathRanker | Üzleti logika (ranking) | ✅ |
| ActionPathTransformer | Presentation (response) | ✅ |
| ProductionPlanningService | ActionTree wrapper | ✅ |
| ProcessPersistenceService | Perzisztencia | ✅ |
