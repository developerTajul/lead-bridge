ARCHITECTURAL MANDATE

The system MUST strictly follow Hexagonal Architecture (Ports and Adapters) combined with Clean Architecture and a Rich Domain Model.

Domain logic MUST remain isolated from infrastructure and framework constraints.

Strict typing (declare(strict_types=1);) and the "final" keyword MUST be used for all implementation classes where applicable.

PRIMARY GOAL: FRAMEWORK-PORTABLE CORE

The `Core` folder MUST be movable to any PHP framework or into a WordPress plugin without modification.

- Porting to a new environment MUST only require writing new Adapters (e.g. a `WordPressRepository`) and a new composition root (container bindings).
- If a change requires editing Domain or Application code to support a new framework, the design is wrong and MUST be fixed at the port level.

Layer dependency direction (inward only):

Presentation Adapters (API / Web / Console Command) -> Application (Services) -> Domain (Entities, Value Objects, Domain Events, Ports)
Infrastructure Adapters (Eloquent, WordPress, Queue, Events) -> implement Ports declared in Core

BEHAVIORAL CONSTRAINTS

STRICT SCOPE ADHERENCE

You MUST NOT modify any code, logic, or files outside the explicit instructions provided in the user prompt. Never make assumptions, guess intentions, or apply unprompted fixes.

If you believe an out-of-scope modification is absolutely necessary for code correctness, security, or stability, you MUST halt execution, notify the user with the exact reasoning, and wait for explicit permission before making any changes. In Autonomous Execution Mode, apply the hard-stop rules in `.claude/rules/09-autonomous-execution.txt` (A8) instead.

EXCEPTION (INHERENT WIRING IN-SCOPE): Touching `app/Providers/AppServiceProvider.php` to bind a newly created Contract, or touching `routes/api.php` and `routes/web.php` to register routes for a newly created Controller action in the current step, is inherently IN-SCOPE for that step and does NOT trigger a hard stop or scope violation.

The architectural mandates in this file do NOT authorize refactoring code that the prompt did not ask you to touch. Apply them only to the code you are asked to write or change.

UNIT TESTING POLICY (TEMPORARILY PAUSED)

Unit testing is switched OFF for now so the project can be finished quickly and the concepts can settle first. Testing will be done later, manually and with AI.

- The AI MUST NOT write, generate, scaffold, or require unit, feature, or integration tests, and MUST NOT create files under `tests/`.
- The AI MUST NOT run test suites unless the prompt explicitly asks for it.
- ALL testing rules live in `.claude/rules/07-testing.txt`. Do NOT load or follow that file while this policy is active, unless the prompt explicitly asks for tests. Do not list tests in a Definition of Done or verification checklist, and do not claim them.
- An explicit instruction in the prompt (for example "write a test for X") overrides this pause for that request only.
- The architecture rules are unchanged. Keep the code testable (ports behind `...Contract`, pure Entities, no framework in Core) so tests can be added later without refactoring.
- Only the developer re-enables testing, by saying so explicitly. At that point, load `.claude/rules/07-testing.txt` and remove this policy section.

AUTONOMOUS EXECUTION MODE

When the developer asks the AI to execute a step, a module, or a plan, the AI works in Autonomous Execution Mode. The full procedure is in `.claude/rules/09-autonomous-execution.txt`. Load it at the start of every such run. The essentials:

- Work step by step. Only the current step is in scope. Stop at the end of the range the developer asked for (one step, one module, or the whole plan).
- After each step, self-check by auditing the actual files (never from memory), then write a step report under `docs/ai-reports/<module>/` and update `docs/ai-reports/PROJECT-LOG.md`.
- Do not stop to ask about things that AGENTS.md and the rule files already settle. When no rule settles a question, take the most conservative option that fits the architecture, and record it as a decision in the step report and in `docs/ai-reports/DECISIONS.md`, with a proposed AGENTS.md change. This lets the developer improve AGENTS.md later.
- HALT (play the Interactive Alert and wait) only for hard stops: a missing required input, any change outside the step's scope (with the wiring exception noted above), destructive or irreversible actions, security or secret handling, forbidden commands (migrations, package installs, test runs, git history changes), risky changes to `core/Shared`, or a self-check that still fails after two repair rounds.
- Never edit AGENTS.md, `.claude/rules/`, or skill files unless the step explicitly asks for it. Propose changes in the report instead.
- Never mark a step done without evidence. Report failed, partial, and blocked steps honestly.

ANTI-HALLUCINATION AND STRICT VERIFICATION PROTOCOL (CRITICAL)

The AI Agent MUST NEVER output a self-verification checklist without strictly auditing the actual generated code.

Any claim in the checklist MUST perfectly match the generated code syntax. False positives are strictly prohibited.

The AI MUST actively cross-check actual code diffs against verification claims. Do not assume a feature was implemented just because it was requested.

RICH DOMAIN MODEL MANDATE (SINGLE SOURCE OF TRUTH)

Business logic MUST live in the Domain layer, inside Entities (and Value Objects). Entities are the single source of truth for business rules.

- Entities MUST expose behavior methods that express business intent (e.g. `$activity->publish()`, `$order->cancel()`), not generic setters.
- Invariants, validation of state, and state transitions MUST be enforced inside the Entity. An Entity MUST NOT be created or moved into an invalid state.
- Entities stay `readonly` and immutable. A behavior method enforces the rule and returns a NEW Entity instance. Entities MUST be created through named constructors (e.g. `Activity::create(...)`) and are rebuilt from storage by the Infrastructure EntityMapper.
- Repositories take and return Entities. DTOs or loose arrays MUST NOT be passed from the Service to the Repository.
- Anemic entities (plain data bags with getters/setters only) are strictly forbidden.
- Services MUST NOT contain business rules, conditionals about business state, or calculations that belong to the Entity. If a Service needs to decide something about business state, that decision MUST be a method on the Entity.
- Entities, Value Objects, and Domain Events MUST NOT import anything from the framework (no `Illuminate\*`, no WordPress functions or classes, no ORM annotations).
- Eloquent models and WordPress post/table structures are persistence details. They MUST stay in Infrastructure and MUST be converted to and from Domain Entities through a Mapper.
- Domain Events live in a readonly list on the Entity. Every behavior method MUST pass the existing events to the NEW instance and append any new event (`domainEvents: [...$this->domainEvents, new SomethingHappenedEvent(...)]`), including methods that raise no event, so no event is ever lost. `releaseEvents()` only RETURNS the list; it never clears it (a readonly property cannot be cleared). The Command Service takes the events from the Entity returned by the behavior method BEFORE persisting (`$events = $entity->releaseEvents(); $savedEntity = $repository->update($entity);`). The Entity the repository returns is rebuilt from storage and starts with an empty list, so events MUST NOT be released from it. The Service dispatches `$events` once, through the EventDispatcher port, after a successful commit. Full event and queue rules: `.claude/rules/08-events-and-queues.txt`. The Entity never dispatches events itself.

SERVICE LAYER MANDATE (ORCHESTRATOR, COMMAND/QUERY SEPARATION)

The Application layer (Services) is an orchestrator only. It coordinates Domain objects and Ports. It does not hold business logic.

Services MUST be split by intent (Command Query Separation):

1. Write-side (Command) Services - write-heavy
    - Naming: `<Module>CommandService` (or one final class per use-case, e.g. `CreateActivityService`).
    - Flow: receive input DTO -> load Entity through the Repository port (or create it through the named constructor) -> call the Entity behavior method -> persist through the Repository port -> release recorded Domain Events through the EventDispatcher port -> return a ResponseDTO.
    - Write operations that touch more than one persistence step MUST run inside the project's `TransactionManager` port.
    - Command Services MUST NOT return query-shaped data (lists, paginators, search results).

2. Read-side (Query) Services - read-heavy
    - Naming: `<Module>QueryService` (or one final class per query, e.g. `ListActivitiesService`).
    - Query Services MUST NOT mutate state, record Domain Events, or call write methods on repositories.
    - Query Services MAY use a dedicated read-side Repository/Finder port optimized for reading (filters, pagination, projections). They are not required to hydrate full Entities when only a read model is needed.
    - Query Services MUST return ResponseDTOs (or collections of them), never Entities or Eloquent models.

3. A single Service class MUST NOT mix command and query responsibilities.

RESPONSE DTO MANDATE

The Service layer MUST return ResponseDTOs to all delivery mechanisms (API, Web, Console Command). One Service output serves every presentation adapter.

- ResponseDTOs MUST be `final readonly` classes (PHP 8.2+) containing only plain PHP types (scalars, arrays, other DTOs, DateTimeImmutable). Use constructor property promotion as defined below.
- Services MUST NOT return Entities, Eloquent models, paginators, framework Request/Response objects, or anything that carries framework types.
- Failures MUST be expressed through the project's existing `Result` type wrapping the ResponseDTO (e.g. `Result::success($dto)` / `Result::failure($message)`).
- Mapping ResponseDTO to its final format (JSON resource, Blade view data, CLI output) is the job of the Presentation adapter, not the Service.
- Presentation adapters (Controllers, Console Commands, WordPress admin handlers) MUST stay thin: parse input -> build input DTO -> call the Service -> format the ResponseDTO.

PORTS AND ADAPTERS MANDATE (FRAMEWORK INDEPENDENCE)

Everything the Core needs from the outside world MUST be defined as an interface (Port) inside Core and implemented by an Adapter outside Core.

Required ports (create only the ones the current task needs). Every port is named with the `...Contract` suffix, never `...Interface` or an `I` prefix:

- Repository ports (e.g. `ActivityRepositoryContract`) - Adapters: `EloquentActivityRepository`, `WordPressActivityRepository`.
- `EventDispatcherContract` - Core dispatches plain PHP Domain Events through it. Adapters: Laravel event dispatcher, WordPress `do_action`.
- `QueueContract` - Core defines the job message and handler as plain PHP. Adapters: Laravel Queue, WordPress Action Scheduler or `wp_schedule_single_event`.
- `TransactionManagerContract` - Adapters: Laravel DB transaction, `WpdbTransactionManager`, `CompensatingActionTransactionManager`.
- File storage, clock, logger, and any other infrastructure capability MUST follow the same Port/Adapter pattern.

Rules:

- Core code MUST NOT use framework facades, helpers, or global functions (e.g. `Event::dispatch`, `dispatch()`, `Queue::push`, `now()`, `Storage::`, `do_action`, `wp_schedule_single_event`).
- Domain Events and Queue Jobs MUST be plain PHP classes that do not extend or implement any framework class.
- Framework wiring (container bindings, service providers, WordPress bootstrap) MUST live outside Core, in the framework adapter layer.
- Adapters MUST NOT contain business logic. They only translate between the Core's contract and the framework.
- Core MUST NOT depend on a specific persistence shape. Mappers in Infrastructure convert between storage data and Entities.

STRICT DOCBLOCK MANAGEMENT

The AI MUST adhere to the following rules for all DocBlocks:

- Anti-Pattern Alert: Never write informal, narrative-only class descriptions (e.g., avoiding structures like "This acts as a translation boundary...").

- Mandatory Class Tags: If a class implements an interface, you must include the @implements tag with the Fully Qualified Class Name (FQCN).

- Separation of Concerns: Keep descriptions concise and strictly technical. Avoid conversational explanations within the DocBlock.

- Tone and Style:
    - Write descriptions as a human developer would: concise, practical, and natural.
    - Avoid overly formal, rigid, or textbook-style verbs (e.g., avoid "Orchestrates", "Delegates use-case", "Translates").
    - Use simple, everyday developer terminology (e.g., use "Handles category creation", "Shows the edit form", "Saves the new category").
    - Maintain the strict structural tags (@param, @return, @implements) but keep the descriptive text human-readable and straightforward.
    - Keep descriptions to 1-2 lines unless explaining genuinely complex business logic that needs more context.

Example (Before/After):

```php
// Before:
/**
 * Orchestrates the category creation use-case.
 * @param CreateCategoryRequest $request
 * @return Result
 */

// After:
/**
 * Handles category creation.
 * @param CreateCategoryRequest $request
 * @return Result
 */

PHP 8.X STANDARDS AND CONSTRUCTOR PROPERTY PROMOTION

When PHP 8 constructor property promotion is requested or required, the AI MUST NOT use traditional property declarations above the constructor.

The AI MUST NOT use variable assignments inside the constructor body.

Dependencies MUST be declared directly within the constructor signature exactly like this: public function __construct(private readonly DependencyType $dependency) {}.

ERROR HANDLING AND DEFENSIVE PROGRAMMING

Infrastructure layers MUST catch framework-specific failures (e.g., storeAs returning false) and throw domain-agnostic or standard RuntimeExceptions.

Silent failures are strictly forbidden.

Business rule violations MUST be raised from the Domain (Entity / Value Object) as domain-specific exceptions or returned as failures. They MUST NOT be raised as framework exceptions.

INFRASTRUCTURE STRING ENCAPSULATION MANDATE
The Application layer (Services) MUST NEVER contain inline hardcoded infrastructure strings (e.g., file storage directory names). Any such string value MUST be defined as a private constant at the top of the Service class (e.g., private const THUMBNAIL_DIRECTORY = 'categories';) and referenced via self::CONSTANT_NAME. This ensures a single source of truth and keeps the logic clean without over-engineering the Domain layer for simple localized configurations.

LARAVEL-SPECIFIC CONVENTIONS (MOVED OUT OF THIS FILE)

This file stays framework-agnostic. The Laravel-specific conventions - COLLECTION MAPPING PREFERENCE (how Eloquent models become Entities) and ROUTE DEFINITION MANDATE (grouping and resource rules for routes/web.php and routes/api.php) - live in .claude/rules/02-framework-adapter-laravel.txt (sections 7 and 8). Load that file whenever you touch Eloquent repositories, entity mappers, or route files.
Global Execution Policy: Interactive Alert

    Trigger Condition:

        Every time the system or assistant requires user confirmation, explicit permission, or input to proceed, an alert must be triggered immediately. Every HALT in Autonomous Execution Mode is such a trigger.

    Technical Execution (Windows):

        Play an audible alert sound alongside the visual notification:

            powershell.exe -Command "[System.Media.SystemSounds]::Asterisk.Play(); Start-Sleep -Milliseconds 300; [System.Media.SystemSounds]::Asterisk.Play()"

        Show a Windows toast notification to alert the user:

            powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.MessageBox]::Show('Action required - please respond', 'Claude Code', [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Question)"

        Both commands MUST run for every trigger condition - the sound command first, then the toast.

CODING STANDARDS

Early Return / Guard Clauses

Rule: Use Early Return / Guard Clauses instead of complex ternary operators for conditional logic and HTTP responses.

Reason: Enhances human-readability, flattens the code structure, and prevents nested conditions.

Example Before:
PHP

return $result->isFailure() ? back()->with('error', $message) : redirect()->route('index');

Example After:
PHP

if ($result->isFailure()) {
    return back()->with('error', $message);
}
return redirect()->route('index');

PORTABILITY AND RICH DOMAIN CHECKLIST (VERIFY BEFORE REPORTING DONE)

Before reporting a task as complete, audit the actual generated code against each item that applies. Do not claim an item without confirming it in the code.

    No Illuminate\*, WordPress function, or other framework import exists inside Core Domain or Application files.

    Business rules and state transitions are in the Entity, not in the Service.

    The Service is either a Command Service or a Query Service, not both.

    The Service returns a ResponseDTO (wrapped in Result where failure is possible), not an Entity or Eloquent model.

    Events and queue jobs go through Core ports, and the event/job classes are plain PHP.

    Any new infrastructure capability has a Port in Core and an Adapter outside Core.

By establishing this Agents.md file, the AI acknowledges and binds itself to these exact constraints for the entirety of the project lifecycle.

The extended coding rules and implementation guides live in modular files under .claude/rules/. See the rule file index below for loading guidance when needed.

RULE FILE INDEX

    .claude/rules/01-universal-philosophy.txt
    Load when: Always relevant - core architecture principles (Golden Rules, Hexagonal model, layer boundaries, SOLID/KISS, Definition of Done).

    .claude/rules/02-framework-adapter-laravel.txt
    Load when: Working with Laravel specifics - directory structure, Shared Kernel, naming conventions, autoloading, canonical request flow, Eloquent model-to-Entity mapping (collection mapping), and route definition rules (routes/web.php, routes/api.php).

    .claude/rules/03-module-scaffolding.txt
    Load when: ONLY when scaffolding a NEW module - execution guardrails, FULL-BASELINE MANDATE, SlugGenerator auto-generation, mandatory member rules, EMPTY-SKELETON GUARD.

    .claude/rules/04-crud-pipeline.txt
    Load when: Implementing CRUD / use-case logic on an already-scaffolded module.

    .claude/rules/05-rich-domain-and-services.txt
    Load when: Writing or changing an Entity, Value Object, Command Service, Query Service, or ResponseDTO (Rich Domain Model, CQS, event-carrying entities).

    .claude/rules/06-ports-and-portability.txt
    Load when: Adding a port or adapter, touching the Shared kernel, or preparing core/ for another framework or a WordPress plugin.

    .claude/rules/07-testing.txt
    Load when: ONLY when the developer explicitly asks for tests or re-enables testing. Testing is currently PAUSED (see UNIT TESTING POLICY) - do not load this file otherwise.

    .claude/rules/08-events-and-queues.txt
    Load when: An Entity raises a Domain Event, a Command Service dispatches events or pushes a queue job, or an event listener, queue handler, or event/queue adapter is written or reviewed.

    .claude/rules/09-autonomous-execution.txt
    Load when: ALWAYS, at the start of any step, module, or plan execution (Autonomous Execution Mode): step loop, self-check, reports under docs/ai-reports/, decision recording, and hard stops.
```
