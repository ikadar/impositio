# Legacy part processing – refactor brief

> **Note for Claude Code**  
> This document will be the **single source of truth** for the refactor.  
> Add comments / code **only** for the behaviour described here. If something is unclear or missing, assume it will be specified later and keep the implementation minimal / decoupled.

---

## 0. Overall goal

The current PHP backend receives a POST request that defines one or more **parts** to be produced in a print manufacturing workflow.

- **Current behaviour:** based on the **part type**, the backend has **hardcoded logic** that determines which manufacturing steps (**actions**) are required for that part.
- **New behaviour:** the backend must **no longer infer actions from the part type**.  
  Instead, the **POST request will explicitly contain the list of actions** that belong to each part, and the backend must work **directly from these actions**.

The goal of this refactor is to migrate from *"part type → hardcoded actions"* to *"request payload → explicit actions"*.

---

## 1. Scope and relevant files

> **TODO (filled by me before asking for code changes):**  
> List all frontend and backend files that are relevant for this refactor.
>
> - Backend entry point(s) for the POST request handling the parts
> - PHP services / helpers that currently compute actions from part type
> - Any JS code that builds the current payload
>
> - `src/Controller/ParserController.php` – POST endpoint that currently receives **Joblang DSL source**, invokes `ParseJoblangScriptUseCase::execute()`, persists the parsed script and returns a script ID.
> - `src/Application/Joblang/UseCase/ParseJoblangScript/ParseJoblangScriptUseCase.php` – Orchestrates parsing and persistence of `JoblangScript`, `JoblangLine`, `Job`, and `Part` entities using `JoblangService`.
> - `src/Service/JoblangServiceInterface.php` / `src/Service/JoblangService.php` – Implements Joblang parsing and persistence logic.
> - `src/Entity/JoblangScript.php` – Stores the original DSL script and links to parsed lines.
> - `src/Entity/JoblangLine.php` – Represents a parsed DSL line (`source` + `parsed` structure) and links to a `Job`.
> - `src/Entity/Job.php` – Represents a print job, created from parsed DSL data, containing one or more `Part` entities.
> - `src/Entity/Part.php` – Represents a manufacturable part; contains a `json` field derived from DSL parsing.
> - `src/Entity/ActionPath.php` – Stores computed manufacturing action paths for a `Part` as structured JSON.
> - `src/Controller/TestController.php::getTest()` – Loads a stored script and job, derives abstract actions, computes action paths using the action tree, and persists `ActionPath` entities.
> - `src/Infrastructure/Mapper/JobMapper.php` – Maps persistence entities (`Job`, `Part`) to domain objects.
> - `src/Application/Joblang/ResponseTransformer/JoblangScriptParseResponseTransformer.php` – Transforms parsed script + domain job into the structured payload consumed by `TestController`.

---

## 2. Current behaviour (LEGACY)

This section will describe how the system behaves **before** the refactor.

### 2.1 Current request payload (OLD)

The current `/parse` endpoint receives a raw **Joblang DSL script** as plain text (not JSON):

```
[jobNumber=JOB001][quantity=1000][
  [
    type=leaflet
    properties[dimensions]=420x296/210x148
    properties[medium[weight]]=116g
    properties[medium[inking[recto]]]=cyan,magenta,yellow
    properties[medium[inking[verso]]]=magenta,yellow
  ]
]
```

After parsing, the internal representation includes:

```json
{
  "type": "leaflet",
  "partId": "PART0001",
  "properties": {
    "dimensions": "420x296/210x148",
    "medium": {
      "weight": "116g",
      "inking": {
        "recto": ["cyan", "magenta", "yellow"],
        "verso": ["magenta", "yellow"]
      }
    }
  },
  "required_parts": []
}
```

**Key observation:** The `actions` field does **not** exist in the payload – it is derived from `type` via hardcoded logic.

### 2.2 Backend logic based on part type

The mapping from **part type → actions** is **hardcoded in the domain Part subclass constructors**:

**`src/Domain/Part/Leaflet.php`:**
```php
public function __construct($partData)
{
    self::$type = PartType::Leaflet;
    $this->actions = [
        ["type" =>  "stitching"],
        ["type" =>  "folding"],
        ["type" =>  "printing"]
    ];
    parent::__construct($partData);
}
```

**`src/Domain/Part/Feuillet.php`:**
```php
public function __construct($partData)
{
    self::$type = PartType::Feuillet;
    $this->actions = [
        ["type" =>  "folding"],
        ["type" =>  "printing"]
    ];
    parent::__construct($partData);
}
```

**Flow:**
1. `PartFactory::create($partData)` reads `$partData["type"]`
2. Based on the type, it instantiates `Leaflet` or `Feuillet`
3. The constructor sets the hardcoded `$this->actions` array
4. `JoblangScriptParseResponseTransformer::toArray()` calls `$part->getActions()` to include actions in the payload

### 2.3 Action path generation (current flow)

- Action paths are **not** generated during parsing. They are generated later, via `TestController::getTest(string $scriptId)`.

**High-level flow:**
- `ParserController` receives a Joblang DSL script, calls `ParseJoblangScriptUseCase::execute()`, persists the script and related entities, and returns a `scriptId`.
- `TestController::getTest($scriptId)` is then used to:
  - Load the stored `JoblangScript` with its lines and parts.
  - Map persistence entities to a domain `Job` using `JobMapper::toDomain()`.
  - Build a structured response model using `JoblangScriptParseResponseTransformer`.

**Part processing (`processPayload` / `processPartPayload`):**
- For each part:
  - Basic attributes are read (part id, copies, medium, size, zone, required parts).
  - An action list is constructed by iterating over `partPayload['actions']` and instantiating `AbstractAction` objects from action type names.
  - Size strings are parsed into open/closed `Dimensions`.
  - Colour count, paper weight, inking, zone sheets, and press sheet inputs are computed.
- The result is a normalised internal part representation containing:
  - `abstractActions`
  - pose dimensions
  - production parameters (copies, colours, weight, inking)
  - zone and sheet definitions

**Action tree execution and persistence:**
- The normalised part data is fed into the `ActionTreeInterface`, producing one or more candidate action paths.
- For each selected path:
  - Metrics (tiles, cost, duration, etc.) are calculated.
  - An `ActionPath` entity is created, linked to the corresponding `Part`, and the computed path is stored in its `json` field.
- `ActionPath` entities are persisted and later returned to the client as part of the test response.

**Key conclusion:**
- Although `processPartPayload()` operates on an `actions` list, these actions are **indirectly derived from the Joblang DSL** via parsing and transformation.
- The backend currently remains **DSL-centric**; clients do **not** provide explicit AST/CST or action definitions.
- The upcoming refactor will replace this implicit DSL-derived action generation with **explicit, client-supplied actions in the POST payload**, eliminating the need for DSL parsing.

---

## 3. Target behaviour (NEW)

This section will define how the system must behave **after** the refactor.

### 3.1 New request payload (NEW)

**Endpoint:** `POST /process`
**Controller:** `ProcessController`
**Content-Type:** `application/json`

**Example payload:**

```json
{
  "parts": [
    {
      "partId": "PART0001",
      "properties": {},
      "actions": [
        {
          "name": "print",
          "params": {
            "paper": {
              "type": "dimension",
              "dimension": {
                "length": 200,
                "width": 300,
                "unit": "mm"
              }
            },
            "bleed": {
              "size": 6,
              "unit": "mm"
            },
            "inking": {
              "type": "recto/verso",
              "colorList": "blue, green, red"
            },
            "varnish": {
              "type": "recto"
            }
          }
        },
        {
          "name": "cut",
          "params": {}
        }
      ],
      "required_parts": []
    }
  ]
}
```

**Breaking changes compared to OLD format:**

| Aspect | OLD | NEW |
|--------|-----|-----|
| Format | Joblang DSL (plain text) | JSON |
| Endpoint | `/parse` | `/process` |
| Actions | Derived from `type` | Explicit in payload |
| Action identifier | `type` field | `name` field |
| Action names | `printing`, `folding`, `stitching` | `print`, `cut`, `cutout`, `split`, `assembly` |
| Part type | Required (`leaflet`, `feuillet`) | **Removed** |
| MetaData | In DSL header | Not yet implemented (later) |

**Action order:** The array order in `actions[]` defines the execution order (no separate `order` field needed).

### 3.2 New backend logic based on explicit actions

**Processing flow:**

1. `ProcessController` receives JSON payload
2. Validate payload structure:
   - `parts` array must exist
   - Each part must have `partId`, `actions[]`, `required_parts`
   - Each action must have `name` field
   - Action `name` must be one of: `print`, `cut`, `cutout`, `split`, `assembly`
3. For invalid action names → return error response
4. Store parts and actions (Part entity)
5. Run ActionTree to compute ActionPath candidates
6. Persist ActionPath entities
7. Return ID in response

**Action model:**

```php
class Action {
    public string $name;      // "print", "cut", "cutout", "split", "assembly"
    public array $params;     // action-specific parameters (stored, not validated yet)
}
```

**Allowed action names (enum):**

| Name | Status | Notes |
|------|--------|-------|
| `print` | Active | |
| `cut` | Active | |
| `cutout` | Active | |
| `split` | Experimental | Will be removed later |
| `assembly` | Experimental | Will be removed later |

**Validation rules:**

| Rule | Behaviour |
|------|-----------|
| Unknown action name | Error response |
| Empty actions array | Allowed |
| Action order constraints | None (array order = execution order) |
| Required actions | None |
| `params` validation | Not implemented yet (store as-is) |

**Response:**

Success:
```json
{
  "id": "<generated-id>"
}
```

Error (e.g. unknown action):
```json
{
  "error": "Unknown action name: laminate",
  "code": "INVALID_ACTION_NAME"
}
```

---

## 4. Refactor tasks for Claude Code

### Phase 1: New Action Infrastructure ✅

- [x] **1.1** Create `ActionName` enum in `src/Domain/Action/`
  - Values: `print`, `cut`, `cutout`, `split`, `assembly`
  - Mark `split` and `assembly` as experimental (PHPDoc comment)

- [x] **1.2** Create `PayloadAction` DTO in `src/Domain/Action/`
  - Properties: `name: ActionName`, `params: array`
  - Constructor accepts raw array from JSON payload

- [x] **1.3** Create `ActionValidator` service in `src/Service/`
  - Validates action name against `ActionName` enum
  - Returns structured error for unknown action names

### Phase 2: New Endpoint ✅

- [x] **2.1** Create `ProcessController` in `src/Controller/`
  - Route: `POST /process`
  - Accepts JSON payload
  - Returns `{"id": "..."}` on success
  - Returns error response on validation failure

- [x] **2.2** Create `ProcessRequestModel` DTO
  - Represents the incoming JSON structure
  - Contains `parts[]` with `partId`, `actions[]`, `properties`, `required_parts`

- [x] **2.3** Create `ProcessUseCase` in `src/Application/Process/`
  - Orchestrates: validation → persistence
  - ActionTree integration deferred until params structure is defined

### Phase 3: Adapt ActionTree Integration ✅

- [x] **3.1** Map new `ActionName` enum to existing `ActionType`/`MachineType`
  - `print` → `PrintingPress`
  - `cut` → `CuttingMachine`
  - `cutout` → `CutoutMachine`
  - `split` → `Splitter`
  - `assembly` → `Assembler`
  - Created `ProcessAbstractAction` class

- [x] **3.2** Integrate with `ActionTree::process()`
  - Created `ActionParamsExtractor` service to extract ActionTree params from payload
  - Created `ActionTreeInput` DTO for ActionTree parameters
  - Created `PressSheetProvider` service for hardcoded press sheets
  - Extended `ProcessUseCase` with full ActionTree integration
  - Extended `ActionTree::extend()` for new action types (cutout, splitter, assembler)

### Phase 4: Persistence ✅

- [x] **4.1** Entity structure decided
  - Created new entities: `ProcessRequest`, `ProcessPart`, `ProcessActionPath`
  - Independent from Joblang DSL flow

- [x] **4.2** Full persistence implemented
  - `ProcessUseCase` persists `ProcessRequest` and `ProcessPart` entities
  - `ProcessActionPath` entities are created from ActionTree results
  - Best paths (top 10 by cost) are persisted

### Phase 5: Deprecation & Cleanup ✅

- [x] **5.1** Mark legacy endpoints as deprecated
  - Added `@deprecated` to `ParserController`
  - Added `@deprecated` to `Leaflet`, `Feuillet`, `PartFactory`, `PartType`
  - Added `@deprecated` to `ActionType`
  - Added `@deprecated` to `JoblangService`, `ParseJoblangScriptUseCase`

- [x] **5.2** Document migration path
  - See section 5: Migration notes

### Phase 6: Testing ✅

- [x] **6.1** Add PHPUnit tests for `ProcessController`
  - `tests/Controller/ProcessControllerTest.php` (9 tests)
  - Valid payload → success response
  - Invalid action name → error response
  - Missing parts/partId/actions → error response
  - Empty parts array → success
  - Multiple parts → success
  - All action types → success
  - Invalid JSON → error response

- [x] **6.2** Add unit tests for `ActionValidator`
  - `tests/Service/ActionValidatorTest.php` (10 tests)
  - Valid/invalid action names
  - Missing name field
  - Params preservation
  - Error response format

---

### Resolved Questions

1. **Machine type mapping:**
   | ActionName | MachineType |
   |------------|-------------|
   | `print` | `MachineType::PrintingPress` |
   | `cut` | `MachineType::CuttingMachine` |
   | `cutout` | `MachineType::CutoutMachine` |
   | `split` | `MachineType::Splitter` |
   | `assembly` | `MachineType::Assembler` |

2. **Entity reuse:** Prefer reusing existing entities. Only create new entities if absolutely necessary.

3. **ActionTree compatibility:** ActionTree will need extension to support the new action types (later task).

---

## 5. Migration notes

### Deprecated classes (marked with @deprecated)

| File | Replacement |
|------|-------------|
| `src/Controller/ParserController.php` | `ProcessController` |
| `src/Domain/Part/Leaflet.php` | Explicit actions in payload |
| `src/Domain/Part/Feuillet.php` | Explicit actions in payload |
| `src/Domain/Part/PartFactory.php` | Not needed with new flow |
| `src/Domain/Part/PartType.php` | Not needed with new flow |
| `src/Domain/Action/ActionType.php` | `ActionName` enum |
| `src/Domain/Joblang/JoblangService.php` | `ProcessUseCase` |
| `src/Application/Joblang/UseCase/ParseJoblangScript/ParseJoblangScriptUseCase.php` | `ProcessUseCase` |

### Migration path: OLD → NEW

**Old flow (Joblang DSL):**
```
POST /parse (Joblang DSL text)
  → ParserController
  → ParseJoblangScriptUseCase
  → JoblangService (Node.js parser)
  → Job, Part entities (with hardcoded actions from PartFactory)
  → {"scriptId": "..."}

GET /test/{scriptId}
  → TestController
  → ActionTree processing
  → ActionPath entities
```

**New flow (JSON payload):**
```
POST /process (JSON with explicit actions)
  → ProcessController
  → ActionValidator
  → ProcessUseCase
  → ProcessRequest, ProcessPart entities
  → {"id": "..."}

(ActionTree integration - COMPLETE)
  → ActionParamsExtractor
  → PressSheetProvider
  → ActionTree::process()
  → ProcessActionPath entities
```

### New files created

| File | Purpose |
|------|---------|
| `src/Domain/Action/ActionName.php` | New action name enum |
| `src/Domain/Action/PayloadAction.php` | Action DTO from payload |
| `src/Domain/Action/ProcessAbstractAction.php` | AbstractAction for new flow |
| `src/Service/ActionValidator.php` | Validates action names |
| `src/Service/ActionValidatorInterface.php` | Interface |
| `src/Service/ActionValidationResult.php` | Validation result DTO |
| `src/Controller/ProcessController.php` | New endpoint |
| `src/Application/Process/ProcessRequestModel.php` | Request DTO |
| `src/Application/Process/ProcessResponseModel.php` | Response DTO |
| `src/Application/Process/PartPayload.php` | Part DTO |
| `src/Application/Process/UseCase/ProcessUseCase.php` | Use case |
| `src/Entity/ProcessRequest.php` | Request entity |
| `src/Entity/ProcessPart.php` | Part entity |
| `src/Entity/ProcessActionPath.php` | ActionPath entity |
| `src/Repository/ProcessRequestRepository.php` | Repository |
| `src/Repository/ProcessPartRepository.php` | Repository |
| `src/Repository/ProcessActionPathRepository.php` | Repository |
| `src/Application/Process/ActionTreeInput.php` | DTO for ActionTree params |
| `src/Service/ActionParamsExtractor.php` | Extracts ActionTree params from payload |
| `src/Service/ActionParamsExtractorInterface.php` | Interface |
| `src/Service/PressSheetProvider.php` | Provides hardcoded press sheets |
| `src/Service/PressSheetProviderInterface.php` | Interface |
| `src/Domain/Equipment/CutoutMachine.php` | Cutout machine class |
| `src/Domain/Equipment/Splitter.php` | Splitter machine class (experimental) |
| `src/Domain/Equipment/Assembler.php` | Assembler machine class (experimental) |

## 6. ActionTree Integration Plan

### 6.1 ActionTree.process() paraméterek

Az `ActionTree::process()` metódus a következő paramétereket várja:

| Paraméter | Típus | Leírás | Honnan jön? |
|-----------|-------|--------|-------------|
| `abstractActions` | `AbstractActionInterface[]` | Action-ök listája | `PayloadAction[]` → `ProcessAbstractAction[]` |
| `pressSheets` | `PressSheetInterface[]` | Nyomtatási ívek | Rendszer konfiguráció vagy payload |
| `zone` | `InputSheetInterface` | Zóna (closed dimensions + grip margin) | Payload params |
| `openPoseDimensions` | `DimensionsInterface` | Nyitott méret | Payload params |
| `closedPoseDimensions` | `DimensionsInterface` | Zárt méret | Payload params |
| `numberOfCopies` | `float` | Példányszám | Payload (metaData vagy part szint) |
| `numberOfColors` | `float` | Színek száma | Számított (inking-ből) |
| `paperWeight` | `float` | Papír súly (g/m²) | Payload params |
| `inking` | `array` | Festékezés (`recto`/`verso`) | Payload params |

### 6.2 Szükséges payload params struktúra

Az ActionTree integrációhoz a következő `params` struktúrát kell definiálni a `print` action-höz:

```json
{
  "parts": [
    {
      "partId": "PART0001",
      "copies": 1000,
      "actions": [
        {
          "name": "print",
          "params": {
            "dimensions": {
              "open": { "width": 420, "height": 296 },
              "closed": { "width": 210, "height": 148 }
            },
            "paper": {
              "weight": 120
            },
            "inking": {
              "recto": ["cyan", "magenta", "yellow", "black"],
              "verso": ["cyan", "magenta"]
            },
            "zone": {
              "width": 210,
              "height": 148,
              "gripMargin": 10
            }
          }
        },
        {
          "name": "cut",
          "params": {}
        }
      ],
      "required_parts": []
    }
  ]
}
```

### 6.3 Implementációs lépések

#### Step 1: Param extractor service

Létrehozni egy `ActionParamsExtractor` service-t, ami kiolvassa az ActionTree-hez szükséges értékeket a payload-ból:

```php
class ActionParamsExtractor
{
    public function extractForActionTree(PartPayload $part): ActionTreeInput
    {
        // Find print action and extract params
        // Build Dimensions, Zone, etc.
        // Return structured input for ActionTree
    }
}
```

#### Step 2: ActionTreeInput DTO

```php
class ActionTreeInput
{
    public function __construct(
        public array $abstractActions,        // ProcessAbstractAction[]
        public array $pressSheets,            // PressSheetInterface[]
        public InputSheetInterface $zone,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
    ) {}
}
```

#### Step 3: ProcessUseCase bővítése

```php
class ProcessUseCase
{
    public function execute(ProcessRequestModel $request): ProcessResponseModel
    {
        // 1. Persist ProcessRequest and ProcessPart entities
        // 2. For each part:
        //    - Extract ActionTree params
        //    - Convert PayloadAction[] to ProcessAbstractAction[]
        //    - Call ActionTree::process()
        //    - Persist ProcessActionPath entities
        // 3. Return response
    }
}
```

#### Step 4: PressSheet konfiguráció

Opciók:
- **A)** Hardcoded press sheets (mint most a TestController-ben)
- **B)** Payload-ban küldi a kliens
- **C)** Adatbázisból/konfigurációból jön

### 6.4 Új action típusok kezelése

Az ActionTree jelenleg csak `printing press`, `folder`, `stitching machine` típusokat kezel az `extend()` metódusban.

Az új action típusokhoz (`cut`, `cutout`, `split`, `assembly`) bővíteni kell:

```php
// ActionTree::extend() bővítése
if ($node->getMachine()->getType()->value === "cutting machine") {
    $node->setTodo([
        "numberOfCopies" => $this->numberOfCopies,
        "cutSheetCount" => $cutSheetCount,
        // cutting-specific params
    ]);
}

if ($node->getMachine()->getType()->value === "cutout machine") {
    // cutout-specific logic
}
// etc.
```

### 6.5 Equipment konfiguráció

Új gép definíciók kellenek a `resources/equipment.yaml` (vagy hasonló) fájlban:

- `cutout-machine` (CutoutMachine)
- `splitter` (Splitter) - experimental
- `assembler` (Assembler) - experimental

Az `EquipmentFactory::fromType()` és `EquipmentFactory::create()` metódusokat bővíteni kell.

### 6.6 Összefoglaló - Tennivalók ✅

| # | Feladat | Státusz |
|---|---------|---------|
| 1 | Definiálni a `print` action params struktúrát | ✅ Kész (6.2 szekcióban) |
| 2 | Létrehozni `ActionParamsExtractor` service-t | ✅ Kész |
| 3 | Létrehozni `ActionTreeInput` DTO-t | ✅ Kész |
| 4 | Bővíteni `ProcessUseCase`-t ActionTree hívással | ✅ Kész |
| 5 | Dönteni a PressSheet forrásról (hardcoded/payload/config) | ✅ Hardcoded (PressSheetProvider) |
| 6 | Bővíteni `ActionTree::extend()` az új action típusokhoz | ✅ Kész |
| 7 | Hozzáadni új gép konfigurációkat | ✅ Kész (equipment.yaml) |
| 8 | Bővíteni `EquipmentFactory`-t az új MachineType-okhoz | ✅ Kész |

---

## 7. Working notes

This section is for **temporary notes** during the refactor.

> Here I can add clarifications, edge cases, and decisions that come up while analysing the legacy code.
> Claude can use this section as additional context but should always prioritise the formal description in sections 1–4.

### Phase 7: Response format átalakítás

**Cél:** A `ProcessController::process()` response-ját úgy kell átalakítani, hogy a régi `TestController::getTest()` response formátumát kövesse.

#### 7.1 Jelenlegi állapot

**ProcessController::process() response:**
```json
{
  "id": "<generated-id>"
}
```

**TestController::getTest() response (createResponse2):**
```json
[
  {
    "metaData": {
      "jobNumber": "JOB001",
      "quantity": 1000,
      "jobId": "<job-uuid>"
    },
    "parts": {
      "PART0001": {
        "actionPaths": [
          {
            "id": "<actionpath-uuid>",
            "designation": "(1020x720) PrintingPress > Folder Cost: 150€; Duration: 45min",
            "nodes": [...],
            "cost": 150,
            "duration": 45,
            "pressSheet": "1020x720mm",
            "openPoseDimensions": "420x296",
            "closedPoseDimensions": "210x148",
            "requiredParts": [],
            "medium": {...}
          }
        ]
      }
    }
  }
]
```

#### 7.2 Tervezett új response (ProcessController)

A régi formátumot követve, hardcoded fake metaData-val (később implementáljuk):

```json
[
  {
    "metaData": {
      "jobNumber": "PROCESS-001",
      "quantity": 0,
      "jobId": "<processrequest-uuid>"
    },
    "parts": {
      "PART0001": {
        "actionPaths": [
          {
            "id": "<actionpath-uuid>",
            "designation": "(1020x720) PrintingPress > CuttingMachine Cost: 150€; Duration: 45min",
            "nodes": [...],
            "cost": 150,
            "duration": 45,
            "pressSheet": "1020x720mm",
            "openPoseDimensions": "420x296",
            "closedPoseDimensions": "210x148",
            "requiredParts": []
          }
        ]
      }
    }
  }
]
```

#### 7.3 Implementációs lépések

| # | Feladat | Leírás | Státusz |
|---|---------|--------|---------|
| 1 | `ProcessResponseModel` bővítése | Új property: `parts` array, `metaData` array | ✅ Kész |
| 2 | `ProcessUseCase` módosítása | Response építés az ActionPath entitásokból, hardcoded metaData | ✅ Kész |
| 3 | `ProcessController` módosítása | Új response formátum visszaadása (tömb struktúra) | ✅ Kész |
| 4 | Tesztek frissítése | `ProcessControllerTest` elvárások frissítése | ✅ Kész |

#### 7.4 Részletes terv

**1. ProcessResponseModel bővítése:**
```php
class ProcessResponseModel
{
    public function __construct(
        public string $id,
        public array $metaData = [],   // Hardcoded fake metaData (later: from payload)
        public array $parts = [],       // partId => ['actionPaths' => [...]]
    ) {}
}
```

**2. ProcessUseCase::execute() módosítása:**
- Az ActionPath entitások létrehozása után össze kell gyűjteni a response adatokat
- Csoportosítás partId szerint
- Hardcoded metaData: `["jobNumber" => "PROCESS-001", "quantity" => 0, "jobId" => $processRequest->getId()]`
- JSON struktúra építése

**3. ProcessController::process() módosítása:**
```php
return new JsonResponse(
    [
        [
            'metaData' => $responseModel->metaData,
            'parts' => $responseModel->parts,
        ]
    ],
    JsonResponse::HTTP_OK
);
```

**Megjegyzés:** A response tömb formátumú (array of jobs), ahogy a TestController-ben is, így a kliens kompatibilis marad.

---

### Phase 7: Response Format Transformation ✅

A `ProcessController::process()` response-ja sikeresen átalakítva lett, hogy a `TestController::getTest()` kompatibilis formátumot adjon vissza.

#### Implementált változások:

**Backend:**
- `ProcessResponseModel` bővítve `metaData` és `parts` property-kkel
- `ProcessUseCase::execute()` módosítva ActionPath adatok összegyűjtésére
- `ProcessController::process()` tömb struktúrájú response visszaadása
- `ProcessControllerTest` frissítve az új formátumhoz (9/9 teszt átment)

**Frontend:**
- `public/index.html::parse()` átírva `/process` endpoint direkt híváshoz
- Textarea tartalom lecserélve Joblang DSL-ről JSON payload-ra
- `public/js/app.js::calc()` deprecated (megjegyzésekkel)
- `displayAllTextualExplanation()` globálisan exportálva

#### Response formátum:

```json
[
  {
    "metaData": {
      "jobNumber": "PROCESS-001",
      "quantity": 0,
      "jobId": "<uuid>"
    },
    "parts": {
      "PART0001": {
        "actionPaths": [...]
      }
    }
  }
]
```

#### Frontend flow:

**Előtte (2-körös):**
```
User input (DSL) → Parse button → /parse → scriptId
                                 ↓
                              calc()
                                 ↓
                            /test/{scriptId}
                                 ↓
                            Display results
```

**Utána (1-körös):**
```
User input (JSON) → Parse button → /process → Display results
```

---

### 2024-12-08: ActionTree Integration Complete

Az ActionTree integráció elkészült. Új fájlok:

| Fájl | Cél |
|------|-----|
| `src/Service/PressSheetProvider.php` | Hardcoded press sheet-ek szolgáltatása |
| `src/Service/PressSheetProviderInterface.php` | Interface |
| `src/Domain/Equipment/CutoutMachine.php` | Cutout gép osztály |
| `src/Domain/Equipment/Splitter.php` | Splitter gép osztály (experimental) |
| `src/Domain/Equipment/Assembler.php` | Assembler gép osztály (experimental) |

Módosított fájlok:

- `src/Application/Process/UseCase/ProcessUseCase.php` - ActionTree integráció
- `src/Domain/Action/ActionTree.php` - Új action típusok támogatása extend()-ben
- `src/Domain/Equipment/EquipmentFactory.php` - Új MachineType-ok támogatása
- `resources/equipment.yaml` - Új gép konfigurációk
- `config/services.yaml` - Interface binding-ok
