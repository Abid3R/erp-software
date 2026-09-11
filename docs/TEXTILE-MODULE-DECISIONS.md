# Textile Module — Design Decisions & Why

This document explains **what** was added to turn the generic manufacturing engine into a
proper **textile (knit → dye → finish)** module, and **why** each decision was taken. It is
written for a non-developer reviewer (your superior) as well as future developers.

> TL;DR — We kept the existing costing/inventory/accounting engine untouched and *layered*
> textile behaviour on top: real knitting/dyeing spec fields, the Bangladesh **knitting
> sub-contract** (job-work) flow, and a **master production schedule** that links a customer
> order to its production stages. Everything is opt-in and non-breaking.

---

## 1. A separate "Textile" section

**Decision.** The textile screens (Process Orders, Process Types, Machines, Lab Dips,
Batches, Quality Inspections) plus the new **Production Plans** and **Knitting Sub-contracts**
now live under their own top-level **Textile** navigation group, separate from the generic
BOM-based **Manufacturing** group.

**Why.**
- Textile production is *process-based* (knit → dye → finish, each a stage that transforms
  fabric), not *assembly-based* (a bill of materials producing one item). Mixing them in one
  menu confused users.
- Keeping the generic `ManufacturingOrder` (BOM) in **Manufacturing** means nothing existing
  breaks — factories that assemble non-textile goods keep their flow.
- A dedicated section lets us show textile-specific language ("Grey Fabric", "GSM", "Gauge",
  "Lab Dip") without polluting the general manufacturing screens.

**Non-breaking guarantee.** No table, route, or resource was renamed or deleted — only the
`navigationGroup` label on the textile resources changed, plus new resources were added.

---

## 2. How process specifications (knitting/dyeing details) are stored

A knitting order card in a real mill carries fields like: fabric composition, GSM, fabric
width ("dia"), machine diameter, gauge, stitch length, yarn count, quality/grade. A dyeing
order carries: dyeing process (reactive/disperse/pigment), liquor ratio, temperature, shade %,
etc.

**Decision.** Each `ProcessType` gets a **category** (`Knitting`, `Dyeing`, `Finishing`,
`Printing`, `Other`). Every process order stores its technical parameters in a single
`specifications` **JSON column**, and the data-entry form shows **category-specific fields**
(the knitting card when the process is knitting, the dyeing card when it is dyeing). The few
attributes that are always meaningful and printed on documents — fabric composition, GSM,
fabric width, colour — are also mirrored into first-class columns for easy reporting.

**Why JSON + a few typed columns (a hybrid), not 20 separate columns.**
- Knitting needs `machine_diameter`, `gauge`, `stitch_length`; dyeing needs `liquor_ratio`,
  `temperature`, `shade_percentage`. Giving each its own column would leave every knitting row
  with empty dyeing columns and vice-versa — a sparse, ugly table.
- JSON keeps the schema clean and lets a mill add a new parameter (e.g. "elastane %") without
  a database migration — matching the existing "configurable process engine" philosophy.
- The always-relevant, must-print fields (composition, GSM, width, colour) are *also* stored as
  real columns so reports and PDFs can filter/sort on them quickly.

**Trade-off accepted.** You cannot run a fast SQL filter on a *pure*-JSON field (e.g. "all
orders with gauge 24"). That is acceptable: those are shop-floor spec details, not reporting
dimensions. The reporting dimensions were promoted to real columns.

---

## 3. Knitting sub-contract (job-work) — the Bangladesh practice

Most Bangladeshi knit factories **do not own every knitting machine**. They send yarn to an
outside knitter ("sub-contractor"), who knits it for a **service charge per KG** and returns
grey fabric. Your example:

| Field | Example |
|---|---|
| Specification | 80% Cotton 20% Polyester / 280–290 GSM / 72" Open / Combed |
| Machine Dia / Gauge / Stitch Length | 30 / 24 / 4.1+5.0+0.5 |
| Sub-contractor (supplier) | SUP-000010 — Acme International |
| Yarn quantity | 2,000.00 KG |
| Service charge item | NS-00003 — "Service Charge for Knitting" |
| Bill qty × service rate | 2,000 KG × 25.00 = 50,000 |
| Currency | BDT |

**Decision.** Sub-contracting is a **mode** of a process order (`in_house` vs `subcontract`),
not a separate parallel engine. A sub-contract order:
1. **Issues the yarn** to the sub-contractor — the yarn leaves main stock into Work-in-Progress
   (exactly like an in-house issue), so its value is not lost.
2. **Records the knitting service charge** = `bill_qty × service_rate` (2,000 × 25 = 50,000
   BDT). This posts **Dr Work-in-Progress / Cr Accounts Payable** with the **sub-contractor as
   the party**, so the mill owes the knitter 50,000 and the fabric absorbs that cost.
3. **Receives the grey fabric** back into stock at its true cost (yarn + knitting charge), with
   full batch traceability (grey batch → yarn lot).
4. The payable is then settled through the **existing supplier-payment flow** (Accounts
   Payable), so no new payment system is introduced.

**Why a "mode", not a new document.**
- The sub-contract still *issues materials, adds a conversion cost, and receives output* — the
  exact three steps the process engine already does. Re-using it means one costing path, one
  WIP account, one traceability mechanism — no duplicate logic to keep in sync.
- The only real difference is **who the conversion cost is owed to**: an internal accrual for
  in-house work vs a **real supplier payable** for sub-contracted work. That is a one-line
  change in the posting (credit `payable` with the supplier as party instead of
  `accrued_overhead`).

**Why the service item (NS-00003).** The service charge is billed against a **non-stock
service product** so it appears on the sub-contractor's bill and in purchase/expense reports
with a proper description, without ever moving as inventory. A new `is_service` flag on products
marks these so they never affect stock.

**Accounting summary (sub-contract knitting of 2,000 KG @ 25):**

| Step | Debit | Credit |
|---|---|---|
| Issue yarn to knitter | WIP | Inventory (yarn) |
| Record knitting charge 50,000 | WIP | Accounts Payable — Acme International |
| Receive grey fabric | Inventory (grey fabric) | WIP |
| Pay the knitter | Accounts Payable | Bank/Cash |

The grey fabric's unit cost = (yarn cost + 50,000) ÷ good KG received. The books stay balanced
and the mill's liability to the knitter is tracked like any other supplier.

---

## 4. Master production schedule (Time & Action) linked to the order

A mill plans an order as a **sequence of dated stages** on specific machines/lines — this is the
"Time & Action" (T&A) calendar / production plan. It answers: *for this customer order, when
does knitting start, when must dyeing finish, are we on track?*

**Decision.** A new **Production Plan** links to a **Sales Order** and holds an ordered list of
**stages** (Knitting, Dyeing, Finishing, …), each with: planned quantity, planned start/end
dates, assigned machine, and a status. Each stage can **spawn a real Process Order** with one
click, and the actual produced quantity rolls back up so the plan shows planned-vs-actual
progress.

**Why link to the Sales Order (not invent a new order type).**
- The customer order already carries the product, quantity, and delivery date — the plan's
  targets are *derived* from it, so planners don't retype anything.
- "Generate plan from sales order" reads the order lines and proposes the standard stage
  sequence; the planner adjusts dates/machines and confirms.

**Why stages spawn process orders (rather than being the same thing).**
- The **plan** is the intent ("dyeing should run 5–7 Sep, 460 KG, on DYE-01"); the **process
  order** is the execution (materials issued, costs, output, QC). Keeping them separate means a
  stage can be re-planned or split into several actual runs without corrupting the schedule,
  and the schedule survives even if a run is cancelled and redone.
- Progress is a computed roll-up (Σ produced on linked process orders ÷ planned), so the plan
  never stores a number that can drift out of sync.

---

## 5. Lab dip & dyeing — proper colour-development fields

**Decision.** The lab dip (colour approval) gains real dye-house fields: dyeing process type
(reactive/disperse/pigment/direct), substrate/fabric type, **liquor ratio**, **temperature**,
**time**, **pH**, **shade %**, and the wash/rubbing/light **fastness** grades, plus the
existing colour name / Pantone reference / recipe / sample. The dyeing process order references
the *approved* lab dip and copies its shade/recipe onto the run.

**Why.** A lab dip is the contract for a colour; bulk dyeing must reproduce it. Capturing the
recipe parameters and fastness results makes the approval meaningful and gives QC something to
verify bulk against. These live on the lab dip (colour master) and are surfaced read-only on the
dyeing order so the dyer follows the approved recipe.

---

## 5b. Specification / recipe masters (define once, auto-fill, print)

**Decision.** Each fabric product can carry a **Fabric Specification master** (`ProductSpecification`,
one per product): composition, GSM, width, and the knitting parameters (machine dia, gauge, stitch
length, yarn count, fabric type, quality). When that product is chosen as the output on a knitting
process order **or** a sub-contract, the fabric fields **auto-fill** from the master. The lab dip is
the equivalent master for colour: selecting an approved lab dip on a dyeing order auto-fills the
colour and dye-house parameters (process, liquor ratio, temperature, shade %).

Both masters are **printable operator sheets** — `Textile → Fabric Specifications → Spec sheet` and
`Textile → Lab Dips → Recipe sheet` — so the value handed to the machine operator is a single page
they read while setting the machine, exactly matching what the order will produce.

**Dyeing has its own master too.** Knitting's master is the **Fabric Specification** (per fabric);
dyeing's is split into two, matching Bangladesh practice: the **Dyeing Specification** (`DyeingSpecification`,
per dyed-fabric product) is the *standard dyeing program* — process, liquor ratio, temperature, shade,
fastness + the dyes/chemicals recipe — reused across lots; the **Lab Dip** is the *shade approval* for a
specific colour/buyer and can override the standard. A dyeing order fills from the lab dip if one is
selected, otherwise from the product's dyeing specification (recipe precedence: lab dip → dyeing spec →
fabric spec). Both print as operator recipe sheets.

**Why a per-product master (not re-typing each order).**
- The spec is a property of the fabric, not of one order — the same grey fabric is knitted the same
  way every time. Storing it once removes re-entry and the transcription errors that come with it.
- Auto-fill is a *convenience copy*, not a hard link: the order keeps its own snapshot, so a
  one-off deviation on a single run doesn't rewrite the master (and vice-versa).
- The lab dip already *is* the colour recipe master, so dyeing reuses it rather than duplicating a
  second recipe store.

## 5c. Material requirement (consumption / BOM) against the order quantity

A spec sheet says *how* the fabric is made; it does **not** say *how much* material an order
needs. In Bangladesh those are two documents — the spec sheet and the **consumption / BOM /
requirement sheet**. This module adds the second.

**Decision.** Each recipe holder (a fabric specification for knitting, a lab dip for dyeing)
carries **consumption lines** (`RecipeConsumption`): a material and its per-output-unit rate, in
the dosing basis the trade actually uses:

| Basis | Formula | Used for |
|---|---|---|
| **per_unit** | `outputQty × rate` | yarn (kg yarn per kg fabric) |
| **percent_owf** | `outputQty × rate ÷ 100` | dyes (% on weight of fabric) |
| **g_per_litre** | `(outputQty × liquorFactor) × rate ÷ 1000` | salt, soda, auxiliaries (g/L of bath) |

`liquorFactor` is parsed from the lab dip's liquor ratio (`"1:8"` → 8 L of bath per kg of goods).
Every line then adds its **process loss / wastage %** on top — knitting loss, dyeing loss — because
BD costing never assumes a clean 1:1.

On a process order or sub-contract, **"Calculate from recipe"** reads the planned quantity, pulls
the recipe (fabric spec for knitting, lab dip for dyeing), computes each material, and fills the
input lines — keeping any manually-added input (e.g. the grey-fabric substrate on a dyeing order).

**Worked example (seeded):**
- Knit 400 kg grey → **420 kg** cotton yarn (1.0/unit + 5% knitting loss).
- Dye 460 kg at liquor 1:8 → **13.8 kg** reactive dye (3% owf) + **147.2 kg** salt (40 g/L) +
  **55.2 kg** soda ash (15 g/L).

**Why this shape.**
- The rate lives on the **recipe master**, defined once; the order just supplies the quantity —
  matching how a mill quotes and issues materials.
- Three dosing bases cover the real chemistry: yarn is weight-proportional, dyes are owf, and bath
  chemicals depend on water volume (liquor ratio), not fabric weight directly.
- Wastage is per-line, so knitting loss and dyeing loss are modelled independently and flow into
  the issued quantity (and therefore the cost).

The calculation is a pure, unit-tested service (`App\Domain\Textile\MaterialRequirement`); the UI
action is a thin wrapper over it.

## 6. What was deliberately **not** changed

- **The costing/inventory/accounting engine.** Sub-contract and in-house both post through the
  same `IssueStock` / `ReceiveStock` / `JournalDraft` / `PostJournal`. No second ledger.
- **Generic BOM manufacturing.** `ManufacturingOrder` is untouched and stays in Manufacturing.
- **Single-currency books.** The sub-contract service currency defaults to BDT; if a foreign
  service currency is ever used it converts to base at post time, exactly like the export module.
- **Existing routes/permissions.** New resources get permissions via `shield:generate`; nothing
  existing is revoked.

---

## 7. File map (where to look)

| Area | Files |
|---|---|
| Specs & category | `process_types.category`, `process_orders.specifications` (JSON) + mirrored columns |
| Sub-contract | `process_orders.mode` + subcontract fields; `App\Actions\Process\RecordSubcontractCharge` |
| Production schedule | `ProductionPlan`, `ProductionPlanStage`; `App\Actions\Process\GenerateProductionPlanFromSalesOrder` |
| Lab dip / dyeing | expanded `lab_dips` columns; `LabDipResource` |
| UI | **Textile** nav group; `ProcessOrderResource`, `KnittingSubcontractResource`, `ProductionPlanResource` |
| Documents | knitting job card + sub-contract work-order PDFs |
| Demo | `TextileDemoSeeder` (in-house chain + a sub-contract order + a production plan) |

*Every figure in the demo is produced by running the real actions, so the screens, reports, and
PDFs reconcile.*
