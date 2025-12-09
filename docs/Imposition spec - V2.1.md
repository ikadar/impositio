# Table of contents

[**Vocabulary	2**](#vocabulary)

[Dimension orientation convention	2](#dimension-orientation-convention)

[Print carriers and sheet definitions	2](#print-carriers-and-sheet-definitions)

[Cutting operations	2](#cutting-operations)

[Layout model	3](#layout-model)

[Production model	4](#production-model)

[Abstract model	4](#abstract-model)

[Execution context	4](#execution-context)

[Machine-Driven Behavior	5](#machine-driven-behavior)

[Execution Phases	5](#execution-phases)

[Action Logic Structures	6](#action-logic-structures)

[**Application overview	7**](#application-overview)

[The Purpose of the Application	7](#the-purpose-of-the-application)

[Computational model overview	8](#computational-model-overview)

[1\. Abstract production process	8](#1.-abstract-production-process)

[2\. Machine assignment plan	8](#2.-machine-assignment-plan)

[3\. Action backtrace tree	8](#3.-action-backtrace-tree)

[4\. Action path	8](#4.-action-path)

[Application Workflow	8](#application-workflow)

[Input interpretation	8](#input-interpretation)

[Machine assignment plan enumeration	9](#machine-assignment-plan-enumeration)

[Action path expansion & layout resolution	9](#action-path-expansion-&-layout-resolution)

[Layout and cut planning (pre-cut phase)	9](#layout-and-cut-planning-\(pre-cut-phase\))

[Run calculation	10](#run-calculation)

[Output generation	10](#output-generation)

# 

# Vocabulary {#vocabulary}

## Dimension orientation convention {#dimension-orientation-convention}

Throughout this specification, all rectangular areas — including [sheet](#bookmark=id.qd1ud4bprmb6), [zone](#bookmark=id.gfmlm071olh9), [pose](#bookmark=id.dn2qgc42v0lh), and their corresponding layout types — are described in landscape orientation by default.

This means that:

* Width always refers to the longer side  
* Height always refers to the shorter side

## Print carriers and sheet definitions {#print-carriers-and-sheet-definitions}

During the printing process, [parent sheets](#bookmark=id.4lnlziyrwje8) may be cut into smaller, equal-sized pieces and/or trimmed, depending on production needs.

**Press sheet:** the original, full-sized, uncut sheet.

**Parent sheet:** a sheet that is to be trimmed or cut into smaller [cut sheets](#bookmark=id.qd1ud4bprmb6) in a [mid-process cutting](#bookmark=id.ybjmnriuua23) [action](#bookmark=id.i0pznlc0r7dg). In this context, “parent” refers to its role in the current workflow stage — it may be the original [press sheet](#bookmark=id.5yd117q4r7cf) or a previously [cut sheet](#bookmark=id.qd1ud4bprmb6). The term is relative to the current stage of the workflow.

**Cut sheet**: a piece of a [parent sheet](#bookmark=id.4lnlziyrwje8) that has been cut into smaller, equal-sized pieces, and/or trimmed. 

Notes: 

- Use “cut sheet” for [trimmed sheets](#bookmark=id.os1qn6q2u3ny) as well, unless the trimming needs to be explicitly emphasized.)

- The term "*cut sheet*" is the same as "single sheet" in industry standard terminology, however it emphasizes sheet origin (cut from another sheet); “single sheet” refers to how it is fed into the press.

**Trimmed sheet:** a specific term used only when it is necessary to emphasize that the sheet has been trimmed. If trimming is not the focus, use “cut sheet” or “sheet,” depending on the context.

From this point on, the term “sheet” refers to both “[parent sheets](#bookmark=id.4lnlziyrwje8)” and “cut sheets”, unless we specifically want to emphasize whether the sheet has been cut or not.

## Cutting operations {#cutting-operations}

**Cut spacing buffer:** a spacing margin introduced around the perimeter of a [cut sheet](#bookmark=id.qd1ud4bprmb6) — or between [sheet layout](#bookmark=id.1t6o2moxn6gc) segments — to allow for precise [mid-process cutting](#bookmark=id.ybjmnriuua23). Unlike [bleed](#bookmark=id.rlb51snc6gzd) (which is design-driven and uniform), *cut spacing buffers* are production-driven and may be asymmetrical. They are determined programmatically. Default base value: 20 mm on all sides.

**Mid-process cut:** a cut applied between production [actions](#bookmark=id.i0pznlc0r7dg), not based on product dimensions but required to conform to the input size or any other constraints of subsequent [machines](#bookmark=id.us1pvx2opje4). These cuts necessitate [cut spacing buffers](#bookmark=id.87ihx7pgmjdf) and occur before [final product cutting](#bookmark=id.5kigvheyrsj2).

**Final product cut:** refers to the last cutting operation in the production process, where the printed and processed material is trimmed to match the required dimensions by the end product. This cut removes excess [bleed](#bookmark=id.rlb51snc6gzd) and ensures precise final dimensions of the end product.

## Layout model {#layout-model}

**Bleed:** the margin surrounding the unfolded product dimensions. The *bleed* size is constant and uniform on both the shorter and longer edges of the area. The default value is 5mm.

**Grip margin:** the part of the sheet that cannot be used in a given [action](#bookmark=id.i0pznlc0r7dg), as it is reserved for gripping by the machine. The *grip margin* is always considered part of the sheet, not of the [zone](#bookmark=id.gfmlm071olh9), therefore in case of the [zone](#bookmark=id.gfmlm071olh9) is smaller than the sheet size, then the *grip margin* is separated from the zone.

**Pose:** a rectangular area with the dimensions of the unfolded product, including [bleed](#bookmark=id.rlb51snc6gzd). It is calculated from the unfolded size of the final product as follows:

* Leaflet  
  * *pose* width \= product unfolded width \+ 2 \* [bleed](#bookmark=id.rlb51snc6gzd) size;  
  * *pose* height: product unfolded height \+ 2 \* [bleed](#bookmark=id.rlb51snc6gzd) size;  
* Booklet: (booklet imposition logic depends on folding and binding strategy and will be defined later.)

**Zone:** an area containing exactly one [pose layout](#bookmark=id.e26wcdx2jx4a). If the zone is larger than the [pose layout](#bookmark=id.e26wcdx2jx4a), the layout must be centered within the zone.

**Usable area:** the portion of a sheet that remains available for use after subtracting the [grip margin](#bookmark=id.2ru3fb1u3ar).

**Exhaustive grid fitting:** the complete set of all possible ways a rectangular unit (such as a [pose](#bookmark=id.dn2qgc42v0lh), [zone](#bookmark=id.gfmlm071olh9), or [cut sheet](#bookmark=id.qd1ud4bprmb6)) can be arranged in a uniform grid within a larger rectangular area.

This includes variations in:

* the number of units placed,  
* their orientation (unrotated and 90° rotated),  
* and their horizontal and vertical arrangement.

**Pose layout:** a single [exhaustive grid fitting](#bookmark=id.2inl7iqz8xiy) configuration of [poses](#bookmark=id.dn2qgc42v0lh) within a [zone](#bookmark=id.gfmlm071olh9), where [poses](#bookmark=id.dn2qgc42v0lh) are placed directly next to each other without spacing, as [mid-process cuts](#bookmark=id.ybjmnriuua23) are never applied within [zones](#bookmark=id.gfmlm071olh9).

**Zone layout:** a single [exhaustive grid fitting](#bookmark=id.2inl7iqz8xiy) configuration of identical [zones](#bookmark=id.gfmlm071olh9) within the [usable area](#bookmark=id.8hw9c78fw08f) of a sheet.

**Sheet layout:** a single [exhaustive grid fitting](#bookmark=id.2inl7iqz8xiy) configuration of identical [cut sheets](#bookmark=id.qd1ud4bprmb6) within the [usable area](#bookmark=id.8hw9c78fw08f) of the [parent sheet](#bookmark=id.4lnlziyrwje8). This defines how a [parent sheet](#bookmark=id.4lnlziyrwje8) is subdivided via [mid-process cuts](#bookmark=id.ybjmnriuua23) before the next [action](#bookmark=id.i0pznlc0r7dg), and serves as the basis for determining [mid-process cut](#bookmark=id.ybjmnriuua23) positions in the previous step.

## Production model {#production-model}

### Abstract model {#abstract-model}

**Abstract production process:** a preliminary definition of the production workflow that lists the required [abstract actions](#bookmark=id.fs49fqnhf9ax) (e.g., printing → folding → binding), in logical order. An *abstract production process* includes all essential [abstract actions](#bookmark=id.fs49fqnhf9ax) to produce the final product, excluding any assumptions about [mid-process cutting](#bookmark=id.ybjmnriuua23) or layout configurations.

**Abstract action:** a single operation in the [abstract production process](#bookmark=id.ktprva1s4kki), such as folding, printing, or binding. Each *abstract action* is assigned to exactly one [machine type](#bookmark=id.un6zap84esa9), and during production, is performed by exactly one specific [machine](#bookmark=id.us1pvx2opje4).

**Machine type:** an abstract category of [machines](#bookmark=id.us1pvx2opje4) that perform the same kind of [abstract action](#bookmark=id.fs49fqnhf9ax) (e.g., sheet-fed offset press, folding machine, guillotine cutter). *Machine types* define constraints such as supported sheet sizes, orientation rules, grip margin requirements, and maximum [sheet](#bookmark=id.qd1ud4bprmb6) throughput.

### Execution context {#execution-context}

**Machine:** a specific physical *machine* in the printing facility, associated with one [machine type](#bookmark=id.un6zap84esa9). Each *machine* may have its own individual parameters (e.g., minimum and maximum sheet size, feeder orientation, throughput speed, setup time) and is assigned to one or more [actions](#bookmark=id.i0pznlc0r7dg).

**Machine assignment plan:** a mapping of [abstract actions](#bookmark=id.fs49fqnhf9ax) to specific [machines](#bookmark=id.us1pvx2opje4) within a given [abstract production process](#bookmark=id.ktprva1s4kki). A *machine assignment plan* defines a complete, concrete execution setup by selecting one specific [machine](#bookmark=id.us1pvx2opje4) for each required [action](#bookmark=id.i0pznlc0r7dg). It serves as the structural basis for generating all valid [action paths](#bookmark=id.2cnndstbwd1u).

**Action:** a discrete step in the print production workflow that performs a physical operation on a print carrier (such as a sheet, [zone](#bookmark=id.gfmlm071olh9), or [cut sheet](#bookmark=id.qd1ud4bprmb6)) — including printing, folding, [mid-process cutting](#bookmark=id.ybjmnriuua23), or binding. Each *action* has defined input conditions (e.g., sheet size, rotation allowance, [grip margin](#bookmark=id.2ru3fb1u3ar)). Each node of the [action backtrace tree](#bookmark=id.ao8kn3es00fc) represents an *action*.

The backward construction of [action paths](#bookmark=id.2cnndstbwd1u) ensures that every sequence of [actions](#bookmark=kix.ir6ef7djan8j) is physically valid in the forward direction of the production process — each [action](#bookmark=kix.ir6ef7djan8j) is guaranteed to produce a result that is acceptable as input for the next.

**Machine run:** a continuous activation of a specific [machine](#bookmark=id.us1pvx2opje4) to perform the same [action](#bookmark=id.i0pznlc0r7dg) on a batch of input [sheets](#bookmark=id.qd1ud4bprmb6) with identical configuration settings. Each *run* processes a fixed layout setup without reconfiguration.

[Machines](#bookmark=id.us1pvx2opje4) may specify a minimum or maximum batch size that can be processed in a single *run*.

**Batch:** a group of input [sheets](#bookmark=id.qd1ud4bprmb6) processed in one [machine run](#bookmark=kix.jn4wvx2uxu49). *Batches* must meet the minimum and maximum [sheet](#bookmark=id.qd1ud4bprmb6) quantity or dimension requirements of the [machine](#bookmark=id.us1pvx2opje4) being used. 

### Machine-Driven Behavior {#machine-driven-behavior}

**Machine constraints:** a set of physical and operational limitations that define what a [machine](#bookmark=id.us1pvx2opje4) can accept as input and produce as output during an [action](#bookmark=id.i0pznlc0r7dg). *Machine constraints* typically include:

* minimum and maximum [sheet](#bookmark=id.qd1ud4bprmb6) size  
* [grip margin](#bookmark=id.2ru3fb1u3ar) requirements  
* throughput speed (used in time estimation)

These *constraints* are used in layout planning, [mid-process cut](#bookmark=id.ybjmnriuua23) calculation.

Machines may also be subject to additional machine-level rules, such as required prerequisite steps or multifunctional behavior, which are not considered constraints but affect how actions are assigned.

**Machine implication rule:** when a [machine](#bookmark=id.us1pvx2opje4) requires a prerequisite processing step (e.g., a CTP step before offset printing), the system automatically inserts a corresponding [concrete action](#bookmark=id.i0pznlc0r7dg) into any affected [machine assignment plan](#bookmark=id.uuwoyfjyeme6), before the [action](#bookmark=id.i0pznlc0r7dg) associated with that [machine](#bookmark=id.us1pvx2opje4). This does not alter the [abstract production process](#bookmark=id.ktprva1s4kki).

**Multi-action capability rule:** when a single [machine](#bookmark=id.us1pvx2opje4) is capable of executing multiple consecutive [abstract actions](#bookmark=id.fs49fqnhf9ax), the system may assign those [abstract actions](#bookmark=id.fs49fqnhf9ax) to the same [machine](#bookmark=id.us1pvx2opje4) within a [machine assignment plan](#bookmark=id.uuwoyfjyeme6). This reflects real-world multifunctional [machines](#bookmark=id.us1pvx2opje4), such as saddle stitchers that also trim in-line.

### Execution Phases {#execution-phases}

**Production phase:** the production workflow is conceptually divided into two distinct phases:

1. The [pre-cut phase](#bookmark=id.uw41xbjgmyz8), which includes all layout-driven [actions](#bookmark=id.i0pznlc0r7dg) leading up to the [final product cut](#bookmark=id.5kigvheyrsj2), and  
2. The [post-cut phase](#bookmark=id.lflybw9vii4f), which includes trimming, finishing, and any downstream actions after the [final product cut](#bookmark=id.5kigvheyrsj2).

This distinction is essential for understanding which components of the system apply to layout planning and mid-process cut determination.

**Pre-cut phase:** the [phase](#bookmark=id.rs7lxqfr1gwh) of the production process that precedes the [final product cut](#bookmark=id.5kigvheyrsj2). It includes all layout-relevant transformations such as printing, folding, mid-process cutting, and their associated layout planning steps.

All calculations related to [sheet](#bookmark=id.1t6o2moxn6gc), [zone](#bookmark=id.yywhdcgtw8ii), and [pose layouts](#bookmark=id.e26wcdx2jx4a) — as well as the [action backtrace tree](#bookmark=id.ao8kn3es00fc) — take place exclusively within this [phase](#bookmark=id.rs7lxqfr1gwh).

**Post-cut phase:** The [phase](#bookmark=id.rs7lxqfr1gwh) of the production process that begins with the [final product cut](#bookmark=id.5kigvheyrsj2) and continues with finishing steps such as sorting, packaging, and delivery preparation.

No further layout computations are performed in this [phase](#bookmark=id.rs7lxqfr1gwh), and it falls outside the scope of [mid-process cut](#bookmark=id.ybjmnriuua23) logic and [action backtrace](#bookmark=id.ao8kn3es00fc) computation.

### Action Logic Structures {#action-logic-structures}

**Action backtrace tree:** a tree structure constructed for a single [exhaustive grid fitting](#bookmark=id.2inl7iqz8xiy) configuration (such as a [zone layout](#bookmark=id.yywhdcgtw8ii)), representing all valid upstream [action paths](#bookmark=id.2cnndstbwd1u) including [mid-process cut](#bookmark=id.ybjmnriuua23) combinations that can lead to the current layout from earlier stages in the production workflow.

Each complete branch from a leaf to the root — where the leaf represents the first machine’s [action](#bookmark=id.i0pznlc0r7dg), and the root represents the last machine’s [action](#bookmark=id.i0pznlc0r7dg) before the [final product cut](#bookmark=id.5kigvheyrsj2) — defines a full upstream production scenario that can generate the current layout.

**Action path:** a sequence of connected actions in the production workflow.In the context of the [action backtrace tree](#bookmark=id.ao8kn3es00fc), an *action path* represents a continuous route from a leaf node to the root node, describing a valid upstream production scenario.

**Root node:** in the context of an [action backtrace tree](#bookmark=id.ao8kn3es00fc), the *root node* represents the last machine’s [action](#bookmark=id.i0pznlc0r7dg) before the [final product cut](#bookmark=id.5kigvheyrsj2). It is the endpoint of each valid upstream [action path](#bookmark=id.2cnndstbwd1u) and marks the [action](#bookmark=id.i0pznlc0r7dg) immediately preceding [final product cutting](#bookmark=id.5kigvheyrsj2).

**Leaf node:** in the context of an [action backtrace tree](#bookmark=id.ao8kn3es00fc), the *leaf node* represents the first [action](#bookmark=id.i0pznlc0r7dg) in a given [action path](#bookmark=id.2cnndstbwd1u) — typically the earliest physical operation performed on the material, such as printing on a [sheet](#bookmark=id.qd1ud4bprmb6).

# Application overview {#application-overview}

## The Purpose of the Application {#the-purpose-of-the-application}

This application is designed for use in a small to mid-sized printing company, with the goal of supporting production optimization through automated layout planning, [machine assignment plan](#bookmark=id.uuwoyfjyeme6) evaluation, and cost-time estimation.

The purpose of the application is to calculate the **time and cost requirements** for all possible [machine assignment plan](#bookmark=id.uuwoyfjyeme6) that can execute a given production task.

To achieve this, the system generates **all valid [action paths](#bookmark=id.2cnndstbwd1u)** for each [machine assignment plan](#bookmark=id.uuwoyfjyeme6).

The **input** is an [abstract production process](#bookmark=id.ktprva1s4kki), which defines the required [abstract actions](#bookmark=id.fs49fqnhf9ax) that must be completed. The system may automatically:

* **insert additional [actions](#bookmark=id.i0pznlc0r7dg)** (e.g., [mid-process cuts](#bookmark=id.ybjmnriuua23), sheet flipping),

The **output** is a complete set of valid [action paths](#bookmark=id.2cnndstbwd1u), each of which:

* transforms the [abstract actions](#bookmark=id.fs49fqnhf9ax) into concrete [actions](#bookmark=id.i0pznlc0r7dg) executed on specific [machines](#bookmark=id.us1pvx2opje4),  
* includes additional [actions](#bookmark=id.i0pznlc0r7dg), if needed to fulfill [machine constraints](#bookmark=id.zmtkgbebxvf) or layout requirements,  
* contains each [action’s](#bookmark=id.i0pznlc0r7dg) execution parameters, estimated time, and cost impact  
* the system may later allow ranking or filtering of [action paths](#bookmark=id.2cnndstbwd1u) based on user preferences (e.g., minimum cost, minimum time, fewest runs).

### 

## Computational model overview {#computational-model-overview}

The application’s decision logic is structured around a **four-stage computational model**. Each stage transforms production input data into increasingly concrete and constrained representations of the print workflow.

### 1\. Abstract production process {#1.-abstract-production-process}

A high-level specification of what must be produced, including [abstract actions](#bookmark=id.fs49fqnhf9ax) such as printing, folding, and binding — but without assigning specific [machines](#bookmark=id.us1pvx2opje4), layout or cutting strategies.

### 2\. Machine assignment plan {#2.-machine-assignment-plan}

A concrete mapping of [abstract actions](#bookmark=id.fs49fqnhf9ax) to specific [machines](#bookmark=id.us1pvx2opje4). Each plan defines a full production route using available equipment, including any inserted prerequisite or multifunctional steps dictated by machine-level rules.

### 3\. Action backtrace tree {#3.-action-backtrace-tree}

For each [machine assignment plan](#bookmark=id.uuwoyfjyeme6), the system calculates all valid upstream layout transformation paths — including pose, zone, and sheet layout configurations — that result in the final required layout before [final product cutting](#bookmark=id.5kigvheyrsj2).

### 4\. Action path {#4.-action-path}

A fully resolved, executable sequence of [concrete actions](#bookmark=kix.ir6ef7djan8j) (including [mid-process cuts](#bookmark=id.ybjmnriuua23)), along with layout and cutting logic, cost and time estimates, and physical feasibility.

## Application Workflow {#application-workflow}

The application processes a production scenario through a structured series of computational phases. Each phase builds upon the previous one, gradually transforming the input [abstract production process](#bookmark=id.ktprva1s4kki) into concrete [action paths](#bookmark=id.2cnndstbwd1u) with associated cost and time estimates.

### Input interpretation {#input-interpretation}

This phase initiates the computation by receiving a **part**, an external data structure produced by another application, which describes a product and its required manufacturing steps.

The system then transforms the part into an internal [abstract production process](#bookmark=id.ktprva1s4kki), which defines a logically ordered sequence of required [abstract actions](#bookmark=id.fs49fqnhf9ax) such as printing, folding, and binding — without assigning specific [machines](#bookmark=id.us1pvx2opje4) or layout strategies.

The application validates both the structure of the incoming **part** and the resulting [abstract production process](#bookmark=id.ktprva1s4kki) after transformation, ensuring completeness and semantic consistency at both stages.

Each [abstract action](#bookmark=id.fs49fqnhf9ax) is associated with a corresponding [machine type](#bookmark=id.un6zap84esa9).

In addition to the process definition, this phase receives global production parameters such as:

* the required quantity of final products,  
* unfolded product [dimensions](#dimension-orientation-convention),  
* and optional constraints (e.g. maximum delivery time, paper type, etc.).

**Input:** part (external format), production parameters

**Output:** validated and normalized [abstract production process](#bookmark=id.ktprva1s4kki) (internal format), ready for machine assignment plan generation

### [Machine assignment plan](#bookmark=id.uuwoyfjyeme6) enumeration {#machine-assignment-plan-enumeration}

In this phase, the system transforms the [abstract production process](#bookmark=id.ktprva1s4kki) into one or more concrete [machine assignment plans](#bookmark=id.uuwoyfjyeme6). Each plan represents a complete mapping of [abstract actions](#bookmark=id.fs49fqnhf9ax) to specific [machines](#bookmark=id.us1pvx2opje4) available in the production facility.

For each [abstract action](#bookmark=id.fs49fqnhf9ax), the system enumerates all [machines](#bookmark=id.us1pvx2opje4) that belong to the corresponding [machine type](#bookmark=id.un6zap84esa9) and are available for execution. The system then generates all viable combinations of [machines](#bookmark=id.us1pvx2opje4) that cover the full sequence of [actions](#bookmark=kix.ir6ef7djan8j) in the [abstract production process](#bookmark=id.ktprva1s4kki).

During this enumeration, the system applies all relevant machine-level rules such as [machine implication rule](#bookmark=id.amqrcb51twmw) and [multi-action capability rule](#bookmark=id.ed0m6kyt7a9c).

As a result, a single [abstract production process](#bookmark=id.ktprva1s4kki) may lead to multiple distinct [machine assignment plans](#bookmark=id.uuwoyfjyeme6), depending on the available machine pool, rule implications, and functional capabilities.

**Input:** validated [abstract production process](#bookmark=id.ktprva1s4kki)

**Output:** complete set of [machine assignment plans](#bookmark=id.uuwoyfjyeme6), each covering all required [actions](#bookmark=kix.ir6ef7djan8j)

### Action path expansion & layout resolution {#action-path-expansion-&-layout-resolution}

For each [machine assignment plan](#bookmark=id.uuwoyfjyeme6), the system expands every valid [action path](#bookmark=id.2cnndstbwd1u) into a fully executable scenario by performing the following steps:

#### [Layout and cut planning](#bookmark=id.o8dfqh3myvqm) (pre-cut phase) {#layout-and-cut-planning-(pre-cut-phase)}

For all [actions](#bookmark=kix.ir6ef7djan8j) occurring before the [final product cut](#bookmark=id.5kigvheyrsj2), the system calculates layout configurations and required transformations that ensure compatibility between machines in the upstream production path.

This includes:

* determining all valid [pose](#bookmark=id.e26wcdx2jx4a), [zone](#bookmark=id.yywhdcgtw8ii), and [sheet layouts](#bookmark=id.1t6o2moxn6gc) for each layout level,  
* computing the [mid-process cuts](#bookmark=id.ybjmnriuua23) needed to match [machine input constraints](#bookmark=id.zmtkgbebxvf),  
* and constructing the corresponding [action backtrace trees](#3.-action-backtrace-tree), which represent all valid upstream layout sequences that can generate the required format for each [action](#bookmark=kix.ir6ef7djan8j).

Due to the complexity and recursive nature of layout and cut planning, this logic is documented in detail in a separate section: *See: Layout and Cut Planning (Pre-Cut Phase)*

**Input:** [machine assignment plan](#bookmark=id.uuwoyfjyeme6)

**Output:** [action backtrace trees](#bookmark=id.ao8kn3es00fc)

#### Run calculation {#run-calculation}

For each [action](#bookmark=kix.ir6ef7djan8j) in the [action path](#bookmark=id.2cnndstbwd1u), the system calculates how many [machine runs](#bookmark=kix.jn4wvx2uxu49) are required to produce the total quantity of output, based on the effective layout yield and the number of [poses](#bookmark=id.dn2qgc42v0lh) per [sheet](#bookmark=id.qd1ud4bprmb6).

The system uses this to calculate:

* the number of required [runs](#bookmark=kix.jn4wvx2uxu49),  
* the estimated time for execution,  
* and the associated production cost.

These values are directly stored within the [action path](#bookmark=id.2cnndstbwd1u), enriching it with all information required for time and cost evaluation.

**Input:** [action path](#bookmark=id.2cnndstbwd1u)

**Output:** [action path](#bookmark=id.2cnndstbwd1u) enriched with run count, estimated time, and estimated cost for each [action](#bookmark=kix.ir6ef7djan8j)

### Output generation {#output-generation}

At the final stage, the system collects all valid [action paths](#bookmark=id.2cnndstbwd1u) generated from the evaluated [machine assignment plans](#bookmark=id.uuwoyfjyeme6). Each [action path](#bookmark=id.2cnndstbwd1u) represents a fully defined, executable production scenario that fulfills the original [abstract production process](#bookmark=id.ktprva1s4kki).

The output includes, for each [action path](#bookmark=id.2cnndstbwd1u):

* the complete list of [actions](#bookmark=kix.ir6ef7djan8j) assigned to specific [machines](#bookmark=id.us1pvx2opje4),  
* all associated layout configurations and [mid-process cuts](#bookmark=id.ybjmnriuua23),  
* calculated values such as run count, estimated time, and estimated cost for each [action](#bookmark=kix.ir6ef7djan8j),  
* and a totalized production time and cost for the [entire path](#bookmark=id.2cnndstbwd1u).

These outputs can be used to:

* compare alternative production scenarios,  
* select the optimal path based on cost or time,  
* and generate downstream instructions for production execution or quotation systems.

**Input:** enriched [action paths](#bookmark=id.2cnndstbwd1u)

**Output:** full set of production-ready [action paths](#bookmark=id.2cnndstbwd1u) with time and cost data

## Layout and Cut Planning (Pre-cut Phase)

This is the most complex and critical part of the application logic. It is responsible for calculating all physically valid [sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.1t6o2moxn6gc), [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.yywhdcgtw8ii), and [pose layout](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.e26wcdx2jx4a) configurations that comply with the [machine constraints](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.zmtkgbebxvf) defined in a given [action path](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2cnndstbwd1u) — before the [final product cut](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.5kigvheyrsj2).

The goal is to determine:

* how the product's [pose layout](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.e26wcdx2jx4a) can be multiplied and placed on a [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9),  
* how [zones](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) can be multiplied into [sheets](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6),  
* how [mid-process cuts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.ybjmnriuua23) must be applied to transform one sheet size into another between [machines](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.us1pvx2opje4),  
* and how each layout configuration traces back to previous layout states.

The system evaluates all possible layout variants using [exhaustive grid fitting](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2inl7iqz8xiy), and for each, it builds a corresponding [action backtrace tree](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.ao8kn3es00fc), which represents all valid upstream [action paths](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2cnndstbwd1u) and cut sequences that can lead to the current layout.

This planning logic operates strictly in the [pre-cut phase](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.uw41xbjgmyz8) — layout operations do not continue beyond the [final product cut](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.5kigvheyrsj2).

### Overall algorithm steps of the [pre-cut phase](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.uw41xbjgmyz8)

The layout and cut planning logic follows a recursive upstream strategy: starting from the [root action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.iho5bca9gcmx) (the last machine before the final product cut), the algorithm traces backward through each preceding [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) to determine valid input [sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) sizes, layout configurations, and required [mid-process cuts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.ybjmnriuua23).

The algorithm works **backward**, starting from the [root node](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.iho5bca9gcmx) of the [action backtrace tree](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.ao8kn3es00fc) and moving upstream through each [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) in the action path. By calculating layouts backward, each [actions’s](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) valid [sheet layouts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.1t6o2moxn6gc) define what sheet formats must be produced by the preceding action.

1. [Determine](#bookmark=kix.m0p7ro1xi2me) the valid [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep)  
2. Determine the valid [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep) for the [root action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.iho5bca9gcmx) based on the valid [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep)  
3. [Calculate the valid target](#bookmark=kix.z9iz5oh5w2rh) [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep) for the preceding [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) using the [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) dimensions of the current [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg)  
4. Compute the required [mid-process cuts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.ybjmnriuua23) between the preceding and current [actions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg)  
5. If the preceding [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) is not a [leaf node](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.cyyta7zflin4) (the first [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg)), update the current [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) to the preceding [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg) and repeat from step 3\.

### Determining [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) dimensions

[Zones](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) serve as an intermediate layout unit between individual [poses](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.dn2qgc42v0lh) and [sheets](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6).

By calculating valid [pose layouts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.e26wcdx2jx4a) — [exhaustive grid fittings](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2inl7iqz8xiy) of [poses](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.dn2qgc42v0lh) — within a [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9), the system avoids the need to compute [pose layouts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.e26wcdx2jx4a) across the entire [sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) surface. Instead, it only needs to determine valid [zone layouts](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.yywhdcgtw8ii), which is computationally more efficient and modular.

#### Maximum [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) size

The dimensions of a [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) must not exceed

* the dimensions of the [press sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.5yd117q4r7cf) size it will be placed on,  
* the most restrictive maximum input size constraint across **all [machines](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.us1pvx2opje4) involved in the [pre-cut phase](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.uw41xbjgmyz8)**

This evaluation is performed for both rotated and unrotated orientations.

#### Minimum [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) size

The dimensions of a [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) must not be smaller than

* the dimensions of a single [pose](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.dn2qgc42v0lh)

whether rotated or unrotated.

#### Valid [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) sizes

Any [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) size that falls between the defined minimum and maximum [zone](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep) is considered valid, provided that it can accommodate at least one valid [pose layout](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.e26wcdx2jx4a) – a single [exhaustive grid-fitting](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2inl7iqz8xiy) configuration of poses, considering the [poses](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.dn2qgc42v0lh) in either rotated or unrotated orientation.

#### Determining [cut shee](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6)t dimensions

Unlike the size of a zone, the size of a cut sheet may change during the production process, as it may be cut and/or trimmed.

#### Maximum [cut shee](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6)t size

The dimensions of a [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) must not exceed

* the dimensions of the [press sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.5yd117q4r7cf) size it will be placed on,  
* the strictest maximum size constraints of the [machine](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.us1pvx2opje4) used in the next [action](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.i0pznlc0r7dg)

whether rotated or unrotated.

#### Minimum [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) size

The dimensions of a [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) must always be larger than or equal to

* the dimensions of a single [pose](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.dn2qgc42v0lh)  
* the strictest minimum size constraints across **all subsequent [machines](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.us1pvx2opje4) involved in the [pre-cut phase](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.uw41xbjgmyz8)**

#### Valid [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) dimensions

Any [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) size that falls between the defined minimum and maximum [cut sheet](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.qd1ud4bprmb6) [dimensions](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#heading=h.gibrgktnd6ep) is considered valid, provided that it can accommodate at least one valid [zone layout](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.yywhdcgtw8ii) – a single [exhaustive grid-fitting](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.2inl7iqz8xiy) configuration of [zones](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9), considering the [zones](https://docs.google.com/document/d/1tYt7rWPaz_ksmTy6hIJbkN5daASSNg9G6H96Gpjxf4M/edit?tab=t.0#bookmark=id.gfmlm071olh9) in either rotated or unrotated orientation.  
