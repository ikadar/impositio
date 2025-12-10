# Refactoring: Domain Hierarchia Tisztázása

## Státusz

| Fázis | Feladat | Státusz |
|-------|---------|---------|
| **0** | Regressziós tesztek írása | ✅ KÉSZ (14 teszt) |
| **1** | Inking kiszervezése action szintre | ✅ KÉSZ |
| **2** | JobContext → PartProductionContext átnevezés | ✅ KÉSZ |
| **3** | TreeBuildContext konszolidálása | ✅ KÉSZ |
| **4** | Dokumentáció és típusok frissítése | ✅ KÉSZ |

### Phase 1 Összefoglaló (COMPLETED)

**Létrehozott fájlok:**
- `src/Domain/Action/PrintActionParams.php` - Action-szintű inking tárolása

**Módosított fájlok:**
- `src/Domain/Action/ProcessAbstractAction.php` - printParams property hozzáadva
- `src/Domain/Action/ActionPathNode.php` - printParams property és getter/setter
- `src/Domain/Action/Interfaces/ActionPathNodeInterface.php` - getPrintParams() metódus
- `src/Service/ActionParamsExtractor.php` - PrintActionParams létrehozása minden print action-höz
- `src/Domain/Action/ActionTreeBuilder.php` - printParams átadása ActionTreeNode-nak
- `src/Domain/Action/Pipeline/Processor/VersoPrintingProcessor.php` - node.getPrintParams() használata
- `src/Domain/Action/Pipeline/Processor/CtpInsertionProcessor.php` - printParams öröklés print node-ból
- `src/Domain/Action/Pipeline/Processor/TodoPreparationProcessor.php` - printParams megőrzése

**Teszt eredmény:** 164 teszt, 1231 assertion - MIND ZÖLD

**Megjegyzés:** A JobContext továbbra is tartalmazza az inking és numberOfColors mezőket backward compatibility miatt. A gépek (OffsetPrintingPress, CTPMachine) továbbra is a JobContext-ből olvassák ezeket - ez a Phase 2/3 részét képezi majd.

### Phase 2+3 Összefoglaló (COMPLETED)

**Létrehozott fájlok:**
- `src/Domain/Part/PartProductionContext.php` - Új part-szintű context osztály

**Törölt fájlok:**
- `src/Domain/Action/TreeBuildContext.php` - Megszüntetve, PartProductionContext-be olvadt

**Módosított fájlok:**
- `src/Domain/Job/JobContext.php` - Most már csak alias (extends PartProductionContext)
- `src/Domain/Action/ActionTree.php` - TreeBuildContext → PartProductionContext
- `src/Domain/Action/ActionTreeProcessor.php` - TreeBuildContext → PartProductionContext
- `src/Domain/Action/ActionTreeBuilder.php` - TreeBuildContext → PartProductionContext
- `src/Domain/Action/Pipeline/ActionPathContext.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/Interfaces/MachineInterface.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/Machine.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/OffsetPrintingPress.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/CTPMachine.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/Folder.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/StitchingMachine.php` - JobContext → PartProductionContext
- `src/Domain/Equipment/CutoutMachine.php` - JobContext → PartProductionContext

**Teszt eredmény:** 164 teszt, 1231 assertion - MIND ZÖLD

**Fő változások:**
1. `PartProductionContext` a központi context osztály (part-level adatok)
2. `JobContext` deprecated alias lett (backward compatibility)
3. `TreeBuildContext` megszűnt - funkcionalitása beolvadt a `PartProductionContext`-be
4. Minden gép és processor `PartProductionContext`-et használ

## Összefoglaló

A jelenlegi kód két problémát tartalmaz:

1. **`JobContext` elnevezés félrevezető** - valójában part-szintű, nem job-szintű adatokat tartalmaz
2. **`inking` rossz helyen van** - a context-ben tároljuk, de valójában action-szintű paraméter

## Motiváció

### Probléma 1: JobContext vs Part

A "Job" szó a kódban félrevezető:

```
Megrendelés (Job): "Éves Jelentés 2024"
├── Part A: Borító      → saját copies, paperWeight, dimensions
├── Part B: Belív       → saját copies, paperWeight, dimensions
└── Part C: Melléklet   → saját copies, paperWeight, dimensions
```

A jelenlegi `JobContext` valójában **egy part gyártási kontextusa**.

### Probléma 2: Inking Szintje

**Jelenlegi (hibás) modell:**
```
JobContext (félrevezető név!)
├── numberOfCopies      ← Part-szintű ✓
├── paperWeight         ← Part-szintű ✓
├── dimensions          ← Part-szintű ✓
├── inking              ← ACTION-szintű! ✗ ROSSZ HELYEN!
└── numberOfColors      ← Számított az inking-ből ✗
```

**Probléma részletezése:**

Az `inking` a `print` action paramétere, NEM a part paramétere:
- Egy part-nak lehet több print action-je (bár jelenleg a kód nem támogatja tisztán)
- A `VersoPrintingProcessor` az inking alapján hoz létre második action-t
- Az `ActionParamsExtractor` az ELSŐ print action inking-jét veszi ki és teszi a context-be
- Ha 2 print action van különböző inking-gel, csak az első számít (bug!)

**Bizonyíték a kódból:**

```php
// ActionParamsExtractor.php - ELSŐ print action-t veszi
private function findPrintAction(array $actions): ?PayloadAction
{
    foreach ($actions as $action) {
        if ($action->name === ActionName::Print) {
            return $action;  // Returns FIRST match only!
        }
    }
    return null;
}
```

### Tiszta Modell

```
Part
├── copies: 1000
├── paperWeight: 300g
├── openDimensions: 210x297mm
├── closedDimensions: 210x297mm
│
├── print action 1
│   └── inking: { recto: [C,M,Y,K], verso: [K] }  ← ACTION szintű!
│
├── cut action
│   └── (nincs inking)
│
└── fold action
    └── (nincs inking)
```

**Az inking tehát:**
- A `print` action specifikus paramétere
- Nem minden action-nek van inking-je
- Minden print action-nek saját inking-je lehet

---

## Jelenlegi Struktúra (Hibás)

### Adatfolyam

```
JSON Request
    │
    ├── parts[0].actions[0] (print)
    │       └── params.inking ──────────────────┐
    │                                           │
    └── parts[0].properties.copies ─────────────┼──→ ActionParamsExtractor
                                                │           │
                                                │           ▼
                                                └──→ ActionTreeInput
                                                           │
                                                           ▼
                                                    TreeBuildContext
                                                           │
                                                           ▼
                                                      JobContext
                                                      (inking itt!)
```

### Probléma

1. Az `inking` a print action-ből jön
2. De a `JobContext`-be kerül (rossz szint!)
3. A `VersoPrintingProcessor` a context-ből olvassa
4. A gép szűrés is a context-ből olvassa
5. Ha több print action van, csak az első inking-je számít

---

## Célstruktúra (Tiszta Modell)

### Új Hierarchia

```
PartProductionContext (korábban JobContext)
├── numberOfCopies      ← Part-szintű
├── paperWeight         ← Part-szintű
├── openPoseDimensions  ← Part-szintű
└── closedPoseDimensions← Part-szintű

PrintActionParams (ÚJ!)
├── inking              ← Action-szintű
└── getInkCount()       ← Számított

ActionTreeNode / ActionPathNode
├── machine
├── enrichment
└── actionParams        ← ÚJ: PrintActionParams vagy null
```

### Új Adatfolyam

```
JSON Request
    │
    ├── parts[0].actions[0] (print)
    │       └── params.inking ──→ PrintActionParams ──→ ActionTreeNode.actionParams
    │
    └── parts[0].properties.copies ──→ PartProductionContext.numberOfCopies
```

---

## Fázis 0: Regressziós Tesztek

A refactoring előtt olyan teszteket kell írni, amelyek rögzítik a jelenlegi működést.
Ezek a tesztek a refactoring alatt NEM változhatnak - csak a belső implementáció változik.

### 0.1 Tesztelési Stratégia

A tesztek a **külső viselkedést** tesztelik, nem a belső struktúrát:
- Input: JSON request vagy magas szintű DTO-k
- Output: Végeredmény (action path-ok, költségek, gép választás)

A tesztek NEM függhetnek:
- `JobContext` létezésétől (át lesz nevezve)
- `inking` helyétől a context-ben (ki lesz szervezve)
- Belső osztályok neveitől

### 0.2 Teszt Fájl

```
tests/Integration/DomainHierarchyRegressionTest.php
```

### 0.3 Tesztesetek

#### 0.3.1 Inking Alapú Gép Szűrés

**Cél:** Biztosítani, hogy a gép szűrés az inking alapján helyesen működik.

```php
/**
 * @test
 * Gépek szűrése színszám alapján - csak a megfelelő kapacitású gépek maradnak.
 */
public function machine_filtering_respects_inking_color_count(): void
{
    // GIVEN: 4 színű inking (CMYK)
    $inking = [
        'recto' => ['cyan', 'magenta', 'yellow', 'black'],
        'verso' => [],
    ];

    // WHEN: Action tree feldolgozás
    $result = $this->processWithInking($inking);

    // THEN: Csak 4+ színű gépek szerepelnek
    foreach ($result->getActionPaths() as $path) {
        foreach ($path->getNodes() as $node) {
            if ($node->getMachine()->getType()->value === 'printing press') {
                $this->assertGreaterThanOrEqual(
                    4,
                    $node->getMachine()->getNumberOfColors(),
                    'Printing press must support at least 4 colors for CMYK inking'
                );
            }
        }
    }
}

/**
 * @test
 * Egyszínű nyomtatáshoz 1 színű gép is megfelelő.
 */
public function single_color_inking_allows_single_color_machines(): void
{
    // GIVEN: 1 színű inking
    $inking = [
        'recto' => ['black'],
        'verso' => [],
    ];

    // WHEN: Action tree feldolgozás
    $result = $this->processWithInking($inking);

    // THEN: 1 színű gépek is szerepelhetnek
    $hasOneColorMachine = false;
    foreach ($result->getActionPaths() as $path) {
        foreach ($path->getNodes() as $node) {
            if ($node->getMachine()->getType()->value === 'printing press') {
                if ($node->getMachine()->getNumberOfColors() === 1) {
                    $hasOneColorMachine = true;
                }
            }
        }
    }
    $this->assertTrue($hasOneColorMachine, 'Single color machines should be available for black-only inking');
}
```

#### 0.3.2 Verso Printing Processor

**Cél:** A `VersoPrintingProcessor` helyesen hoz létre verso action-t.

```php
/**
 * @test
 * Verso inking esetén két print action jön létre (recto + verso).
 */
public function verso_inking_creates_two_print_actions(): void
{
    // GIVEN: Kétoldalas inking
    $inking = [
        'recto' => ['cyan', 'magenta', 'yellow', 'black'],
        'verso' => ['black'],
    ];

    // WHEN: Action tree feldolgozás
    $result = $this->processWithInking($inking);

    // THEN: Minden path-ban 2 print action van
    foreach ($result->getActionPaths() as $path) {
        $printCount = 0;
        foreach ($path->getNodes() as $node) {
            if ($node->getMachine()->getType()->value === 'printing press') {
                $printCount++;
            }
        }
        $this->assertEquals(2, $printCount, 'Path should have 2 print actions (recto + verso)');
    }
}

/**
 * @test
 * Csak recto inking esetén egy print action jön létre.
 */
public function recto_only_inking_creates_single_print_action(): void
{
    // GIVEN: Egyoldalas inking
    $inking = [
        'recto' => ['cyan', 'magenta', 'yellow', 'black'],
        'verso' => [],
    ];

    // WHEN: Action tree feldolgozás
    $result = $this->processWithInking($inking);

    // THEN: Minden path-ban 1 print action van
    foreach ($result->getActionPaths() as $path) {
        $printCount = 0;
        foreach ($path->getNodes() as $node) {
            if ($node->getMachine()->getType()->value === 'printing press') {
                $printCount++;
            }
        }
        $this->assertEquals(1, $printCount, 'Path should have 1 print action (recto only)');
    }
}
```

#### 0.3.3 CTP Insertion Processor

**Cél:** CTP action-ök helyesen jönnek létre az inking alapján.

```php
/**
 * @test
 * CTP action költsége függ a színszámtól.
 */
public function ctp_cost_depends_on_color_count(): void
{
    // GIVEN: Két különböző inking
    $inking1Color = ['recto' => ['black'], 'verso' => []];
    $inking4Colors = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];

    // WHEN: Feldolgozás
    $result1 = $this->processWithInking($inking1Color);
    $result4 = $this->processWithInking($inking4Colors);

    // THEN: 4 színű CTP drágább
    $ctpCost1 = $this->extractCtpCost($result1);
    $ctpCost4 = $this->extractCtpCost($result4);

    $this->assertGreaterThan($ctpCost1, $ctpCost4, 'CTP cost should be higher for 4 colors than 1 color');
}

/**
 * @test
 * Verso inking esetén dupla CTP lemez kell.
 */
public function verso_inking_doubles_ctp_plates(): void
{
    // GIVEN: Egyoldalas vs kétoldalas
    $inkingRecto = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];
    $inkingBoth = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => ['black']];

    // WHEN: Feldolgozás
    $resultRecto = $this->processWithInking($inkingRecto);
    $resultBoth = $this->processWithInking($inkingBoth);

    // THEN: Kétoldalas esetén több CTP költség (5 szín vs 4 szín)
    $ctpCostRecto = $this->extractCtpCost($resultRecto);
    $ctpCostBoth = $this->extractCtpCost($resultBoth);

    $this->assertGreaterThan($ctpCostRecto, $ctpCostBoth, 'CTP cost should be higher with verso');
}
```

#### 0.3.4 Machine Enrichment Számítások

**Cél:** A `calculateEnrichment()` helyesen számol inking alapján.

```php
/**
 * @test
 * Offset press enrichment tartalmazza a festék költséget.
 */
public function offset_press_enrichment_includes_ink_cost(): void
{
    // GIVEN: CMYK inking
    $inking = [
        'recto' => ['cyan', 'magenta', 'yellow', 'black'],
        'verso' => [],
    ];

    // WHEN: Feldolgozás
    $result = $this->processWithInking($inking);

    // THEN: Az enrichment tartalmaz ink-related költséget
    $path = $result->getActionPaths()[0];
    $printNode = $this->findPrintNode($path);
    $enrichment = $printNode->getEnrichment();

    $this->assertNotNull($enrichment);
    $cost = $enrichment->getCost();
    $this->assertGreaterThan(0, $cost, 'Print cost should be > 0');
}

/**
 * @test
 * Több szín = magasabb nyomtatási költség.
 */
public function more_colors_means_higher_print_cost(): void
{
    // GIVEN
    $inking1 = ['recto' => ['black'], 'verso' => []];
    $inking4 = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];

    // WHEN
    $result1 = $this->processWithInking($inking1);
    $result4 = $this->processWithInking($inking4);

    // THEN
    $cost1 = $this->extractPrintCost($result1);
    $cost4 = $this->extractPrintCost($result4);

    $this->assertGreaterThan($cost1, $cost4, '4 color print should cost more than 1 color');
}
```

#### 0.3.5 Part-szintű Paraméterek

**Cél:** A `numberOfCopies`, `paperWeight`, `dimensions` helyesen működnek.

```php
/**
 * @test
 * numberOfCopies befolyásolja a sheet count-ot.
 */
public function number_of_copies_affects_sheet_count(): void
{
    // GIVEN: Két különböző példányszám
    $copies100 = 100;
    $copies1000 = 1000;

    // WHEN: Feldolgozás
    $result100 = $this->processWithCopies($copies100);
    $result1000 = $this->processWithCopies($copies1000);

    // THEN: Több példány = több ív
    $sheets100 = $this->extractSheetCount($result100);
    $sheets1000 = $this->extractSheetCount($result1000);

    $this->assertGreaterThan($sheets100, $sheets1000);
}

/**
 * @test
 * paperWeight befolyásolja a költséget.
 */
public function paper_weight_affects_cost(): void
{
    // GIVEN: Könnyű vs nehéz papír
    $weight80 = 80;
    $weight300 = 300;

    // WHEN: Feldolgozás
    $result80 = $this->processWithPaperWeight($weight80);
    $result300 = $this->processWithPaperWeight($weight300);

    // THEN: Nehezebb papír drágább
    $cost80 = $this->extractTotalCost($result80);
    $cost300 = $this->extractTotalCost($result300);

    $this->assertGreaterThan($cost80, $cost300);
}
```

#### 0.3.6 End-to-End Teszt

**Cél:** Teljes folyamat működik változatlanul.

```php
/**
 * @test
 * Teljes feldolgozás azonos eredményt ad refactoring előtt és után.
 */
public function full_processing_produces_consistent_results(): void
{
    // GIVEN: Komplex input
    $request = [
        'parts' => [
            [
                'partId' => 'test-part-1',
                'actions' => [
                    [
                        'name' => 'print',
                        'params' => [
                            'dimensions' => [
                                'open' => ['width' => 200, 'height' => 280],
                                'closed' => ['width' => 100, 'height' => 140],
                            ],
                            'zone' => ['width' => 100, 'height' => 140, 'gripMargin' => 5],
                            'inking' => [
                                'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                'verso' => ['black'],
                            ],
                            'paper' => ['weight' => 250],
                        ],
                    ],
                    ['name' => 'cut', 'params' => []],
                ],
                'properties' => ['copies' => 1000],
            ],
        ],
    ];

    // WHEN: Feldolgozás
    $result = $this->processFullRequest($request);

    // THEN: Snapshot assertion - ezek az értékek NEM változhatnak
    $this->assertGreaterThan(0, count($result->getActionPaths()));

    $firstPath = $result->getActionPaths()[0];
    $this->assertGreaterThan(0, $firstPath->getTotalCost());

    // Elvárt action típusok sorrendben
    $actionTypes = array_map(
        fn($node) => $node->getMachine()->getType()->value,
        $firstPath->getNodes()
    );

    $this->assertContains('ctp', $actionTypes);
    $this->assertContains('printing press', $actionTypes);
    $this->assertContains('cutting machine', $actionTypes);
}
```

### 0.4 Helper Osztály

```php
// tests/Integration/DomainHierarchyRegressionTest.php

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Application\Process\PartPayload;
use App\Service\ActionParamsExtractor;
use App\Domain\Action\ActionTree;
// ... további importok

class DomainHierarchyRegressionTest extends TestCase
{
    private ActionTree $actionTree;
    private ActionParamsExtractor $extractor;
    // ... további függőségek

    protected function setUp(): void
    {
        // DI container-ből vagy kézi létrehozás
        $this->actionTree = // ...
        $this->extractor = // ...
    }

    // === HELPER METHODS ===

    private function processWithInking(array $inking): ProcessResult
    {
        $payload = $this->createPartPayload([
            'inking' => $inking,
            'copies' => 1000,
            'paperWeight' => 120,
            'dimensions' => $this->getDefaultDimensions(),
        ]);

        return $this->processPayload($payload);
    }

    private function processWithCopies(int $copies): ProcessResult
    {
        $payload = $this->createPartPayload([
            'inking' => $this->getDefaultInking(),
            'copies' => $copies,
            'paperWeight' => 120,
            'dimensions' => $this->getDefaultDimensions(),
        ]);

        return $this->processPayload($payload);
    }

    private function processWithPaperWeight(int $weight): ProcessResult
    {
        $payload = $this->createPartPayload([
            'inking' => $this->getDefaultInking(),
            'copies' => 1000,
            'paperWeight' => $weight,
            'dimensions' => $this->getDefaultDimensions(),
        ]);

        return $this->processPayload($payload);
    }

    private function getDefaultInking(): array
    {
        return [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => [],
        ];
    }

    private function getDefaultDimensions(): array
    {
        return [
            'open' => ['width' => 200, 'height' => 280],
            'closed' => ['width' => 100, 'height' => 140],
        ];
    }

    private function extractCtpCost(ProcessResult $result): float
    {
        // CTP node keresése és költség kinyerése
    }

    private function extractPrintCost(ProcessResult $result): float
    {
        // Print node keresése és költség kinyerése
    }

    private function extractSheetCount(ProcessResult $result): int
    {
        // Sheet count kinyerése
    }

    private function extractTotalCost(ProcessResult $result): float
    {
        // Teljes költség kinyerése
    }

    private function findPrintNode(ActionPath $path): ActionPathNode
    {
        // Print node keresése a path-ban
    }
}
```

### 0.5 Meglévő Tesztek Ellenőrzése

Meg kell vizsgálni, hogy a meglévő tesztek közül melyek fedik le a fenti eseteket:

| Terület | Meglévő teszt? | Új teszt kell? |
|---------|----------------|----------------|
| Gép szűrés színszám alapján | ? | Igen, ha nincs |
| Verso action létrehozás | `ActionPathOrderTest` részben | Kiegészíteni |
| CTP színfüggő költség | ? | Igen, ha nincs |
| numberOfCopies → sheet count | ? | Igen, ha nincs |
| paperWeight → költség | ? | Igen, ha nincs |
| End-to-end snapshot | `ActionPathValueSnapshotTest` | Ellenőrizni |

### 0.6 Végrehajtási Sorrend

1. Meglévő tesztek audit - mely területek vannak lefedve
2. Hiányzó tesztek azonosítása
3. Új teszt fájl létrehozása: `DomainHierarchyRegressionTest.php`
4. Tesztek implementálása
5. Minden teszt zöld
6. Commit: "Add regression tests for domain hierarchy refactoring"

### 0.7 Ellenőrző Lista

- [ ] Meglévő tesztek áttekintése
- [ ] `DomainHierarchyRegressionTest.php` létrehozva
- [ ] Gép szűrés tesztek megírva
- [ ] Verso processing tesztek megírva
- [ ] CTP tesztek megírva
- [ ] Machine enrichment tesztek megírva
- [ ] Part paraméter tesztek megírva
- [ ] End-to-end teszt megírva
- [ ] Minden teszt zöld
- [ ] Commit készítve

---

## Fázis 1: Inking Kiszervezése Action Szintre

### 1.1 Új Osztály: PrintActionParams

```php
// src/Domain/Action/PrintActionParams.php
namespace App\Domain\Action;

/**
 * Parameters specific to a print action.
 *
 * Each print action has its own inking specification.
 * This is NOT part-level data - different print actions
 * in the same part can have different inkings.
 */
readonly class PrintActionParams
{
    public function __construct(
        public array $inking,  // ['recto' => [...], 'verso' => [...]]
    ) {}

    public function getRectoInks(): array
    {
        return $this->inking['recto'] ?? [];
    }

    public function getVersoInks(): array
    {
        return $this->inking['verso'] ?? [];
    }

    public function hasVerso(): bool
    {
        return !empty($this->getVersoInks());
    }

    public function getRectoInkCount(): int
    {
        return count($this->getRectoInks());
    }

    public function getVersoInkCount(): int
    {
        return count($this->getVersoInks());
    }

    public function getTotalInkCount(): int
    {
        return $this->getRectoInkCount() + $this->getVersoInkCount();
    }

    public function getMaxColorsPerSide(): int
    {
        return max($this->getRectoInkCount(), $this->getVersoInkCount());
    }
}
```

### 1.2 ActionTreeNode Módosítása

```php
// src/Domain/Action/ActionTreeNode.php

class ActionTreeNode extends ActionPathNode
{
    public function __construct(
        MachineInterface $machine,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        GridFittingInterface $gridFitting,
        ?PrintActionParams $printParams = null,  // ÚJ
    ) {
        parent::__construct($machine);
        $this->pressSheet = $pressSheet;
        $this->zone = $zone;
        $this->gridFitting = $gridFitting;
        $this->printParams = $printParams;  // ÚJ
    }

    public function getPrintParams(): ?PrintActionParams
    {
        return $this->printParams;
    }
}
```

### 1.3 ActionPathNodeInterface Módosítása

```php
interface ActionPathNodeInterface
{
    // ... existing methods ...

    /**
     * Get print-specific parameters if this is a print action.
     * Returns null for non-print actions (cut, fold, etc.)
     */
    public function getPrintParams(): ?PrintActionParams;
}
```

### 1.4 ProcessAbstractAction Módosítása

Jelenleg a `ProcessAbstractAction` a nyers params tömböt tárolja. Módosítsuk:

```php
// src/Domain/Action/ProcessAbstractAction.php

class ProcessAbstractAction
{
    public function __construct(
        private ActionName $actionName,
        private EquipmentFactoryInterface $equipmentFactory,
        private ?PrintActionParams $printParams = null,  // ÚJ
    ) {}

    public function getPrintParams(): ?PrintActionParams
    {
        return $this->printParams;
    }

    public function isPrintAction(): bool
    {
        return $this->actionName === ActionName::Print;
    }
}
```

### 1.5 ActionParamsExtractor Módosítása

```php
// src/Service/ActionParamsExtractor.php

// ELŐTTE: extractInking() → inking a context-be
// UTÁNA: minden print action-höz külön PrintActionParams

private function createAbstractActions(array $actions): array
{
    $abstractActions = [];

    foreach ($actions as $action) {
        $printParams = null;

        if ($action->name === ActionName::Print) {
            $printParams = new PrintActionParams(
                inking: $this->extractInking($action->params)
            );
        }

        $abstractActions[] = new ProcessAbstractAction(
            actionName: $action->name,
            equipmentFactory: $this->equipmentFactoryRegistry->get($action->name),
            printParams: $printParams,
        );
    }

    return $abstractActions;
}
```

### 1.6 ActionTreeInput Módosítása

Eltávolítjuk az inking-et és numberOfColors-t:

```php
// src/Application/Process/ActionTreeInput.php

// ELŐTTE:
readonly class ActionTreeInput
{
    public function __construct(
        public array $abstractActions,
        public array $pressSheets,
        public InputSheetInterface $zone,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $numberOfCopies,
        public float $numberOfColors,      // ← TÖRÖLNI
        public float $paperWeight,
        public array $inking,              // ← TÖRÖLNI
    ) {}
}

// UTÁNA:
readonly class ActionTreeInput
{
    public function __construct(
        public array $abstractActions,     // PrintParams bennük van!
        public array $pressSheets,
        public InputSheetInterface $zone,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $numberOfCopies,
        public float $paperWeight,
        // inking és numberOfColors TÖRÖLVE - action szinten van
    ) {}
}
```

### 1.7 TreeBuildContext Módosítása

Hasonlóan eltávolítjuk az inking-et:

```php
// ELŐTTE:
final class TreeBuildContext
{
    private array $inking;
    private float $numberOfColors;
    // ...
}

// UTÁNA:
final class TreeBuildContext
{
    // inking és numberOfColors TÖRÖLVE
    // ...
}
```

### 1.8 JobContext (→ PartProductionContext) Módosítása

```php
// ELŐTTE:
readonly class JobContext
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,      // ← TÖRÖLNI
        public float $paperWeight,
        public array $inking,              // ← TÖRÖLNI
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
    ) {}

    public function getTotalInkCount(): int { ... }    // ← TÖRÖLNI
    public function hasVerso(): bool { ... }           // ← TÖRÖLNI
    public function getRectoInkCount(): int { ... }    // ← TÖRÖLNI
    public function getVersoInkCount(): int { ... }    // ← TÖRÖLNI
}

// UTÁNA:
readonly class PartProductionContext  // Átnevezve is!
{
    public function __construct(
        public float $numberOfCopies,
        public float $paperWeight,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        // inking metódusok TÖRÖLVE - PrintActionParams-ban vannak
    ) {}
}
```

### 1.9 Érintett Processzorok

**VersoPrintingProcessor:**
```php
// ELŐTTE: $context->getJobContext()->hasVerso()
// UTÁNA: $node->getPrintParams()?->hasVerso() ?? false
```

**ActionTreeBuilder (gép szűrés):**
```php
// ELŐTTE:
$maxColors = max(count($context->getInking()['recto']), count($context->getInking()['verso']));

// UTÁNA:
$printParams = $abstractAction->getPrintParams();
$maxColors = $printParams?->getMaxColorsPerSide() ?? 0;
```

**Machine.calculateEnrichment():**
```php
// ELŐTTE:
public function calculateEnrichment(
    JobContext $jobContext,
    GridFittingInterface $gridFitting,
    PressSheetInterface $pressSheet,
    float $cutSheetCount,
): ActionEnrichmentInterface;

// UTÁNA:
public function calculateEnrichment(
    PartProductionContext $context,
    GridFittingInterface $gridFitting,
    PressSheetInterface $pressSheet,
    float $cutSheetCount,
    ?PrintActionParams $printParams = null,  // ÚJ paraméter
): ActionEnrichmentInterface;
```

### 1.10 Érintett Fájlok Listája

**Új fájlok:**
- `src/Domain/Action/PrintActionParams.php`

**Módosítandó fájlok:**

| Fájl | Változás |
|------|----------|
| `ActionTreeInput.php` | Eltávolítani: inking, numberOfColors |
| `TreeBuildContext.php` | Eltávolítani: inking, numberOfColors |
| `JobContext.php` | Eltávolítani: inking, numberOfColors, ink metódusok |
| `ActionTreeNode.php` | Hozzáadni: printParams |
| `ActionPathNode.php` | Hozzáadni: getPrintParams() |
| `ActionPathNodeInterface.php` | Hozzáadni: getPrintParams() |
| `ProcessAbstractAction.php` | Hozzáadni: printParams |
| `ActionParamsExtractor.php` | PrintActionParams létrehozása |
| `ActionTreeBuilder.php` | printParams használata gép szűrésnél |
| `VersoPrintingProcessor.php` | node->getPrintParams() használata |
| `CtpInsertionProcessor.php` | printParams használata |
| `TodoPreparationProcessor.php` | printParams átadása |
| `MachineInterface.php` | printParams paraméter |
| `Machine.php` | printParams paraméter |
| `OffsetPrintingPress.php` | printParams használata |
| `CTPMachine.php` | printParams használata |

**Tesztek:**
- `JobContextTest.php` → `PartProductionContextTest.php`
- `MachinePrepareTodoTest.php`
- `ProcessTestBase.php`
- Összes ActionTree teszt
- Összes Processor teszt

---

## Fázis 2: JobContext → PartProductionContext Átnevezés

Ez a fázis már egyszerűbb, mert az inking már ki van szervezve.

### 2.1 Fájl és Osztály Átnevezése

```
src/Domain/Job/JobContext.php
    ↓
src/Domain/Part/PartProductionContext.php
```

### 2.2 Változó Nevek

```php
// ELŐTTE:
$jobContext = new JobContext(...);

// UTÁNA:
$productionContext = new PartProductionContext(...);
```

### 2.3 Érintett Fájlok

Lásd Fázis 1 listáját + minden fájl ahol `JobContext` szerepel.

---

## Fázis 3: TreeBuildContext Konszolidálása

### 3.1 Elemzés

A `TreeBuildContext` az inking eltávolítása után sokkal egyszerűbb lesz.
Meg kell vizsgálni, hogy van-e benne olyan adat, ami nincs a `PartProductionContext`-ben.

### 3.2 Lehetőségek

**A) Kompozíció:**
```php
final class TreeBuildContext
{
    public function __construct(
        private PartProductionContext $productionContext,
        // + fa-specifikus állapot
    ) {}
}
```

**B) Megszüntetés:**
Ha nincs fa-specifikus állapot, a `TreeBuildContext` megszüntethető.

---

## Fázis 4: Dokumentáció

- PHPDoc frissítése minden érintett fájlban
- README frissítése (ha van)
- Architektúra dokumentáció frissítése

---

## Implementációs Sorrend

| Lépés | Feladat | Kockázat | Függőség |
|-------|---------|----------|----------|
| 1.1 | `PrintActionParams` létrehozása | Alacsony | - |
| 1.2 | `ProcessAbstractAction` módosítása | Közepes | 1.1 |
| 1.3 | `ActionParamsExtractor` módosítása | Közepes | 1.2 |
| 1.4 | `ActionTreeInput` módosítása (inking eltávolítása) | Magas | 1.3 |
| 1.5 | `TreeBuildContext` módosítása | Magas | 1.4 |
| 1.6 | `ActionTreeNode` módosítása (printParams) | Közepes | 1.1 |
| 1.7 | `ActionTreeBuilder` módosítása | Magas | 1.5, 1.6 |
| 1.8 | Processzorok módosítása | Magas | 1.6 |
| 1.9 | `JobContext` módosítása (inking eltávolítása) | Közepes | 1.8 |
| 1.10 | Machine-ek módosítása | Közepes | 1.9 |
| 2.1 | `JobContext` → `PartProductionContext` átnevezés | Közepes | 1.10 |
| 3.1 | `TreeBuildContext` konszolidálása | Közepes | 2.1 |
| 4.1 | Dokumentáció | Alacsony | 3.1 |

---

## Előnyök

1. **Tiszta domain modell** - Minden adat a megfelelő szinten van
2. **Több print action támogatása** - Minden action a saját inking-jét használja
3. **Nincs rejtett függőség** - A print action paraméterei explicitek
4. **Könnyebb bővítés** - Új action-specifikus paraméterek könnyen hozzáadhatók
5. **Jobb tesztelhetőség** - Egyértelmű, hogy mit kell mockolni

## Kockázatok

1. **Sok fájl módosítása** - ~20 fájl érintett
2. **Törékenység** - A magas kockázatú lépéseknél figyelni kell
3. **Regresszió** - Alapos tesztelés szükséges minden lépés után

## Mitigáció

1. **Inkrementális megközelítés** - Egy lépés, teszt, commit
2. **Feature branch** - Külön branch-en dolgozni
3. **Alapos tesztek** - Minden lépés után `./vendor/bin/phpunit`

---

## Ellenőrző Lista

### Fázis 0 Előfeltételek
- [ ] Minden meglévő teszt zöld
- [ ] Git branch létrehozva (`refactor/clean-domain-hierarchy`)

### Fázis 0 Végrehajtás
- [ ] Meglévő tesztek áttekintése (mely területek vannak lefedve)
- [ ] `DomainHierarchyRegressionTest.php` létrehozva
- [ ] Gép szűrés tesztek megírva (színszám alapján)
- [ ] Verso processing tesztek megírva
- [ ] CTP színfüggő költség tesztek megírva
- [ ] Machine enrichment tesztek megírva
- [ ] Part paraméter tesztek megírva (copies, paperWeight)
- [ ] End-to-end snapshot teszt megírva
- [ ] Minden teszt zöld
- [ ] Commit: "Add regression tests for domain hierarchy refactoring"

### Fázis 1 Előfeltételek
- [ ] Fázis 0 kész (regressziós tesztek zöldek)

### Fázis 1 Végrehajtás
- [ ] PrintActionParams létrehozva
- [ ] ProcessAbstractAction módosítva
- [ ] ActionParamsExtractor módosítva
- [ ] ActionTreeInput módosítva
- [ ] TreeBuildContext módosítva
- [ ] ActionTreeNode módosítva
- [ ] ActionTreeBuilder módosítva
- [ ] Processzorok módosítva
- [ ] JobContext módosítva (inking eltávolítva)
- [ ] Machine-ek módosítva
- [ ] Tesztek frissítve
- [ ] Minden teszt zöld

### Fázis 2 Végrehajtás
- [ ] JobContext → PartProductionContext átnevezés
- [ ] Import-ok frissítése
- [ ] Változó nevek frissítése
- [ ] Tesztek frissítve
- [ ] Minden teszt zöld

### Fázis 3 Végrehajtás
- [ ] TreeBuildContext elemzése
- [ ] Döntés: kompozíció vagy megszüntetés
- [ ] Implementáció
- [ ] Minden teszt zöld

### Fázis 4 Végrehajtás
- [ ] PHPDoc frissítése
- [ ] Dokumentáció frissítése
- [ ] PR review

---

## Megjegyzések

- A refactoring VÁLTOZTAT a belső struktúrán, de a külső API (JSON request/response) változatlan marad
- Minden változtatás után tesztek futtatása kötelező
- Ha egy lépés eltöri a teszteket, AZONNAL javítani, ne menni tovább
- A `PrintActionParams` bevezetése lehetővé teszi a jövőbeli bővítést (pl. spot colors, varnish)
