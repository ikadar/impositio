# Refactor 3: ActionTree Kódminőség Javítás

## 1. Kód Elemzés

### 1.1 ActionTree osztály áttekintése

Az `ActionTree` osztály felelős:
1. **calculateTree()**: Action backtrace tree felépítése abstract action-ökből
2. **flattenTree()**: Fa lapítása action path-okká
3. **extend()**: Action path kibővítése (CTP, verso, cutting beszúrás)
4. **process()**: Teljes workflow orchestrálása

### 1.2 Specifikációnak való megfelelés

| Spec fogalom | Implementáció | Megfelelés |
|--------------|---------------|------------|
| Abstract production process | `$abstractActions` tömb | ✅ |
| Machine assignment plan | `calculate()` - gép iteráció | ✅ |
| Action backtrace tree | `ActionTreeNode` fa struktúra | ✅ |
| Action path | `flattenTree()` eredménye | ✅ |
| Machine implication rule (CTP) | `extend()` / pipeline | ✅ |
| Mid-process cut | `extend()` / pipeline | ✅ |
| Zone layout | `Calculator::calculateGridFittings()` | ✅ |
| Exhaustive grid fitting | `Packer::calculateExhaustiveGridFitting()` | ✅ |

### 1.3 Azonosított problémák

#### P1: Túl sok felelősség egy osztályban (God Class)
Az `ActionTree` osztály:
- Fa építés (`calculate`)
- Fa lapítás (`flatten`)
- Path kibővítés (`extend`, `extendLegacy`)
- Workflow orchestráció (`process`, `extendPaths`)
- Állapot tárolás (sok property setter/getter)

**Javasolt megoldás**: Single Responsibility Principle alkalmazása - külön osztályok a különböző felelősségeknek.

#### P2: Mutable state és setter injection
```php
protected DimensionsInterface $openPoseDimensions;
protected float $numberOfCopies;
// ... sok más property

$this->setOpenPoseDimensions($openPoseDimensions);
$this->setNumberOfCopies($numberOfCopies);
// ... majd később használat
```

Ez nehezíti a tesztelést és a kód követését.

**Javasolt megoldás**: Immutable context objektumok, paraméter átadás metódus argumentumként.

#### P3: Kikommentezett kód
```php
//        array $pressSheets,
//        $this->setRoot($this->calculate($abstractActions, $pressSheets, $zone, $prevNodes));
//        foreach ($pressSheets as $pressSheet) {
```

Sok kikommentezett kód nehezíti az olvashatóságot.

**Javasolt megoldás**: Törlés vagy áthelyezés verziókezelésbe.

#### P4: Magic string-ek a machine type ellenőrzésekhez
```php
if ($node->getMachine()->getType()->value === "printing press") { ... }
if ($node->getMachine()->getType()->value === "folder") { ... }
```

**Javasolt megoldás**: `MachineType` enum konstansok használata.

#### P5: Üres interface-ek
```php
interface ActionTreeInterface { }
interface ActionPathNodeInterface { }
interface ActionTreeNodeInterface { }
```

Ezek nem adnak hozzá semmit a típusbiztonsághoz.

**Javasolt megoldás**: Interface-ek feltöltése releváns metódus signatúrákkal vagy törlésük.

#### P6: calculate() metódus bonyolultsága
A `calculate()` metódus (~95 sor):
- Rekurzív fa építés
- Gép szűrés (inking alapján)
- Folder speciális kezelés
- Grid fitting iteráció

**Javasolt megoldás**: Kisebb, tesztelhető metódusokra bontás.

#### P7: extendLegacy() duplikáció
Az `extendLegacy()` és a pipeline processzorok között kód duplikáció van. A legacy kód ~190 sor, nehezen karbantartható.

**Javasolt megoldás**: Legacy kód eltávolítása ha a pipeline stabil.

#### P8: Hiányzó típus annotációk
```php
public function process(
    $abstractActions,  // nincs típus
    $pressSheets,      // nincs típus
    $zone,             // nincs típus
    ...
)
```

**Javasolt megoldás**: Strict typing bevezetése.

#### P9: flattenTree() array_reverse logika
A `flattenTree()` megfordítja a path-ot, de a processzorok fordított sorrendben dolgoznak. Ez zavart okoz.

**Javasolt megoldás**: Egyértelmű dokumentáció VAGY a sorrend egységesítése.

---

## 2. Refaktor Terv

### Phase 1: Előkészítés - Tesztek írása ⬜
**Cél**: Biztosítani, hogy a refaktor nem töri el a működést.

- [ ] Unit tesztek a `calculate()` metódushoz
- [ ] Unit tesztek a `flattenTree()` metódushoz
- [ ] Unit tesztek az `extend()` metódushoz
- [ ] Integration tesztek a teljes `process()` workflow-hoz
- [ ] Snapshot tesztek: legacy vs pipeline output összehasonlítás

### Phase 2: Kód tisztítás ⬜
**Cél**: Olvashatóság javítása változtatás nélkül.

- [ ] Kikommentezett kód törlése
- [ ] Magic string-ek cseréje MachineType enum konstansokra
- [ ] Típus annotációk hozzáadása
- [ ] PHPDoc kommentek frissítése

### Phase 3: Interface-ek feltöltése ⬜
**Cél**: Típusbiztonság javítása.

- [ ] `ActionTreeInterface` metódusok definiálása
- [ ] `ActionPathNodeInterface` metódusok definiálása
- [ ] `ActionTreeNodeInterface` metódusok definiálása

### Phase 4: Felelősségek szétválasztása ⬜
**Cél**: Single Responsibility Principle.

- [ ] `ActionTreeBuilder` osztály kiemelése (`calculate`, `calculateTree`)
- [ ] `ActionTreeFlattener` osztály kiemelése (`flatten`, `flattenTree`)
- [ ] `ActionPathExtender` - ez már a Pipeline
- [ ] `ActionTreeProcessor` orchestráció (`process`, `extendPaths`)

### Phase 5: Mutable state eliminálása ⬜
**Cél**: Tisztább adatáramlás.

- [ ] `TreeBuildContext` DTO bevezetése a sok property helyett
- [ ] Setter-ek eltávolítása, constructor injection
- [ ] Metódus paraméterek használata instance property-k helyett

### Phase 6: Legacy kód eltávolítása ⬜
**Cél**: Kód duplikáció megszüntetése.

- [ ] `extendLegacy()` törlése ha pipeline stabil
- [ ] Pipeline-only működés

---

## 3. Részletes Elemzés

### 3.1 calculate() metódus

```php
protected function calculate(
    array $abstractActions,
    PressSheetInterface $pressSheet,
    InputSheetInterface $zone,
    array $prevNodes = [],
    array $inking = [],
): array
```

**Problémák:**
1. `$prevNodes` paraméter soha nincs használva (mindig üres tömb)
2. `$inking` paraméter duplikált - egyszer instance property, egyszer paraméter
3. Folder kezelés speciális eset beágyazva a fő logikába

**Javasolt struktúra:**
```php
class ActionTreeBuilder {
    public function build(TreeBuildContext $context): array {
        $abstractAction = $context->shiftNextAction();
        if ($abstractAction === null) {
            return [];
        }

        $machines = $this->filterCapableMachines($abstractAction, $context);
        return $this->buildNodesForMachines($machines, $context);
    }

    private function filterCapableMachines(...): array { ... }
    private function buildNodesForMachines(...): array { ... }
    private function handleFolderSpecialCase(...): Action { ... }
}
```

### 3.2 flattenTree() / flatten() metódusok

**Jelenlegi logika:**
1. `flatten()` rekurzívan bejárja a fát root → leaves irányban
2. Path-okat gyűjt: `[root, child, grandchild, ...]`
3. `flattenTree()` megfordítja: `[grandchild, child, root]` (backtrace order)

**Probléma:** A "backtrace order" név ellenére a flatten ELŐRE halad, majd megfordítja. Ez zavaró.

**Javasolt megoldás:** Dokumentáció javítása vagy a logika átnevezése.

### 3.3 Calculator osztály

A `Calculator` osztály jól strukturált, de:
- `placeOnSheet()` metódus túl sok mindent csinál
- `calculateLayoutArea()` komplex logikával

**Nem sürgős** - működik, de későbbi refaktorálás célpontja lehet.

---

## 4. Prioritások

| Prioritás | Feladat | Indoklás |
|-----------|---------|----------|
| 🔴 Magas | Phase 1: Tesztek | Biztonságos refaktorálás alapja |
| 🔴 Magas | Phase 2: Kód tisztítás | Gyors, alacsony kockázatú javulás |
| 🟡 Közepes | Phase 3: Interface-ek | Típusbiztonság |
| 🟡 Közepes | Phase 6: Legacy törlés | Duplikáció megszüntetése |
| 🟢 Alacsony | Phase 4-5: Refaktor | Nagyobb változtatások, több kockázat |

---

## 5. Következő lépések

1. **Először**: Unit és integration tesztek írása a jelenlegi működésre
2. **Majd**: Magic string-ek és típusok javítása (alacsony kockázat)
3. **Később**: Strukturális refaktorálás ha szükséges

---

## 6. Megjegyzések

### Mi működik jól:
- A Pipeline pattern bevezetése sikeres volt
- A spec szerinti fogalmak jól leképezettek a kódban
- Az integration tesztek lefedik a fő use case-eket

### Mi igényel figyelmet:
- A backtrace/forward sorrend következetes kezelése
- A mutable state csökkentése
- A felelősségek tisztább szétválasztása
