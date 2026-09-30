# DDD Module Scaffolder Skill (Lean Architecture Scaffold)

## Description

This skill scaffolds a new Domain-Driven Design (DDD) module using the exact architectural rules defined in the project's `AGENTS.md` file. It focuses strictly on generating the baseline domain constructs and empty structural skeletons, deferring request-specific DTOs and use-case methods until the features are explicitly built.

## Context & Rules

- **Source of Truth:** You MUST strictly follow the `AGENTS.md` file located in the project root.
- **Framework Agnostic:** The core `Domain` and `Application` layers must not contain any framework-specific helpers or HTTP dependencies.
- **Just-In-Time Core Separation:** - Scaffold ONLY the common baseline artifacts required for general CRUD mapping: Domain Entity, Domain DataObject, and Schema Enums (if applicable).
    - Do NOT generate any Request DTOs (e.g., CreateDTO, UpdateDTO) during this initial scaffold phase. These must be created later alongside their matching features.

## Execution Steps

1. **Directory Generation:** Create the core directory tree for the requested module inside the `core/` directory as outlined in `AGENTS.md` (omitting `Application/DTOs/Requests/` during the initial phase).

2. **Entity Generation (`Domain/Entities/`):**
    - Create the Entity as a PHP 8 `readonly class`.
    - Implement the `__construct()` method using constructor property promotion, accurately mapping every column from the provided schema.

3. **Enum Generation (`Domain/Enums/`):**
    - If the schema contains limited state or choice fields (e.g., status, type), generate the corresponding Enum.
    - MUST implement a `label()` method using a `match` expression to return human-readable strings.
    - MUST implement a static `values()` method returning an array of raw values via `array_column(self::cases(), 'value')`.

4. **DataObject Generation (`Domain/DataObjects/`):**
    - Create the DataObject as a `readonly class` representing the raw persistence input.
    - Implement the `__construct()` method mapping required fields for database mapping.
    - MUST implement a `toArray()` method that maps properties to an associative array suitable for database insertion. Enum properties must be unwrapped using `->value`.

5. **Readability Standards for Array Manipulations (DTO Generation):**
    - Always separate array definitions from functional operations (such as `array_filter` or `array_map`) to ensure clean code and readability.
    - When generating a method that returns a filtered array, first declare a local variable (e.g., `$data`) to hold the complete associative array. Then, apply the functional operation on this variable in a separate return statement (e.g., `return array_filter($data, ...)`).
    - Never inline large arrays directly inside function calls. This ensures maintainability and clarity.

5. **Skeleton Generation (Interfaces & Logic):**
    - Generate empty class/interface skeletons for Contracts, Services, Repositories, and Mappers.
    - Contracts MUST remain blank interfaces with no method signatures.
    - Services, Repositories, and Mappers must only contain their constructors and necessary dependency injections.
    - Any placeholder method required by infrastructure constraints must throw a `\LogicException('Method not implemented')`.

6. **Formatting:** Ensure `declare(strict_types=1);` is at the very top of EVERY generated PHP file and the correct PSR-4 namespaces (`Core\[Module]\...`) are applied.

## Output

Execute the file creation directly using your filesystem tools. Provide a short tree view of the files created.
