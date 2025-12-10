# Domain Model: DSL + Impositio

## Státusz

**v0.6** - Architektúrális döntés elfogadva (2024-12-10)

## Architektúrális Döntés

**ELFOGADOTT:** A DSL és Impositio kizárólag a Part → Action Path feldolgozásra koncentrál.

```
┌─────────────────────────────────────────────────────────────┐
│                         CPQ                                 │
│  (Customer, Order, OrderItem, Product, ganging logic)       │
│                                                             │
│  Felelősség:                                                │
│  - Üzleti logika (megrendelések, ügyfelek)                  │
│  - Ganging algoritmus (mely Part-ok kerüljenek egy Pose-ba) │
│  - Order → Part mapping                                     │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           │ Part + Action lista
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    DSL + Impositio                          │
│  (Part → Action Path → Cost/Time calculation)               │
│                                                             │
│  Felelősség:                                                │
│  - Action path generálás                                    │
│  - Layout számítás (grid fitting)                           │
│  - Költség és idő kalkuláció                                │
│  - Gép szűrés és kiválasztás                                │
│                                                             │
│  NEM foglalkozik:                                           │
│  - Customer, Order, OrderItem                               │
│  - Product (csak Part-ot ismer)                             │
│  - Ganging döntések (csak végrehajtja)                      │
└─────────────────────────────────────────────────────────────┘
```

### Ganging Támogatás

Két lehetséges megközelítés:

| Megközelítés | Leírás | Impositio támogatás |
|--------------|--------|---------------------|
| **(a) 1 Pose = N Parts** | Több Part egy Pose-ban, final cut választja szét | ✅ **Már támogatott** |
| (b) N Poses / Sheet | Különböző méretű Pose-ok egy íven | ❌ Nem támogatott |

**Az (a) megközelítés választva** - az Impositio változtatás nélkül támogatja a ganging-ot!

---

## Célkitűzés

Ez a dokumentum rögzíti az **Impositio** domain modellt:
1. Építkezik az "Imposition spec - V2.1.md" specifikációra
2. Tisztázza a **Part** (gyártási egység) feldolgozását
3. Dokumentálja a Pose és Part kapcsolatot
4. Definiálja az Impositio felelősségi körét

---

## Impositio Scope

| Funkció | Státusz |
|---------|---------|
| Egyetlen Part feldolgozása | ✅ Implementálva |
| Action path generálás | ✅ Implementálva |
| Költség/idő számítás | ✅ Implementálva |
| Layout/Grid fitting | ✅ Implementálva |
| Gép szűrés (inking alapján) | ✅ Implementálva |
| **1 Pose = N Parts** (ganging) | ✅ **Támogatott** |

### Nem az Impositio Felelőssége

| Funkció | Felelős |
|---------|---------|
| Customer kezelés | CPQ |
| Order/OrderItem | CPQ |
| Product definíció | CPQ |
| Ganging algoritmus | CPQ |
| Quantity tracking | CPQ |

---

## Alapfogalom: Part (Gyártási Egység)

### Definíció

A **Part** az Impositio központi fogalma - ez az a gyártási egység, amit a rendszer feldolgoz.

| Fogalom | Scope | Leírás |
|---------|-------|--------|
| **Part** | Impositio | Gyártási egység, amit a gépen feldolgozunk |
| Product | CPQ | Végtermék (Part-okból áll) - **NEM az Impositio felelőssége** |

### Part Attribútumok (Impositio)

```
Part (Gyártási Egység)
├── partId: string                    // Egyedi azonosító
├── dimensions
│   ├── open: { width, height }       // Nyitott (hajtogatás előtt)
│   └── closed: { width, height }     // Zárt (hajtogatás után)
├── quantity: int                     // Darabszám
├── paperWeight: float                // Papírsúly (g/m²)
└── actions: AbstractAction[]         // Gyártási műveletek
```

### Példa: Part Input az Impositio-ba

```json
{
  "parts": [{
    "partId": "BROCHURE-001",
    "properties": { "copies": 1000 },
    "actions": [
      {
        "name": "print",
        "params": {
          "dimensions": { "open": {...}, "closed": {...} },
          "inking": { "recto": ["C","M","Y","K"], "verso": ["K"] },
          "paper": { "weight": 135 }
        }
      },
      { "name": "fold", "params": {...} },
      { "name": "cut", "params": {} }
    ]
  }]
}
```

**Megjegyzés:** Az Impositio nem tudja és nem is érdekli, hogy ez a Part egy önálló termék-e (pl. szórólap) vagy egy összetett termék része (pl. könyv borítója). Ezt a CPQ kezeli.

---

## Fogalmak Áttekintése

Az alábbi ábra összefoglalja az Impositio hierarchiát:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    GYÁRTÁSI HIERARCHIA (IMPOSITIO)                          │
│                    (Hogyan gyártjuk le a Part-ot?)                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Part (input a CPQ-tól)                                                    │
│         │                                                                   │
│         ▼                                                                   │
│   Abstract Production Process (action lista)                                │
│         │                                                                   │
│         ▼                                                                   │
│   Machine Assignment Plan (gép hozzárendelés)                               │
│         │                                                                   │
│         ▼                                                                   │
│   Action Backtrace Tree                                                     │
│         │                                                                   │
│         ▼                                                                   │
│   Action Path (végrehajtható gyártási útvonal)                              │
│         │                                                                   │
│         ├── Action 1 (machine, enrichment, layout)                          │
│         ├── Action 2 (machine, enrichment, layout)                          │
│         └── Action N                                                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                           │
                                           │ layout szintek
                                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    LAYOUT HIERARCHIA                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Press Sheet (eredeti ív)                                                  │
│         │                                                                   │
│         ├── Sheet Layout (hogyan osztjuk fel cut sheet-ekre)                │
│         │                                                                   │
│         ▼                                                                   │
│   Cut Sheet (vágott ív)                                                     │
│         │                                                                   │
│         ├── Zone Layout (hogyan helyezzük el a zone-okat)                   │
│         │                                                                   │
│         ▼                                                                   │
│   Zone (munkaterület)                                                       │
│         │                                                                   │
│         ├── Pose Layout (hogyan helyezzük el a pose-okat)                   │
│         │                                                                   │
│         ▼                                                                   │
│   Pose (gyártási egység helye, bleed-del)                                   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## I. Spec-ből Átvett Fogalmak

### A) Print Carriers (Nyomathordozók)

| Fogalom | Angol | Definíció (spec alapján) |
|---------|-------|--------------------------|
| **Press Sheet** | Press Sheet | Az eredeti, teljes méretű, vágatlan ív |
| **Parent Sheet** | Parent Sheet | Az ív, amit vágni/trimmelni fogunk - lehet press sheet vagy cut sheet |
| **Cut Sheet** | Cut Sheet | Egy parent sheet-ből kivágott darab |
| **Trimmed Sheet** | Trimmed Sheet | Szélvágott ív (speciális eset) |

### B) Layout Fogalmak

| Fogalom | Angol | Definíció (spec alapján) |
|---------|-------|--------------------------|
| **Pose** | Pose | Az impozíció alapegysége - lásd részletes leírás alább |
| **Zone** | Zone | Egy terület, amely pontosan egy pose layout-ot tartalmaz |
| **Usable Area** | Usable Area | Az ív azon része, ami a grip margin levonása után marad |
| **Pose Layout** | Pose Layout | Pose-ok exhaustive grid fitting elrendezése egy zone-on belül |
| **Zone Layout** | Zone Layout | Zone-ok exhaustive grid fitting elrendezése egy sheet usable area-ján |
| **Sheet Layout** | Sheet Layout | Cut sheet-ek exhaustive grid fitting elrendezése egy parent sheet-en |

#### Pose - Részletes Magyarázat

A **Pose** az impozíció (kilövés) alapegysége. Ez az az egység, amelynek több példányát elrendezzük az íven.

**FONTOS:** A Pose NEM feltétlenül egyezik meg egy Part-tal!

##### Egyszerűsített Értelmezés (Jelenlegi Iteráció)

```
Pose = 1 Part pozíciója az íven (+ bleed)
```

A jelenlegi implementáció ezt az egyszerűsített modellt használja.

##### Valódi Értelmezés (Teljes Modell)

```
Pose = Az impozíció során változatlan egység
     = Tartalmazhat 1 VAGY TÖBB Part-ot
     = A VÉGSŐ OUTPUT határozza meg, mi lesz Part
```

##### Példa: Két Part Egy Pose-ban

Két különböző szórólap (A és B) együtt nyomtatva, majd a végén kettévágva:

```
Press Sheet
┌──────────────────────────────────────────────────┐
│                                                  │
│   ┌────────────────────────────────────────┐     │
│   │              POSE 1                    │     │
│   │   ┌─────────────┬─────────────┐       │     │
│   │   │  Szórólap A │  Szórólap B │       │     │
│   │   │   (Part 1)  │   (Part 2)  │       │     │
│   │   └─────────────┴─────────────┘       │     │
│   └────────────────────────────────────────┘     │
│                                                  │
│   ┌────────────────────────────────────────┐     │
│   │              POSE 2                    │     │
│   │   ┌─────────────┬─────────────┐       │     │
│   │   │  Szórólap A │  Szórólap B │       │     │
│   │   └─────────────┴─────────────┘       │     │
│   └────────────────────────────────────────┘     │
│                                                  │
└──────────────────────────────────────────────────┘

Final cut (végső vágás):
  Pose kettévágása → Part A + Part B külön-külön
```

##### A Végső Output Elve

A joblang DSL-ben (lásd: `joblang.grammar`):
- **Input**: ami bemegy a műveletbe (`on $object`)
- **Output**: ami kijön a műveletből (`as $object`)
- **Split**: egy objektum több objektumra bontása (`split ... into $a, $b`)

**A Part-ot a végső output határozza meg, NEM az input!**

```
Gyártási folyamat:

  Press Sheet (input)
       │
       ▼
  ┌─────────────────┐
  │  print (Pose)   │  ← A Pose az impozíció egysége
  └─────────────────┘
       │
       ▼
  ┌─────────────────┐
  │  fold, etc.     │  ← A Pose változatlan marad
  └─────────────────┘
       │
       ▼
  ┌─────────────────┐
  │  final cut      │  ← A Pose szétválik
  └─────────────────┘
       │
       ├──→ Output A  →  Part A  →  Product X
       │
       └──→ Output B  →  Part B  →  Product Y
```

##### Következmények

1. **A Pose az impozíció szintjén változatlan** - nincs mid-process cut a pose-on belül
2. **A Part a végső output** - amit a final cut után kapunk
3. **Egy Pose tartalmazhat több Part-ot** - ha azokat együtt nyomtatjuk, majd a végén vágjuk szét

##### Jövőbeli Komplexitás (Nem Ebben az Iterációban)

Elméletileg egy sheet-re több különböző méretű/típusú pose is felkerülhet:

```
Press Sheet (JÖVŐBELI - komplex eset)
┌──────────────────────────────────────────────────┐
│  ┌──────────────┐  ┌──────────────┐              │
│  │   POSE A     │  │   POSE A     │              │
│  │  (nagy)      │  │  (nagy)      │              │
│  └──────────────┘  └──────────────┘              │
│                                                  │
│  ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐        │
│  │POSE B │ │POSE B │ │POSE B │ │POSE B │        │
│  │(kicsi)│ │(kicsi)│ │(kicsi)│ │(kicsi)│        │
│  └───────┘ └───────┘ └───────┘ └───────┘        │
└──────────────────────────────────────────────────┘
```

**Ez az iteráció ezt NEM kezeli** - egy sheet-en azonos pose-ok vannak.

### C) Cutting Fogalmak

| Fogalom | Angol | Definíció (spec alapján) |
|---------|-------|--------------------------|
| **Mid-process Cut** | Mid-process Cut | Vágás action-ök között (gép input méret miatt) |
| **Final Product Cut** | Final Product Cut | Utolsó vágás a végtermék méretére |
| **Cut Spacing Buffer** | Cut Spacing Buffer | Távolság a cut sheet-ek között (alapért: 20mm) |
| **Bleed** | Bleed | Kifutó a termék körül (alapért: 5mm) |
| **Grip Margin** | Grip Margin | A gép által megfogott terület |

### D) Production Model Fogalmak

| Fogalom | Angol | Definíció (spec alapján) |
|---------|-------|--------------------------|
| **Abstract Production Process** | Abstract Production Process | A szükséges abstract action-ök sorozata |
| **Abstract Action** | Abstract Action | Egy művelet típus (print, fold, cut, bind...) |
| **Machine Type** | Machine Type | Gépek absztrakt kategóriája |
| **Machine** | Machine | Konkrét fizikai gép |
| **Machine Assignment Plan** | Machine Assignment Plan | Abstract action-ök hozzárendelése konkrét gépekhez |
| **Action** | Action | Konkrét művelet végrehajtás egy gépen |
| **Action Path** | Action Path | Action-ök sorozata (végrehajtható gyártási útvonal) |
| **Action Backtrace Tree** | Action Backtrace Tree | Lehetséges action path-ok fája |
| **Machine Run** | Machine Run | Egy gép egyszeri működtetése egy batch-en |
| **Batch** | Batch | Egy machine run-ban feldolgozott ívek csoportja |

### E) Execution Phases

| Fogalom | Angol | Definíció (spec alapján) |
|---------|-------|--------------------------|
| **Pre-cut Phase** | Pre-cut Phase | Minden layout-releváns művelet a final product cut előtt |
| **Post-cut Phase** | Post-cut Phase | Műveletek a final product cut után (finishing) |

---

## II. Abstract Action

### Definíció

Egy **Abstract Action** egy gyártási művelet típusa, amely egy Part-hoz tartozik.

### Action Típusok

| Action | Machine Type | Leírás |
|--------|--------------|--------|
| `print` | Printing Press | Nyomtatás |
| `cut` | Cutting Machine | Vágás |
| `fold` | Folder | Hajtogatás |
| `stitch` | Stitching Machine | Tűzés |
| `bind` | Binding Machine | Kötés |
| `laminate` | Laminator | Laminálás |
| `die-cut` | Die Cutter | Stancolás |

### Print Action Paraméterei

A **print** action speciális, mert tartalmazza az inking információt:

```
PrintActionParams
├── inking
│   ├── recto: string[]    // Pl: ['cyan', 'magenta', 'yellow', 'black']
│   └── verso: string[]    // Pl: ['black'] vagy []
```

**Fontos:** Az `inking` action-szintű paraméter, nem part-szintű!

---

## III. Kapcsolat a Jelenlegi Kóddal

| Spec Fogalom | Jelenlegi Kód | Megjegyzés |
|--------------|---------------|------------|
| Part | `PartPayload` | Input DTO |
| Part.quantity | `PartPayload.properties.copies` | A Part-ban van |
| Abstract Action | `PayloadAction` | Action definíció |
| Abstract Production Process | `ProcessAbstractAction[]` | Action lista |
| Machine | `MachineInterface` | Gép implementáció |
| Machine Assignment Plan | Implicit (ActionTree) | Gép kiválasztás |
| Action Path | `ActionPath` | Gyártási útvonal |
| Action (konkrét) | `ActionPathNode` | Egy lépés a path-ban |
| Zone | `InputSheetInterface` (zone) | Munkaterület |
| Press Sheet | `PressSheetInterface` | Nyomtatott ív |
| Pose Layout | `GridFittingInterface` | Elrendezés |
| Enrichment | `ActionEnrichmentInterface` | Költség/idő adatok |

### Jelenlegi vs. Spec Elnevezések

| Jelenlegi Kód | Spec Szerinti Név | Javaslat |
|---------------|-------------------|----------|
| `JobContext` | - | → `PartProductionContext` |
| `numberOfCopies` | quantity | Maradhat |
| `inking` (context-ben) | - | → `PrintActionParams.inking` |

---

## IV. Eldöntött Kérdések (Impositio)

### 1. ~~Inking helye~~ → ELDÖNTVE

**Jelenlegi:** Context-ben (JobContext)
**Cél:** Action-ben (PrintActionParams)

**Státusz:** Refactoring fázis 1

---

### 2. ~~JobContext elnevezés~~ → ELDÖNTVE

**Probléma:** `JobContext` félrevezető név - valójában part-szintű adatokat tartalmaz
**Megoldás:** Átnevezés → `PartProductionContext`

**Státusz:** Refactoring fázis 2

---

## V. Nem az Impositio Felelőssége (CPQ Scope)

A következő kérdések a CPQ-ban fognak megoldódni:

| Kérdés | Felelős |
|--------|---------|
| Ganging algoritmus (mely Part-ok kerüljenek együtt) | CPQ |
| Quantity tracking (rendelt vs. gyártott vs. waste) | CPQ |
| Part újrafelhasználás (katalógus elem vs. egyedi) | CPQ |
| Product → Part mapping | CPQ |
| Order/OrderItem kezelés | CPQ |

---

## VI. Következő Lépések (Impositio Refactoring)

A refactoring terv: `docs/refactor-domain-hierarchy.md`

| Fázis | Feladat | Státusz |
|-------|---------|---------|
| 0 | Regressziós tesztek | ⏳ Következő |
| 1 | Inking → PrintActionParams | ⏳ Várakozik |
| 2 | JobContext → PartProductionContext | ⏳ Várakozik |
| 3 | TreeBuildContext konszolidálása | ⏳ Várakozik |
| 4 | Dokumentáció frissítése | ⏳ Várakozik |

---

## Glosszárium (Impositio Scope)

| Magyar | Angol | Definíció |
|--------|-------|-----------|
| **Gyártási egység** | Part | Az Impositio központi inputja - amit feldolgozunk |
| **Pose** | Pose | Az impozíció alapegysége - tartalmazhat N Part-ot (ganging) |
| Ív | Sheet | Általános: press sheet vagy cut sheet |
| Press ív | Press Sheet | Eredeti, vágatlan ív |
| Vágott ív | Cut Sheet | Press sheet-ből kivágott darab |
| Munkaterület | Zone | Terület, ami egy pose layout-ot tartalmaz |
| Elrendezés | Layout | Pose/Zone/Sheet elhelyezése |
| Kifutó | Bleed | Margó a termék körül (5mm) |
| Fogómargó | Grip Margin | Gép által megfogott terület |
| Köztes vágás | Mid-process Cut | Vágás action-ök között |
| Végtermék vágás | Final Product Cut | Utolsó vágás - itt válik szét a Pose Part-okra |
| Gyártási útvonal | Action Path | Action-ök sorozata |
| Művelet | Action | Gyártási lépés |
| Input | Input | Ami bemegy egy műveletbe (joblang: `on $object`) |
| Output | Output | Ami kijön egy műveletből (joblang: `as $object`) |

**CPQ fogalmak (nem Impositio):** Product, Order, OrderItem, Customer, Ganging algoritmus

---

## Verziótörténet

| Verzió | Dátum | Változás |
|--------|-------|----------|
| 0.1 | 2024-12-10 | Kezdeti draft |
| 0.2 | 2024-12-10 | Kettős hierarchia, ganging támogatás |
| 0.3 | 2024-12-10 | Spec integrálása, fogalmak tisztázása |
| 0.4 | 2024-12-10 | Product vs Part szétválasztás |
| 0.5 | 2024-12-10 | Pose részletes tisztázása, végső output elv |
| **0.6** | **2024-12-10** | **Architektúrális döntés: DSL/Impositio scope tisztázása, CPQ felelősségek kiszervezése**
