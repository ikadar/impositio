# ActionTree::extend() Refactoring

> **Note for Claude Code**
> This document covers the second refactoring phase: cleaning up `ActionTree::extend()` method.

---

## 1. ActionTree::extend() Hívási Struktúra

### 1.1 Teljes hívási lánc

```
ProcessController::process()
  └─> ProcessUseCase::execute()
        └─> ActionTree::process()
              └─> ActionTree::extendPaths()
                    ├─> ActionTree::calculateTree()
                    │     └─> ActionTree::calculate() [rekurzív]
                    ├─> ActionTree::flattenTree()
                    │     └─> ActionTree::flatten() [rekurzív]
                    └─> ActionTree::extend() ← EZ A FŐ REFACTORING TARGET
```

### 1.2 extend() metódus bemenete

- `$flatActionPath`: `ActionTreeNode[]` tömb - a fa egy lapított ága
- Osztály szintű property-k (korábban beállítva `extendPaths()`-ben):
  - `$openPoseDimensions`
  - `$closedPoseDimensions`
  - `$numberOfCopies`
  - `$numberOfColors`
  - `$paperWeight`
  - `$inking`

### 1.3 extend() metódus kimenete

- `ActionPathNode[]` tömb - kibővített action path, ami tartalmaz:
  - Eredeti action-öket (módosított `todo` array-jel)
  - Automatikusan hozzáadott action-öket (CTP, verso printing, cutting)

---

## 2. ActionTree::extend() Jelenlegi Logikájának Elemzése

### 2.1 A metódus felelősségei (jelenleg egyben)

A ~190 soros `extend()` metódus a következő feladatokat végzi:

| # | Felelősség | Sorok | Gép típus |
|---|------------|-------|-----------|
| 1 | CTP action automatikus beszúrása print elé | 344-373 | printing press |
| 2 | Print action `todo` beállítása | 375-381 | printing press |
| 3 | Verso printing action beszúrása (ha van verso inking) | 442-470 | printing press |
| 4 | Folder action `todo` beállítása | 384-400 | folder |
| 5 | Stitching action `todo` beállítása | 402-407 | stitching machine |
| 6 | Cutout action `todo` beállítása | 410-423 | cutout machine |
| 7 | Splitter action `todo` beállítása | 425-430 | splitter |
| 8 | Assembler action `todo` beállítása | 432-437 | assembler |
| 9 | Cutting action automatikus beszúrása (trim + cut) | 472-509 | cutting machine |
| 10 | `cutSheetCount` tracking és frissítés | 328, 511-513 | cross-cutting |

### 2.2 Részletes logika géptípusonként

#### 2.2.1 Printing Press (lines 342-382, 442-470)

**Előtte beszúrt action:**
```php
// CTP action beszúrása (alumínium lemez készítés)
$ctpAction = new ActionPathNode($ctpMachine, ..., [
    "numberOfCopies" => $this->numberOfCopies,
    "numberOfColors" => $this->numberOfColors,
    "cutSheetCount" => $cutSheetCount,
    "inking" => $this->getInking()
]);
$extendedActionPath[] = $ctpAction;
```

**Todo beállítás:**
```php
$node->setTodo([
    "numberOfCopies" => $this->numberOfCopies,
    "numberOfColors" => $this->numberOfColors,
    "paperWeight" => $this->paperWeight,
    "cutSheetCount" => $cutSheetCount,
]);
```

**Utána beszúrt action (ha van verso):**
```php
if (verso inking exists) {
    $versoPrintingAction = new ActionPathNode(...);
    $versoPrintingAction->setTodo([...]);
    $extendedActionPath[] = $versoPrintingAction;
}
```

#### 2.2.2 Folder (lines 384-400)

```php
$node->setTodo([
    "openPoseDimensions" => [...],
    "closedPoseDimensions" => [...],
    "inputSheetLength" => $openPoseDimensions->getHeight() / 1000,
    "cutSheetCount" => $cutSheetCount,
    "numberOfCopies" => $this->numberOfCopies,
]);
```

#### 2.2.3 Stitching Machine (lines 402-407)

```php
$node->setTodo([
    "numberOfCopies" => $this->numberOfCopies,
    "cutSheetCount" => $cutSheetCount
]);
```

#### 2.2.4 Cutout Machine (lines 410-423)

```php
$node->setTodo([
    "numberOfCopies" => $this->numberOfCopies,
    "cutSheetCount" => $cutSheetCount,
    "openPoseDimensions" => [...],
    "closedPoseDimensions" => [...],
]);
```

#### 2.2.5 Splitter (lines 425-430)

```php
$node->setTodo([
    "numberOfCopies" => $this->numberOfCopies,
    "cutSheetCount" => $cutSheetCount,
]);
```

#### 2.2.6 Assembler (lines 432-437)

```php
$node->setTodo([
    "numberOfCopies" => $this->numberOfCopies,
    "cutSheetCount" => $cutSheetCount,
]);
```

#### 2.2.7 Cutting (automatikus, lines 472-513)

```php
// Számítások:
$numberOfTrimCuts = ...; // sheet levágások
$numberOfCutCuts = cols - 1 + rows - 1; // grid vágások

if ($numberOfCuts > 0) {
    $cuts = new ActionPathNode($cuttingMachine, ..., [
        "numberOfCuts" => $numberOfCuts,
        "numberOfCopies" => $this->numberOfCopies,
        "numberOfColors" => $this->numberOfColors,
        "paperWeight" => $this->paperWeight,
        "cutSheetCount" => $cutSheetCount,
        "trimCuts" => $numberOfTrimCuts,
        "cuts" => $numberOfCutCuts,
    ]);
    $extendedActionPath[] = $cuts;
}

// cutSheetCount update
if ($numberOfCutCuts > 0) {
    $cutSheetCount = $cutSheetCount * cols * rows;
}
```

---

## 3. Problémák az Aktuális Implementációval

### 3.1 Single Responsibility Principle megsértése

Az `extend()` metódus 7 különböző géptípus logikáját tartalmazza egyetlen metódusban.

### 3.2 Open/Closed Principle megsértése

Új géptípus hozzáadásakor az `extend()` metódust kell módosítani (új `if` blokk).

### 3.3 Kód duplikáció

- `numberOfCopies` és `cutSheetCount` minden `todo`-ban szerepel
- Hasonló struktúrájú `setTodo()` hívások ismétlődnek

### 3.4 Implicit függőségek

- A gépek `calculateCost()` metódusai a `todo` array bizonyos kulcsait várják
- Nincs explicit contract, hogy melyik gép milyen `todo` kulcsokat vár

### 3.5 Automatikus action beszúrás keveredik a todo beállítással

- CTP beszúrás
- Verso printing beszúrás
- Cutting beszúrás

Ezek különböző szintű felelősségek.

---

## 4. Javasolt Refactoring Stratégia

### 4.1 Opció A: Strategy Pattern az Action-ökben

Minden action típushoz létrehozni egy `ActionExtender` strategy-t:

```php
interface ActionExtenderInterface
{
    public function supports(ActionTreeNode $node): bool;
    public function extend(
        ActionTreeNode $node,
        ExtensionContext $context
    ): ExtensionResult;
}

class ExtensionContext
{
    public float $numberOfCopies;
    public float $numberOfColors;
    public float $paperWeight;
    public array $inking;
    public DimensionsInterface $openPoseDimensions;
    public DimensionsInterface $closedPoseDimensions;
    public float $cutSheetCount;
    public ?ActionTreeNode $nextAction;
}

class ExtensionResult
{
    /** @var ActionPathNode[] */
    public array $nodesBefore = [];
    public ActionPathNode $mainNode;
    /** @var ActionPathNode[] */
    public array $nodesAfter = [];
    public float $newCutSheetCount;
}
```

Konkrét implementációk:
- `PrintingPressExtender`
- `FolderExtender`
- `StitchingMachineExtender`
- `CutoutMachineExtender`
- `SplitterExtender`
- `AssemblerExtender`

### 4.2 Opció B: Logika áthelyezése a Machine osztályokba

A `Machine` osztályoknak már van `calculateCost()`, `calculateSetupDuration()`, `calculateRunDuration()` metódusuk.

Hozzáadni egy `prepareTodo()` metódust:

```php
interface MachineInterface
{
    // Meglévő metódusok...

    public function prepareTodo(ExtensionContext $context): array;
    public function getPreActions(ExtensionContext $context): array;
    public function getPostActions(ExtensionContext $context): array;
}
```

### 4.3 Opció C: Hibrid megközelítés

1. A `todo` előkészítést áthelyezni a Machine-okba
2. Az automatikus action beszúrást (CTP, cutting) külön service-be

---

## 5. Tisztázott Kérdések

### 5.1 CTP és Cutting automatikus beszúrás

> **Kérdés 1:** A CTP action mindig kell print előtt? Van olyan eset, amikor nem kell?
>
> **Válasz:** Offset printer esetén mindig kell, és jelenleg csak offset printer van a gépek között, tehát mindig kell.

> **Kérdés 2:** A Cutting action automatikusan kerül be, amikor a sheet méretek változnak. Ez helyes üzleti logika? Vagy a kliens küld explicit `cut` action-t a payload-ban?
>
> **Válasz:** Ez a helyes logika. A kliens csak ritkán küld explicit cutting action-t.

### 5.2 Verso printing

> **Kérdés 3:** A verso printing mindig a PrintingPress másodszori futása? Vagy lehet más gép is?
>
> **Válasz:** Mindig a PrintingPress másodszori futása.

### 5.3 cutSheetCount tracking

> **Kérdés 4:** A `cutSheetCount` a "hány ív van jelenleg a folyamatban" értelmezésű? Ez globális state, amit végig kell vezetni az action path-on?
>
> **Válasz:** Igen.

### 5.4 Választott irány: Pipeline Pattern

**Választás:** Pipeline Pattern (Opció D)

**Indoklás:**
- Van amikor az action beszúrás az action-tól függ, van amikor a géptől
- Van olyan gép ami egyszerre 2 action-t tud végezni (pl. stitching + cutting)
- A pipeline lehetővé teszi a felelősségek tiszta szétválasztását

---

## 6. Választott Architektúra: Pipeline Pattern

### 6.1 Áttekintés

```
ActionTree::extend($flatActionPath)
    │
    ▼
┌─────────────────────────────────────────────────────┐
│              ActionPathPipeline                      │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │ 1. TodoPreparationProcessor (priority: 10)  │    │
│  │    - Beállítja a todo-t minden node-hoz     │    │
│  │    - Delegál a Machine::prepareTodo()-nak   │    │
│  └─────────────────────────────────────────────┘    │
│                      │                               │
│                      ▼                               │
│  ┌─────────────────────────────────────────────┐    │
│  │ 2. CtpInsertionProcessor (priority: 20)     │    │
│  │    - CTP action beszúrása print elé         │    │
│  └─────────────────────────────────────────────┘    │
│                      │                               │
│                      ▼                               │
│  ┌─────────────────────────────────────────────┐    │
│  │ 3. VersoPrintingProcessor (priority: 30)    │    │
│  │    - Verso print beszúrása ha van verso     │    │
│  └─────────────────────────────────────────────┘    │
│                      │                               │
│                      ▼                               │
│  ┌─────────────────────────────────────────────┐    │
│  │ 4. CuttingInsertionProcessor (priority: 40) │    │
│  │    - Cutting beszúrása ha layout változik   │    │
│  └─────────────────────────────────────────────┘    │
│                      │                               │
│                      ▼                               │
│  ┌─────────────────────────────────────────────┐    │
│  │ 5. CutSheetCountProcessor (priority: 50)    │    │
│  │    - cutSheetCount tracking és frissítés    │    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
└─────────────────────────────────────────────────────┘
    │
    ▼
ActionPathNode[] (extended)
```

### 6.2 Interfészek

```php
namespace App\Domain\Action\Pipeline;

interface ActionPathProcessorInterface
{
    /**
     * Feldolgozza a kontextust és visszaadja a módosított kontextust.
     */
    public function process(ActionPathContext $context): ActionPathContext;

    /**
     * Processzor prioritása (alacsonyabb = előbb fut).
     */
    public function getPriority(): int;
}
```

```php
namespace App\Domain\Action\Pipeline;

class ActionPathContext
{
    public function __construct(
        /** @var ActionPathNode[] */
        public array $nodes,
        public float $cutSheetCount,
        public ExtensionParams $params,
        /** @var ActionTreeNode[] Original flat path for reference */
        public array $originalPath,
    ) {}
}
```

```php
namespace App\Domain\Action\Pipeline;

class ExtensionParams
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
    ) {}
}
```

### 6.3 Pipeline Service

```php
namespace App\Domain\Action\Pipeline;

class ActionPathPipeline
{
    /** @var ActionPathProcessorInterface[] */
    private array $processors = [];

    public function __construct(iterable $processors)
    {
        $this->processors = iterator_to_array($processors);
        usort($this->processors, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
    }

    public function process(ActionPathContext $context): ActionPathContext
    {
        foreach ($this->processors as $processor) {
            $context = $processor->process($context);
        }
        return $context;
    }
}
```

### 6.4 Machine Interface Bővítés

```php
namespace App\Domain\Equipment\Interfaces;

interface MachineInterface
{
    // ... meglévő metódusok ...

    /**
     * Készíti el a todo array-t az adott kontextus alapján.
     */
    public function prepareTodo(TodoContext $context): array;
}
```

```php
namespace App\Domain\Equipment;

class TodoContext
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $cutSheetCount,
    ) {}
}
```

---

## 7. Implementációs Terv

### Phase 1: Infrastruktúra létrehozása ✅
- [x] `ExtensionParams` DTO létrehozása
- [x] `TodoContext` DTO létrehozása
- [x] `ActionPathContext` DTO létrehozása
- [x] `ActionPathProcessorInterface` interface létrehozása
- [x] `ActionPathPipeline` service létrehozása

### Phase 2: Todo Logic áthelyezése Machine-okba ✅
- [x] `MachineInterface::prepareTodo()` metódus hozzáadása
- [x] `Machine` base class default implementáció
- [x] `PrintingPress::prepareTodo()` implementálása
- [x] `Folder::prepareTodo()` implementálása
- [x] `StitchingMachine::prepareTodo()` implementálása
- [x] `CuttingMachine::prepareTodo()` implementálása (note: nem kellett, automatikusan beszúrt)
- [x] `CTPMachine::prepareTodo()` implementálása
- [x] `CutoutMachine::prepareTodo()` implementálása
- [x] `Splitter::prepareTodo()` implementálása (note: alapértelmezett Machine impl. elég)
- [x] `Assembler::prepareTodo()` implementálása (note: alapértelmezett Machine impl. elég)

### Phase 3: Processzorok implementálása ✅
- [x] `TodoPreparationProcessor` - todo beállítás delegálása Machine-nak
- [x] `CtpInsertionProcessor` - CTP beszúrás print elé
- [x] `VersoPrintingProcessor` - verso printing beszúrás
- [x] `CuttingInsertionProcessor` - cutting beszúrás layout változáskor
- [x] `CutSheetCountProcessor` - cutSheetCount tracking

### Phase 4: ActionTree::extend() refaktorálás ✅
- [x] Pipeline injektálása ActionTree-be
- [x] extend() egyszerűsítése pipeline hívásra
- [x] Régi kód megtartása `extendLegacy()` néven fallback-ként

### Phase 5: Tesztelés ✅
- [x] Integrációs teszt a teljes pipeline-ra (ProcessControllerTest: 9/9 teszt sikeres)
- [x] Regressziós teszt (régi és új output összehasonlítás - tesztek átmennek)
- [ ] Unit tesztek az egyes processzorokhoz (opcionális, később)

### Phase 6 (Későbbre): Action összevonás
- [ ] `ActionMergingProcessor` - kombinált gépek kezelése
- [ ] `ActionTree::calculate()` módosítása kombinált gépekhez

---

## 8. Fájl Struktúra

```
src/Domain/Action/Pipeline/
├── ActionPathProcessorInterface.php
├── ActionPathPipeline.php
├── ActionPathContext.php
├── ExtensionParams.php
├── Processor/
│   ├── TodoPreparationProcessor.php
│   ├── CtpInsertionProcessor.php
│   ├── VersoPrintingProcessor.php
│   ├── CuttingInsertionProcessor.php
│   └── CutSheetCountProcessor.php

src/Domain/Equipment/
├── TodoContext.php
├── MachineInterface.php  (bővítve prepareTodo-val)
├── Machine.php           (default prepareTodo implementáció)
├── PrintingPress.php     (prepareTodo override)
├── Folder.php            (prepareTodo override)
└── ...
```

---

## 9. Working Notes

### 2024-12-08: Architektúra döntés

- Pipeline pattern választva a komplex felelősségek miatt
- Action összevonás (pl. stitch+cut kombinált gép) későbbre halasztva
- A pipeline processzorok prioritással rendelkeznek, így a sorrend explicit

### 2024-12-08: Implementáció kész ✅

**Elkészült komponensek:**

1. **DTOs** (`src/Domain/Action/Pipeline/`):
   - `ExtensionParams.php` - paraméterek az extend művelethez
   - `ActionPathContext.php` - mutable kontextus a pipeline-hoz

2. **DTOs** (`src/Domain/Equipment/`):
   - `TodoContext.php` - kontextus a Machine::prepareTodo()-hoz

3. **Pipeline infrastruktúra** (`src/Domain/Action/Pipeline/`):
   - `ActionPathProcessorInterface.php` - processzor interface
   - `ActionPathPipeline.php` - fő pipeline service

4. **Processzorok** (`src/Domain/Action/Pipeline/Processor/`):
   - `TodoPreparationProcessor.php` (priority: 10)
   - `CtpInsertionProcessor.php` (priority: 20)
   - `VersoPrintingProcessor.php` (priority: 30)
   - `CuttingInsertionProcessor.php` (priority: 40)
   - `CutSheetCountProcessor.php` (priority: 50)

5. **Machine módosítások**:
   - `MachineInterface::prepareTodo()` hozzáadva
   - `Machine::prepareTodo()` default implementáció
   - `PrintingPress`, `Folder`, `StitchingMachine`, `CTPMachine`, `CutoutMachine` - specifikus implementációk

6. **ActionTree módosítások**:
   - Pipeline opcionális injektálás konstruktorban
   - `extend()` átírva pipeline használatra fallback-kel
   - `extendLegacy()` - régi kód @deprecated annotációval

7. **Symfony DI konfiguráció** (`config/services.yaml`):
   - Processzorok taggelve `app.action_path_processor`-ként
   - Pipeline service konfigurálva tagged iterator-ral

**Teszt eredmények:** 9/9 ProcessControllerTest sikeres

### Kombinált gép példa (későbbi feature)

```
Kliens küld: [..., stitch, cut, ...]
Opció 1: StitchingMachine → CuttingMachine (2 gép, 2 menet)
Opció 2: StitchingCuttingMachine (1 kombinált gép, 1 menet)
```

Ez a `calculate()` szinten kezelendő, nem a pipeline-ban.
