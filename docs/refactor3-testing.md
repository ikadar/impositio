# ActionTree Refactor - Részletes Tesztelési Terv

**Cél**: 100%-os test coverage az ActionTree osztályra vonatkozóan a biztonságos refaktorálás előtt.

---

## 1. Test Dependencies & Setup

### 1.1 Mock Objects

```php
// Test alapértékek és mock objektumok
class ActionTreeTestBase extends TestCase 
{
    protected Calculator $mockCalculator;
    protected EquipmentFactoryInterface $mockEquipmentFactory;
    protected PropertyAccessorInterface $mockPropertyAccessor;
    protected ActionPathPipeline $mockPipeline;
    
    // Test fixtures
    protected DimensionsInterface $openPoseDimensions;   // 200x300
    protected DimensionsInterface $closedPoseDimensions; // 100x150
    protected PressSheetInterface $pressSheet;           // 1000x700
    protected InputSheetInterface $zone;                 // 190x140
    protected array $inking;                             // ["recto" => [1,2], "verso" => []]
    
    protected function setUp(): void 
    {
        $this->mockCalculator = $this->createMock(Calculator::class);
        $this->mockEquipmentFactory = $this->createMock(EquipmentFactoryInterface::class);
        $this->mockPropertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $this->mockPipeline = $this->createMock(ActionPathPipeline::class);
        
        $this->setupTestFixtures();
    }
}
```

### 1.2 Test Data Fixtures

```php
protected function setupTestFixtures(): void
{
    // Dimensions
    $this->openPoseDimensions = new Dimensions(200, 300);
    $this->closedPoseDimensions = new Dimensions(100, 150);
    
    // PressSheet 
    $this->pressSheet = new PressSheet(1000, 700, 80); // width, height, weight
    
    // Zone (InputSheet)
    $this->zone = new Zone(190, 140, 10); // width, height, grip
    
    // Inking
    $this->inking = [
        "recto" => [1, 2], // 2 színek recto oldalon
        "verso" => []      // nincs verso
    ];
    
    // Abstract Actions
    $this->abstractActions = [
        $this->createPrintAction(),
        $this->createFoldAction()
    ];
}
```

---

## 2. Unit Tests - Protected Methods

### 2.1 calculate() Method Tests

**Coverage**: ~95 sor komplex logika + rekurzió

#### Test: `test_calculate_with_empty_abstract_actions`
**Cél**: Üres action lista kezelése  
**Input**: `[]` (üres tömb)  
**Expected**: `[]` visszaadás  
**Coverage**: Early return ág (L150-152)

#### Test: `test_calculate_single_printing_press_action`
**Cél**: Egyszerű printing press action feldolgozása  
**Input**:
```php
$abstractActions = [$printAction];
$pressSheet = $this->pressSheet;
$zone = $this->zone;
$inking = ["recto" => [1,2], "verso" => []];
```
**Mocks**: 
- `$printAction->getAvailableMachines()` → `[$printingPress1, $printingPress2]`
- `$printingPress1->getNumberOfColors()` → `4` (>= 2, megfelelő)
- `$printingPress2->getNumberOfColors()` → `1` (< 2, nem megfelelő)
- `Calculator` 2 grid fitting-et ad vissza
**Expected**: 2 ActionTreeNode (1 megfelelő gép × 2 grid fitting)  
**Coverage**: Printing press ág (L158-168), színszám szűrés logika

#### Test: `test_calculate_printing_press_with_verso_inking`
**Cél**: Kétoldali nyomtatás kezelése  
**Input**: `inking = ["recto" => [1,2], "verso" => [3,4]]`  
**Expected**: Csak 4+ színes gépek elfogadása  
**Coverage**: Verso inking logika (L161-162)

#### Test: `test_calculate_folder_machine_special_handling`
**Cél**: Folder gép speciális kezelése  
**Setup**:
- `$folderMachine->getType()->value` → `"folder"`
- `zone->setDimensions()` mock elvárt hívás
- `machine->setOpenPoseDimensions()` mock elvárt hívás
**Expected**: Action újra létrehozás folder esetén  
**Coverage**: Folder speciális ág (L186-198)

#### Test: `test_calculate_multiple_press_sheets_multiple_machines`
**Cél**: Kombinatorikus esetkezelés  
**Input**: 
- 2 abstract action
- 3 elérhető gép 
- 2 grid fitting / gép
**Expected**: 2×3×2 = 12 ActionTreeNode  
**Coverage**: Teljes nested loop (L177-225)

#### Test: `test_calculate_recursive_call`
**Cél**: Rekurzív hívás ellenőrzése  
**Setup**: Mock `$this->calculate()` hívást a `setPrevActions()`-ben  
**Expected**: Rekurzív hívás a megfelelő paraméterekkel  
**Coverage**: Rekurzív ág (L214-221)

#### Test: `test_calculate_with_prev_nodes`
**Cél**: `$prevNodes` paraméter figyelmen kívül hagyása  
**Input**: `$prevNodes = [...]` (nem üres)  
**Expected**: `$prevNodes` nem befolyásolja az eredményt  
**Coverage**: Bizonytalan paraméter kezelés

---

### 2.2 flatten() Method Tests

#### Test: `test_flatten_leaf_node`
**Cél**: Levél node (nincs prevActions) kezelése  
**Input**: `$node` amelynek `getPrevActions()` → `[]`  
**Expected**: `[[$node]]` (single path)  
**Coverage**: Alap eset (L252-253)

#### Test: `test_flatten_single_level_tree`
**Cél**: 1 szintű fa lapítása  
**Input**: Root → [Child1, Child2]  
**Expected**: 2 path: `[[Root, Child1], [Root, Child2]]`  
**Coverage**: Egy szintű rekurzió (L257-262)

#### Test: `test_flatten_deep_tree`
**Cél**: Mély fa struktúra lapítása  
**Input**: Root → Child → GrandChild → Leaf  
**Expected**: `[[Root, Child, GrandChild, Leaf]]`  
**Coverage**: Mély rekurzió

#### Test: `test_flatten_complex_branching_tree`
**Cél**: Komplex elágazó fa  
**Setup**:
```
    Root
   /    \
Child1  Child2
  |      |
Leaf1  Leaf2
```
**Expected**: 2 path: `[[Root, Child1, Leaf1], [Root, Child2, Leaf2]]`  
**Coverage**: Komplex rekurzív logika

#### Test: `test_flatten_node_cloning`
**Cél**: Node klónozás működése  
**Verification**: Az eredeti node `prevActions` nem módosul  
**Coverage**: Clone művelet (L248-249)

---

### 2.3 extendWithPipeline() Method Tests

#### Test: `test_extendWithPipeline_creates_correct_context`
**Cél**: ActionPathContext helyes létrehozása  
**Verification**: 
- `ExtensionParams` helyes értékekkel
- `ActionPathContext` helyes inicializálás
- Pipeline `process()` hívás
**Coverage**: Teljes metódus (L348-368)

#### Test: `test_extendWithPipeline_returns_pipeline_result`
**Cél**: Pipeline eredmény visszaadása  
**Setup**: `$mockPipeline->process()` → `$mockContext` melynek `nodes = [...]`  
**Expected**: Pipeline eredmény nodes tömb  
**Coverage**: Return statement (L368)

---

### 2.4 extendLegacy() Method Tests

**Coverage**: ~190 sor komplex legacy logika

#### Test: `test_extendLegacy_printing_press_with_ctp`
**Cél**: CTP beszúrás printing press előtt  
**Input**: `[$printingPressNode]`  
**Setup**: 
- `$equipmentFactory->fromId("ctp-machine")` → `$ctpMachine`
- `$printingPressNode->getMachine()->getType()->value` → `"printing press"`
**Expected**: `[$ctpAction, $printingPressNode]`  
**Coverage**: CTP ág (L391-427)

#### Test: `test_extendLegacy_printing_press_with_verso`
**Cél**: Verso nyomtatás beszúrása  
**Setup**: 
- `$propertyAccessor->getValue($inking, "[verso]")` → `[3,4]` (nem üres)
**Expected**: `[$ctpAction, $recto, $verso]`  
**Coverage**: Verso ág (L487-515)

#### Test: `test_extendLegacy_folder_machine_todo`
**Cél**: Folder gép todo beállítása  
**Expected**: Todo tartalmazza `openPoseDimensions`, `closedPoseDimensions`, `inputSheetLength`  
**Coverage**: Folder ág (L429-445)

#### Test: `test_extendLegacy_stitching_machine_todo`
**Cél**: Stitching machine todo beállítása  
**Coverage**: Stitching ág (L447-452)

#### Test: `test_extendLegacy_cutout_machine_todo`
**Cél**: Cutout machine todo beállítása  
**Coverage**: Cutout ág (L455-468)

#### Test: `test_extendLegacy_splitter_todo`
**Cél**: Splitter todo beállítása  
**Coverage**: Splitter ág (L470-475)

#### Test: `test_extendLegacy_assembler_todo`
**Cél**: Assembler todo beállítása  
**Coverage**: Assembler ág (L477-482)

#### Test: `test_extendLegacy_cutting_insertion_with_trim_and_cuts`
**Cél**: Vágás beszúrása trim + cut vonalakkal  
**Setup**:
- `$node->getMachine()->getMaxSheetDimensions()` ≠ `$nextAction->getZone()->getDimensions()`
- `$gridFitting->getTrimLines()` → `["top" => ["y" => 10], "left" => ["x" => 5]]`
- `$gridFitting->getCols()` → `2`, `getRows()` → `3`
**Expected**: 
- `numberOfTrimCuts = 4` (2+2)
- `numberOfCutCuts = 3` (2-1 + 3-1)
- `numberOfCuts = 7`
- Cutting action beszúrása
**Coverage**: Cutting logika (L517-558)

#### Test: `test_extendLegacy_cut_sheet_count_multiplication`
**Cél**: Cut sheet count frissítése vágás után  
**Setup**: Grid fitting 2×3  
**Expected**: `cutSheetCount *= 6`  
**Coverage**: Cut sheet count logika (L556-558)

#### Test: `test_extendLegacy_complex_workflow`
**Cél**: Teljes komplex workflow  
**Input**: `[ctpNode, printingNode, foldingNode]` egymás után  
**Expected**: Helyes sorrend és todo értékek  
**Coverage**: Teljes workflow logika

---

## 3. Unit Tests - Public Methods

### 3.1 flattenTree() Method Tests

#### Test: `test_flattenTree_calls_flatten_for_each_root`
**Cél**: flattenTree() minden root elemre hívja flatten()-t  
**Setup**: `$root = [$node1, $node2]`  
**Expected**: flatten() hívás mindkét node-ra  
**Coverage**: Root iteráció (L234-236)

#### Test: `test_flattenTree_reverses_paths`
**Cél**: Path-ok megfordítása  
**Setup**: flatten() visszaad `[[A,B,C], [A,D,E]]`  
**Expected**: `[[C,B,A], [E,D,A]]`  
**Coverage**: array_reverse() logika (L240)

### 3.2 extend() Method Tests

#### Test: `test_extend_uses_pipeline_when_available`
**Cél**: Pipeline használata ha elérhető  
**Setup**: `$this->pipeline !== null`  
**Expected**: `extendWithPipeline()` hívás  
**Coverage**: Pipeline ág (L336-339)

#### Test: `test_extend_falls_back_to_legacy`
**Cél**: Legacy használata ha nincs pipeline  
**Setup**: `$this->pipeline === null`  
**Expected**: `extendLegacy()` hívás  
**Coverage**: Legacy ág (L341-342)

### 3.3 process() Method Tests

#### Test: `test_process_delegates_to_extendPaths`
**Cél**: Paraméter átadás ellenőrzése  
**Expected**: `extendPaths()` hívás helyes paraméterekkel  
**Coverage**: Teljes metódus (L267-291)

### 3.4 extendPaths() Method Tests

#### Test: `test_extendPaths_sets_all_properties`
**Cél**: Setter metódusok hívása  
**Verification**: Minden setter hívva van (L307-312)  
**Coverage**: Property beállítás

#### Test: `test_extendPaths_processes_each_press_sheet`
**Cél**: Minden press sheet feldolgozása  
**Input**: 3 press sheet  
**Expected**: 3× `calculateTree()` + `flattenTree()` + `extend()` hívás  
**Coverage**: Press sheet loop (L316-325)

#### Test: `test_extendPaths_accumulates_results`
**Cél**: Eredmények gyűjtése  
**Expected**: Minden extended path az eredménybe kerül  
**Coverage**: Akkumuláció (L322-324)

### 3.5 calculateTree() Method Tests

#### Test: `test_calculateTree_delegates_to_calculate`
**Cél**: Wrapper működés ellenőrzése  
**Expected**: `calculate()` hívás + `setRoot()` hívás  
**Coverage**: Teljes metódus (L121-132)

---

## 4. Integration Tests

### 4.1 Complete Workflow Tests

#### Test: `test_complete_workflow_print_and_fold`
**Cél**: Teljes workflow end-to-end teszt  
**Scenario**: Print + Fold action  
**Input**: 
- 2 abstract action: [print, fold]
- 1 press sheet
- Standard zone + dimensions
**Expected**: 
1. Action tree felépítése
2. Tree flatten
3. Path extension (CTP beszúrása, cutting, stb.)
4. Helyes node sorrend és todo értékek

#### Test: `test_workflow_with_verso_printing`
**Cél**: Kétoldali nyomtatás workflow  
**Input**: Recto + verso inking  
**Expected**: CTP → Recto → Verso → további műveletek

#### Test: `test_workflow_multiple_press_sheets`
**Cél**: Több press sheet kezelése  
**Input**: 3 különböző press sheet  
**Expected**: 3 külön action path set

#### Test: `test_workflow_error_handling`
**Cél**: Hibakezelés  
**Scenario**: Nincs megfelelő gép a színek számához  
**Expected**: Üres vagy részleges eredmény

### 4.2 Pipeline vs Legacy Comparison Tests

#### Test: `test_pipeline_vs_legacy_output_compatibility`
**Cél**: Pipeline és legacy kimenet azonossága  
**Setup**: Azonos input adatok  
**Verification**: 
- Pipeline result ≈ Legacy result (költség, időtartam, node sorrend)
- Csak a belső implementáció különbözik

#### Test: `test_pipeline_performance_baseline`
**Cél**: Performance különbség mérése  
**Measurement**: Pipeline vs legacy futási idő

---

## 5. Snapshot Tests

### 5.1 Golden Master Tests

#### Test: `test_snapshot_simple_print_workflow`
**Purpose**: Egyszerű nyomtatás workflow kimenet rögzítése  
**Data**: Standard print action + paraméterek  
**Snapshot**: JSON output az action path-okkal

#### Test: `test_snapshot_complex_multi_action_workflow`
**Purpose**: Komplex multi-action workflow  
**Data**: Print + Fold + Stitch sequence  
**Snapshot**: Teljes extended action path

#### Test: `test_snapshot_all_machine_types`
**Purpose**: Minden gép típus tesztelése  
**Data**: Minden ActionName enum érték  
**Snapshot**: Machine-specific todo értékek

### 5.2 Regression Tests

#### Test: `test_snapshot_regression_issue_123`
**Purpose**: Ismert bug regression teszt  
**Description**: Cutting machine téves beszúrása  
**Expected**: Fix utáni helyes kimenet

---

## 6. Property-Based Tests

### 6.1 Invariant Tests

#### Test: `test_property_tree_flattening_preserves_all_nodes`
**Property**: `count(flatten(tree)) === count(all_nodes_in_tree)`  
**Generator**: Véletlenszerű tree struktúrák  
**Invariant**: Minden node megjelenik a flat path-okban

#### Test: `test_property_cut_sheet_count_monotonic_increase`
**Property**: Cut sheet count soha nem csökken a path során  
**Generator**: Véletlenszerű grid fitting értékek  
**Invariant**: `cutSheetCount[i] <= cutSheetCount[i+1]`

---

## 7. Performance Tests

### 7.1 Scalability Tests

#### Test: `test_performance_large_tree_depth`
**Goal**: Mély fa teljesítmény  
**Data**: 10+ szintű action tree  
**Measurement**: Flatten idő < 1s

#### Test: `test_performance_wide_tree_branching`
**Goal**: Széles fa teljesítmény  
**Data**: 100+ gép/grid fitting kombináció  
**Measurement**: Calculate idő < 5s

#### Test: `test_memory_usage_large_datasets`
**Goal**: Memória használat ellenőrzése  
**Measurement**: Memória nem nő exponenciálisan

---

## 8. Test Implementation Priority

### 🔴 Phase 1 - Kritikus (Első hét)
1. **calculate() unit tests** - Core logika biztosítása
2. **flatten() unit tests** - Tree lapítás működés
3. **Complete workflow integration test** - End-to-end működés
4. **Snapshot tests** - Golden master rögzítése

### 🟡 Phase 2 - Fontos (Második hét) 
1. **extendLegacy() teljes coverage** - Legacy logika védelme
2. **Pipeline vs legacy comparison** - Kompatibilitás
3. **Error handling tests** - Hibák kezelése
4. **Property-based tests** - Invariant ellenőrzés

### 🟢 Phase 3 - Kiegészítő (Harmadik hét)
1. **Performance tests** - Teljesítmény benchmark
2. **Edge case tests** - Szélsőséges esetek 
3. **Regression tests** - Korábbi bugok
4. **Documentation tests** - Példa kódok működése

---

## 9. Test Data Management

### 9.1 Test Fixtures Repository
```php
class ActionTreeTestFixtures 
{
    public static function createStandardPrintAction(): ProcessAbstractAction 
    {
        // Standard 4-color print action
    }
    
    public static function createComplexWorkflow(): array 
    {
        // Print + Fold + Stitch workflow
    }
    
    public static function createLargePressSheetSet(): array 
    {
        // 10 különböző méretű press sheet
    }
}
```

### 9.2 Snapshot Storage
- `tests/snapshots/action-tree/simple-print.json`
- `tests/snapshots/action-tree/complex-workflow.json`
- `tests/snapshots/action-tree/all-machine-types.json`

---

## 10. Coverage Requirements

### 10.1 Minimum Coverage Targets
- **Line Coverage**: 100% minden metódusra
- **Branch Coverage**: 95%+ (minden if/else ág)
- **Path Coverage**: 90%+ (főbb execution path-ok)

### 10.2 Critical Path Coverage
1. ✅ **calculate() rekurzív logika** - Minden rekurzív ág
2. ✅ **Machine type filtering** - Minden machine type
3. ✅ **Grid fitting iteration** - Minden kombinatorikus eset  
4. ✅ **Legacy extend() logic** - Minden machine type branch
5. ✅ **Cutting insertion logic** - Trim + cut számítások
6. ✅ **Pipeline vs legacy paths** - Mindkét implementation

---

## 11. Tesztek Érvényessége a Refaktorálás Után

### 11.1 Test Survival Matrix

| Teszt Kategória | Phase 1-2 után | Phase 3 után | Phase 4-5 után | Phase 6 után | Megjegyzés |
|-----------------|---|---|---|---|---|
| **Unit: calculate()** | ✅ 100% | ✅ 100% | ⚠️ 70% | ⚠️ 50% | ActionTreeBuilder-re migrálás szükséges |
| **Unit: flatten()** | ✅ 100% | ✅ 100% | ⚠️ 80% | ⚠️ 80% | ActionTreeFlattener-re migrálás szükséges |
| **Unit: extendLegacy()** | ✅ 100% | ✅ 100% | ✅ 100% | ❌ 0% | Phase 6-ban törlésre kerül |
| **Unit: extendWithPipeline()** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Marad, pipeline-only lesz |
| **Unit: extend()** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Wrapper marad |
| **Unit: process()** | ✅ 100% | ✅ 100% | ⚠️ 70% | ⚠️ 70% | ActionTreeProcessor-re migrálás |
| **Unit: extendPaths()** | ✅ 100% | ✅ 100% | ⚠️ 60% | ⚠️ 60% | Orchestration módosul |
| **Unit: calculateTree()** | ✅ 100% | ✅ 100% | ⚠️ 50% | ⚠️ 50% | ActionTreeBuilder-be integrálódik |
| **Unit: flattenTree()** | ✅ 100% | ✅ 100% | ⚠️ 80% | ⚠️ 80% | ActionTreeFlattener-be integrálódik |
| **Integration: Complete Workflow** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Behavior-based, marad |
| **Integration: Pipeline vs Legacy** | ✅ 100% | ✅ 100% | ✅ 100% | ❌ 0% | Legacy eltávolítás után irreleváns |
| **Snapshot: Simple Print** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Output nem változik |
| **Snapshot: Complex Workflow** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Output nem változik |
| **Snapshot: All Machine Types** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Output nem változik |
| **Property-based Tests** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Invariant-ok maradnak |
| **Performance Tests** | ✅ 100% | ✅ 100% | ✅ 100% | ✅ 100% | Baseline összehasonlítás |

---

### 11.2 Phase-by-Phase Test Impact Analysis

#### **Phase 1-2: Tesztek + Kód Tisztítás**
**Refaktor típusa**: Nem strukturális  
**Test Impact**: ✅ **0% veszteség**

- Kikommentezett kód törlése → Nem érinti a teszteket
- Magic string-ek → Enum konstansokra cserélés → Tesztek adaptálása (trivial)
- Típus annotációk → Nem érinti a tesztek logikáját
- PHPDoc frissítés → Nem érinti a teszteket

**Akció**: Tesztek változatlanul futnak, esetleg mock setup-ok frissítése.

---

#### **Phase 3: Interface-ek Feltöltése**
**Refaktor típusa**: Nem strukturális  
**Test Impact**: ✅ **0% veszteség**

- Interface metódusok definiálása → Csak type hints javulás
- Implementáció nem változik → Tesztek futnak

**Akció**: Tesztek változatlanul futnak.

---

#### **Phase 4-5: Felelősségek Szétválasztása + Mutable State Eliminálása**
**Refaktor típusa**: Strukturális  
**Test Impact**: ⚠️ **30-50% migráció szükséges**

**Érintett tesztek:**

1. **calculate() unit tests** → **ActionTreeBuilder unit tests**
   - Teszt logika: 100% átmásolható
   - Mock setup: Módosítás szükséges (`ActionTreeBuilder` helyett `ActionTree`)
   - Survival rate: **70%** (új builder API-hoz adaptálás)

2. **flatten() unit tests** → **ActionTreeFlattener unit tests**
   - Teszt logika: 100% átmásolható
   - Mock setup: Módosítás szükséges
   - Survival rate: **80%** (flattener API-hoz adaptálás)

3. **process() unit tests** → **ActionTreeProcessor unit tests**
   - Teszt logika: 60% átmásolható (orchestration módosul)
   - Új tesztek szükségesek az új workflow-hoz
   - Survival rate: **70%**

4. **extendPaths() unit tests** → **ActionTreeProcessor unit tests**
   - Teszt logika: 60% átmásolható
   - Survival rate: **60%**

5. **calculateTree() unit tests** → **ActionTreeBuilder unit tests**
   - Teszt logika: 50% átmásolható (wrapper eltűnik)
   - Survival rate: **50%**

6. **flattenTree() unit tests** → **ActionTreeFlattener unit tests**
   - Teszt logika: 80% átmásolható
   - Survival rate: **80%**

**Maradnak 100%-ban:**
- ✅ `extend()` unit tests (wrapper marad)
- ✅ `extendWithPipeline()` unit tests (pipeline logika marad)
- ✅ Integration tesztek (behavior-based)
- ✅ Snapshot tesztek (output-based)
- ✅ Property-based tesztek (invariant-ok)
- ✅ Performance tesztek

**Migráció stratégia:**
```php
// ELŐTTE: ActionTree::calculate() teszt
class ActionTreeTest extends TestCase {
    public function test_calculate_single_printing_press_action() {
        $actionTree = new ActionTree(...);
        $result = $actionTree->calculate($actions, $sheet, $zone, [], $inking);
        $this->assertCount(2, $result);
    }
}

// UTÁN: ActionTreeBuilder::build() teszt
class ActionTreeBuilderTest extends TestCase {
    public function test_build_single_printing_press_action() {
        $builder = new ActionTreeBuilder(...);
        $context = new TreeBuildContext($actions, $sheet, $zone, $inking);
        $result = $builder->build($context);
        $this->assertCount(2, $result);
    }
}
```

---

#### **Phase 6: Legacy Kód Eltávolítása**
**Refaktor típusa**: Kód törlés  
**Test Impact**: ⚠️ **10-15% veszteség**

**Törlésre kerülő tesztek:**

1. ❌ **extendLegacy() unit tests** (9 teszt)
   - `test_extendLegacy_printing_press_with_ctp`
   - `test_extendLegacy_printing_press_with_verso`
   - `test_extendLegacy_folder_machine_todo`
   - `test_extendLegacy_stitching_machine_todo`
   - `test_extendLegacy_cutout_machine_todo`
   - `test_extendLegacy_splitter_todo`
   - `test_extendLegacy_assembler_todo`
   - `test_extendLegacy_cutting_insertion_with_trim_and_cuts`
   - `test_extendLegacy_complex_workflow`
   - `test_extendLegacy_cut_sheet_count_multiplication`

2. ❌ **Pipeline vs Legacy comparison test** (2 teszt)
   - `test_pipeline_vs_legacy_output_compatibility`
   - `test_pipeline_performance_baseline`

**Maradnak 100%-ban:**
- ✅ `extend()` unit tests (pipeline-only lesz)
- ✅ `extendWithPipeline()` unit tests
- ✅ Integration tesztek (pipeline-based)
- ✅ Snapshot tesztek (output-based, pipeline output)
- ✅ Property-based tesztek
- ✅ Performance tesztek

**Akció**: Ezek a tesztek már nem szükségesek, de az output-based snapshot tesztek garantálják, hogy a pipeline helyesen működik.

---

### 11.3 Test Migration Checklist

#### **Phase 4-5 után szükséges akciók:**

```
[ ] ActionTreeBuilder unit tesztek létrehozása
    - calculate() tesztek átmásolása
    - calculateTree() tesztek átmásolása
    - Új API-hoz adaptálás (TreeBuildContext)
    
[ ] ActionTreeFlattener unit tesztek létrehozása
    - flatten() tesztek átmásolása
    - flattenTree() tesztek átmásolása
    - Új API-hoz adaptálás
    
[ ] ActionTreeProcessor unit tesztek létrehozása
    - process() tesztek átmásolása
    - extendPaths() tesztek átmásolása
    - Új orchestration logika tesztelése
    
[ ] ActionTree unit tesztek frissítése
    - Csak wrapper/delegation tesztek maradnak
    - Mutable state tesztek törlése
    
[ ] Integration tesztek frissítése
    - Új class hierarchia-hoz adaptálás
    - Behavior-based tesztek maradnak
    
[ ] Snapshot tesztek frissítése
    - Új output snapshot-ok rögzítése
    - Regression protection fenntartása
```

#### **Phase 6 után szükséges akciók:**

```
[ ] extendLegacy() tesztek törlése (9 teszt)
[ ] Pipeline vs Legacy tesztek törlése (2 teszt)
[ ] extend() tesztek frissítése (pipeline-only)
[ ] Integration tesztek frissítése (legacy eltávolítás)
[ ] Snapshot tesztek frissítése (pipeline-only output)
```

---

### 11.4 Test Reusability Summary

**Teljes refaktorálás után (Phase 1-6):**

| Teszt Típus | Eredeti | Marad | Migrálható | Törlendő |
|---|---|---|---|---|
| Unit: calculate() | 7 | 0 | 7 | 0 |
| Unit: flatten() | 5 | 0 | 4 | 1 |
| Unit: extendLegacy() | 10 | 0 | 0 | 10 |
| Unit: extendWithPipeline() | 2 | 2 | 0 | 0 |
| Unit: extend() | 2 | 2 | 0 | 0 |
| Unit: process() | 1 | 0 | 1 | 0 |
| Unit: extendPaths() | 3 | 0 | 2 | 1 |
| Unit: calculateTree() | 1 | 0 | 1 | 0 |
| Unit: flattenTree() | 2 | 0 | 2 | 0 |
| **Unit Összesen** | **33** | **4** | **17** | **12** |
| Integration | 6 | 5 | 1 | 0 |
| Snapshot | 4 | 4 | 0 | 0 |
| Property-based | 2 | 2 | 0 | 0 |
| Performance | 3 | 3 | 0 | 0 |
| **ÖSSZESEN** | **48** | **18** | **18** | **12** |

**Végeredmény:**
- ✅ **18 teszt marad 100%-ban** (37.5%)
- ⚠️ **18 teszt migrálható** (37.5%)
- ❌ **12 teszt törlendő** (25%)

---

### 11.5 Ajánlás: Behavior-Based Testing Strategy

A **strukturális refaktorálás után** a legjobb megközelítés:

1. **Behavior-based tesztek megtartása** (Integration + Snapshot)
   - Output-based assertion-ök
   - Implementáció-független
   - Refaktorálás-rezisztens

2. **Unit tesztek migrálása az új osztályokra**
   - ActionTreeBuilder unit tesztek
   - ActionTreeFlattener unit tesztek
   - ActionTreeProcessor unit tesztek

3. **Legacy tesztek törlése**
   - extendLegacy() tesztek
   - Pipeline vs Legacy tesztek

4. **Snapshot tesztek mint "golden master"**
   - Garantálja, hogy a refaktorálás nem változtatja meg az output-ot
   - Regression protection

---

**Következő lépés**: Test suite implementálása phpunit-tal, kezdve a Phase 1 kritikus tesztekkel.