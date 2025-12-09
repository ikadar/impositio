# Refactor 4: Architektúra Elemzés

## 1. Jelenlegi Architektúra Áttekintése

### 1.1 Rétegek (Layers)

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                        │
│  Controller/ProcessController.php                           │
│  Controller/ParserController.php                            │
│  Controller/TestController.php                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER                          │
│  Application/Process/UseCase/ProcessUseCase.php             │
│  Application/Joblang/UseCase/ParseJoblangScriptUseCase.php  │
│  Service/ActionParamsExtractor.php                          │
│  Service/ActionValidator.php                                │
│  Service/PressSheetProvider.php                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     DOMAIN LAYER                             │
│  Domain/Action/*        (ActionTree, Pipeline, etc.)        │
│  Domain/Equipment/*     (Machine, PrintingPress, etc.)      │
│  Domain/Layout/*        (Calculator, GridFitting, etc.)     │
│  Domain/Sheet/*         (PressSheet, InputSheet, etc.)      │
│  Domain/Geometry/*      (Dimensions, Rectangle, etc.)       │
│  Domain/Part/*          (Part, Leaflet, etc.)               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  INFRASTRUCTURE LAYER                        │
│  Entity/*               (Doctrine entities)                 │
│  Repository/*           (Doctrine repositories)             │
│  Infrastructure/Mapper/* (Entity mappers)                   │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Jelenlegi Domain Modulok

| Modul | Felelősség | Függőségek |
|-------|------------|------------|
| **Action** | Action tree építés, kibővítés, pipeline | Equipment, Layout, Sheet, Geometry |
| **Equipment** | Gépek, gyárak, típusok | Sheet, Geometry |
| **Layout** | Grid fitting, layout számítás | Sheet, Geometry |
| **Sheet** | Press sheet, zone, input sheet | Geometry |
| **Geometry** | Méretek, koordináták, téglalapok | - |
| **Part** | Termék típusok (Leaflet, etc.) | - |
| **Joblang** | DSL feldolgozás | - |

---

## 2. Service Boundaries és Ownership

### 2.1 Azonosított Bounded Contexts

#### BC1: Production Planning (Gyártástervezés)
**Owner**: ActionTree, Calculator, Pipeline
**Felelősség**:
- Abstract production process feldolgozása
- Machine assignment plan generálása
- Action path számítás
- Layout és cutting optimalizálás

**Input**: Abstract actions + paraméterek
**Output**: Optimalizált action path-ok költség/idő becslésekkel

#### BC2: Equipment Management (Géppark Kezelés)
**Owner**: Equipment domain
**Felelősség**:
- Gép konfiguráció betöltése (YAML)
- Gép képességek (min/max sheet, colors, etc.)
- Költség és idő számítások

**Input**: Machine ID vagy Type
**Output**: Machine instance-ok

#### BC3: Layout Calculation (Impozíció)
**Owner**: Layout domain
**Felelősség**:
- Grid fitting számítás
- Zone/Sheet layout optimalizálás
- Trim/cut lines meghatározás

**Input**: Machine constraints + Zone dimensions
**Output**: GridFitting variánsok

#### BC4: Request Processing (Kérés Feldolgozás)
**Owner**: ProcessUseCase
**Felelősség**:
- HTTP request validálás
- Payload transzformáció
- Response összeállítás
- Perzisztencia

**Input**: JSON payload
**Output**: Computed action paths + metadata

### 2.2 Javasolt Service Boundaries

```
┌────────────────────────────────────────────────────────────────────┐
│                     Production Planning Service                     │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐ │
│  │  ActionTree      │  │  Layout          │  │  Pipeline        │ │
│  │  Builder         │  │  Calculator      │  │  Processors      │ │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  Input: AbstractActions, PressSheets, Zone, Params                 │
│  Output: ActionPath[] with cost/duration estimates                  │
└────────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ Equipment        │ │ Sheet            │ │ Geometry         │
│ Service          │ │ Service          │ │ Library          │
│                  │ │                  │ │                  │
│ - Load machines  │ │ - Create sheets  │ │ - Dimensions     │
│ - Filter by type │ │ - Press sheets   │ │ - Rectangles     │
│ - Calculate cost │ │ - Zones          │ │ - Alignment      │
└──────────────────┘ └──────────────────┘ └──────────────────┘
```

---

## 3. Aggregates és Domain Services

### 3.1 Aggregates

#### Aggregate: ActionPath
**Root**: ActionPathNode[]
**Entities**: ActionPathNode
**Value Objects**: GridFitting, Zone, PressSheet, Todo

```php
// Javasolt struktúra
class ActionPath {
    private string $id;
    private array $nodes; // ActionPathNode[]
    private float $totalCost;
    private float $totalDuration;
    private PressSheet $pressSheet;

    public function addNode(ActionPathNode $node): void;
    public function calculateTotals(): void;
    public function toArray(): array;
}
```

#### Aggregate: Machine
**Root**: Machine
**Entities**: -
**Value Objects**: Dimensions (min/max sheet), MachineType

```php
// Jelenlegi - jól strukturált
class Machine {
    protected string $id;
    protected MachineType $type;
    protected Dimensions $minSheetDimensions;
    protected Dimensions $maxSheetDimensions;
    // ...
}
```

#### Aggregate: GridFitting
**Root**: GridFitting
**Entities**: Tile[]
**Value Objects**: CutSheet, LayoutArea, TrimLines

### 3.2 Domain Services

#### ProductionPlanningService (Javasolt)
```php
interface ProductionPlanningServiceInterface {
    /**
     * Compute all valid action paths for given abstract actions.
     */
    public function computeActionPaths(
        array $abstractActions,
        array $pressSheets,
        Zone $zone,
        ProductionParams $params
    ): ActionPathCollection;
}
```

#### MachineAssignmentService (Javasolt)
```php
interface MachineAssignmentServiceInterface {
    /**
     * Find all capable machines for an abstract action.
     */
    public function findCapableMachines(
        AbstractAction $action,
        MachineConstraints $constraints
    ): array;
}
```

#### LayoutOptimizationService (Jelenlegi: Calculator)
```php
interface LayoutOptimizationServiceInterface {
    /**
     * Calculate all valid grid fittings for machine/sheet/zone combination.
     */
    public function calculateGridFittings(
        MachineInterface $machine,
        PressSheet $pressSheet,
        Zone $zone
    ): GridFittingCollection;
}
```

### 3.3 Repositories

#### Jelenlegi Repositories
- `ProcessRequestRepository` - Process kérések
- `ProcessPartRepository` - Process part-ok
- `ProcessActionPathRepository` - Számított action path-ok
- `JobRepository`, `PartRepository`, `JoblangScriptRepository` - Legacy DSL flow

#### Javasolt Domain Repository Interface-ek
```php
interface MachineRepositoryInterface {
    public function findById(string $id): ?Machine;
    public function findByType(MachineType $type): array;
    public function findCapableForDimensions(Dimensions $min, Dimensions $max): array;
}

interface PressSheetRepositoryInterface {
    public function findByPaperWeight(float $weight): array;
    public function findAll(): array;
}
```

---

## 4. Domain Events és Integration Events

### 4.1 Domain Events (Belső)

| Event | Trigger | Payload |
|-------|---------|---------|
| `ActionPathCalculated` | ActionTree::process() befejezése | actionPathId, cost, duration |
| `MachineSelected` | calculate() gép kiválasztás | machineId, abstractActionType |
| `GridFittingComputed` | Calculator befejezése | gridFittingId, cols, rows |
| `CuttingInserted` | Pipeline cutting beszúrás | previousActionId, cuts |

### 4.2 Integration Events (Külső rendszerek felé)

| Event | Cél | Payload Schema |
|-------|-----|----------------|
| `ProductionPlanRequested` | ERP/MES | `{ requestId, parts[], timestamp }` |
| `ProductionPlanCompleted` | ERP/MES | `{ requestId, actionPaths[], costs }` |
| `QuotationGenerated` | CRM | `{ requestId, totalCost, duration }` |

### 4.3 Message Schemas

#### ProductionPlanRequest
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "requestId": { "type": "string", "format": "uuid" },
        "timestamp": { "type": "string", "format": "date-time" },
        "parts": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "partId": { "type": "string" },
                    "actions": { "type": "array" },
                    "properties": { "type": "object" }
                },
                "required": ["partId", "actions"]
            }
        }
    },
    "required": ["requestId", "parts"]
}
```

#### ProductionPlanResponse
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "requestId": { "type": "string", "format": "uuid" },
        "status": { "enum": ["success", "partial", "failed"] },
        "parts": {
            "type": "object",
            "additionalProperties": {
                "type": "object",
                "properties": {
                    "actionPaths": {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "properties": {
                                "id": { "type": "string" },
                                "nodes": { "type": "array" },
                                "cost": { "type": "number" },
                                "duration": { "type": "number" }
                            }
                        }
                    }
                }
            }
        }
    }
}
```

---

## 5. API Draft

### 5.1 Jelenlegi API

#### POST /process
**Request:**
```json
{
    "parts": [
        {
            "partId": "PART001",
            "properties": { "copies": 1000 },
            "actions": [
                {
                    "name": "print",
                    "params": {
                        "dimensions": { "open": {...}, "closed": {...} },
                        "zone": { "width": 300, "height": 200, "gripMargin": 10 },
                        "inking": { "recto": [...], "verso": [...] }
                    }
                }
            ],
            "required_parts": []
        }
    ]
}
```

**Response:**
```json
[
    {
        "metaData": { "jobNumber": "...", "quantity": 0, "jobId": 123 },
        "parts": {
            "PART001": {
                "actionPaths": [
                    {
                        "id": "uuid",
                        "designation": "...",
                        "nodes": [...],
                        "cost": 123.45,
                        "duration": 60.5
                    }
                ]
            }
        }
    }
]
```

### 5.2 Javasolt API Változtatások

#### Verzionált API
```
POST /api/v1/production-plans
GET  /api/v1/production-plans/{id}
GET  /api/v1/production-plans/{id}/action-paths
```

#### Async Processing (Nagy számítások)
```
POST /api/v1/production-plans          → 202 Accepted, Location: /api/v1/production-plans/{id}
GET  /api/v1/production-plans/{id}     → 200 OK (status: processing|completed)
```

#### Részletes Error Response
```json
{
    "error": {
        "code": "INVALID_ZONE_DIMENSIONS",
        "message": "Zone dimensions exceed maximum press sheet size",
        "details": {
            "zone": { "width": 1500, "height": 1000 },
            "maxPressSheet": { "width": 1020, "height": 720 }
        }
    }
}
```

### 5.3 Javasolt Új Endpoints

#### GET /api/v1/machines
Elérhető gépek listázása.

#### GET /api/v1/press-sheets
Elérhető press sheet méretek listázása.

#### POST /api/v1/production-plans/{id}/optimize
Adott plan újra-optimalizálása más paraméterekkel.

---

## 6. Architektúra Javítási Javaslatok

### 6.1 Magas Prioritás

#### J1: ProcessUseCase szétbontása
**Probléma**: ProcessUseCase túl sok felelősséget tartalmaz (validálás, transzformáció, számítás, perzisztencia).

**Megoldás**:
```
ProcessUseCase
    ├── ProductionPlanningService (számítás)
    ├── ActionPathRepository (perzisztencia)
    └── ResponseTransformer (output formázás)
```

#### J2: EquipmentFactory tisztítás
**Probléma**: Switch-case minden machine típusra, kód duplikáció.

**Megoldás**: Registry pattern vagy reflection-based factory.
```php
class MachineRegistry {
    private array $factories = [];

    public function register(MachineType $type, MachineFactoryInterface $factory): void;
    public function create(string $id, array $data): MachineInterface;
}
```

#### J3: ActionTree felelősségek szétválasztása
**Probléma**: ActionTree építés, lapítás, és kibővítés egy osztályban.

**Megoldás**: Lásd refactor3.md - külön Builder, Flattener, Extender osztályok.

### 6.2 Közepes Prioritás

#### J4: Domain Events bevezetése
**Cél**: Loose coupling, audit trail, async processing lehetőség.

```php
interface DomainEventDispatcherInterface {
    public function dispatch(DomainEvent $event): void;
}

class ActionPathCalculatedEvent implements DomainEvent {
    public function __construct(
        public readonly string $actionPathId,
        public readonly float $cost,
        public readonly float $duration,
    ) {}
}
```

#### J5: CQRS alkalmazása
**Read Model**: Gyors lekérdezések előre számított adatokkal.
**Write Model**: Komplex számítások, validáció.

#### J6: Specification Pattern a gép szűréshez
```php
interface MachineSpecification {
    public function isSatisfiedBy(MachineInterface $machine): bool;
}

class ColorCapableSpecification implements MachineSpecification {
    public function __construct(private int $requiredColors) {}

    public function isSatisfiedBy(MachineInterface $machine): bool {
        return $machine->getNumberOfColors() >= $this->requiredColors;
    }
}
```

### 6.3 Alacsony Prioritás

#### J7: API Versioning
Bevezetni `/api/v1/` prefix-et, OpenAPI dokumentáció.

#### J8: Hexagonal Architecture
Port/Adapter pattern a külső függőségekhez (YAML config, Database).

---

## 7. Összefoglalás

### Erősségek
- Tiszta domain layer elkülönítés
- Jól definiált value object-ek (Dimensions, Position, etc.)
- Pipeline pattern sikeres bevezetése
- Interface-alapú tervezés

### Gyengeségek
- God class-ok (ActionTree, ProcessUseCase)
- Hiányzó domain events
- Túl sok felelősség az Application layer-ben
- Nincs API versioning

### Prioritások
1. **Sürgős**: ProcessUseCase és ActionTree refaktorálás
2. **Fontos**: Domain events, CQRS előkészítés
3. **Később**: API versioning, Hexagonal architecture
