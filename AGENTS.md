ARCHITECTURAL MANDATE

The system MUST strictly follow Hexagonal Architecture (Ports and Adapters).

Domain logic MUST remain isolated from infrastructure and framework constraints.

Strict typing (declare(strict_types=1);) and the "final" keyword MUST be used for all implementation classes where applicable.

BEHAVIORAL CONSTRAINTS

STRICT SCOPE ADHERENCE

You MUST NOT modify any code, logic, or files outside the explicit instructions provided in the user prompt. Never make assumptions, guess intentions, or apply unprompted fixes.

If you believe an out-of-scope modification is absolutely necessary for code correctness, security, or stability, you MUST halt execution, notify the user with the exact reasoning, and wait for explicit permission before making any changes.

ANTI-HALLUCINATION AND STRICT VERIFICATION PROTOCOL (CRITICAL)

The AI Agent MUST NEVER output a self-verification checklist without strictly auditing the actual generated code.

Any claim in the checklist MUST perfectly match the generated code syntax. False positives are strictly prohibited.

The AI MUST actively cross-check actual code diffs against verification claims. Do not assume a feature was implemented just because it was requested.

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
    - Keep descriptions to 1–2 lines unless explaining genuinely complex business logic that needs more context.

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
```

PHP 8.X STANDARDS AND CONSTRUCTOR PROPERTY PROMOTION

When PHP 8 constructor property promotion is requested or required, the AI MUST NOT use traditional property declarations above the constructor.

The AI MUST NOT use variable assignments inside the constructor body.

Dependencies MUST be declared directly within the constructor signature exactly like this: public function \_\_construct(private readonly DependencyType $dependency) {}.

ERROR HANDLING AND DEFENSIVE PROGRAMMING

Infrastructure layers MUST catch framework-specific failures (e.g., storeAs returning false) and throw domain-agnostic or standard RuntimeExceptions.

Silent failures are strictly forbidden.

INFRASTRUCTURE STRING ENCAPSULATION MANDATE
The Application layer (Services) MUST NEVER contain inline hardcoded infrastructure strings (e.g., file storage directory names). Any such string value MUST be defined as a private constant at the top of the Service class (e.g., private const THUMBNAIL_DIRECTORY = 'categories';) and referenced via self::CONSTANT_NAME. This ensures a single source of truth and keeps the logic clean without over-engineering the Domain layer for simple localized configurations.

COLLECTION MAPPING PREFERENCE
Location: Infrastructure / Repository Layer

When converting Eloquent paginators or models to Domain Entities, strictly avoid using native PHP array_map(). Instead, ALWAYS use Laravel Collection's map() method chained with all(). This ensures cleaner syntax via method chaining while strictly maintaining a framework-agnostic output (plain PHP array) for the Application/Service layer.

Canonical Pattern:

```php
$entities = $laravelPaginator
    ->getCollection()
    ->map(fn($model) => $this->mapper->toEntity($model->toArray()))
    ->all();
```

ROUTE DEFINITION MANDATE
Location: routes/web.php and routes/api.php

All CRUD and module routing MUST be declared through Laravel's grouping and resource helpers. The AI MUST NOT register individual, verbose, per-verb route lines for a module's CRUD surface.

- Shared concerns (URL prefix, route-name prefix, middleware) MUST be hoisted into a single Route::middleware()->prefix()->name()->group() wrapper instead of being repeated on every line.
- Standard seven-action CRUD MUST use Route::resource() (or Route::apiResource() for API endpoints). Do NOT hand-write index/create/store/edit/update/destroy routes.
- When only a subset of actions is needed, constrain the resource with ->only([...]) or ->except([...]) rather than falling back to individual route lines.
- Non-CRUD or genuinely one-off endpoints (e.g. login, logout, a custom action) MAY be declared individually, but MUST still sit inside the appropriate middleware/prefix/name group.

Example Before:

```php
Route::get('/admin/brands', [BrandController::class, 'index'])->name('admin.brands.index');
Route::get('/admin/brands/create', [BrandController::class, 'create'])->name('admin.brands.create');
Route::post('/admin/brands', [BrandController::class, 'store'])->name('admin.brands.store');
Route::get('/admin/brands/{brand}/edit', [BrandController::class, 'edit'])->name('admin.brands.edit');
Route::put('/admin/brands/{brand}', [BrandController::class, 'update'])->name('admin.brands.update');
Route::delete('/admin/brands/{brand}', [BrandController::class, 'destroy'])->name('admin.brands.destroy');
```

Example After:

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('brands', BrandController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('users', UserController::class);
});
```

## Global Execution Policy: Interactive Alert

1. Trigger Condition:
    - Every time the system or assistant requires user confirmation, explicit permission, or input to proceed, an alert must be triggered immediately.

2. Technical Execution (Windows):
    - Play an audible alert sound alongside the visual notification:
        - powershell.exe -Command "[System.Media.SystemSounds]::Asterisk.Play(); Start-Sleep -Milliseconds 300; [System.Media.SystemSounds]::Asterisk.Play()"
    - Show a Windows toast notification to alert the user:
        - powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.MessageBox]::Show('Action required — please respond', 'Claude Code', [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Question)"
    - Both commands MUST run for every trigger condition - the sound command first, then the toast.

CODING STANDARDS

Early Return / Guard Clauses

Rule: Use Early Return / Guard Clauses instead of complex ternary operators for conditional logic and HTTP responses.

Reason: Enhances human-readability, flattens the code structure, and prevents nested conditions.

Example Before:

```php
return $result->isFailure() ? back()->with('error', $message) : redirect()->route('index');
```

Example After:

```php
if ($result->isFailure()) {
    return back()->with('error', $message);
}
return redirect()->route('index');
```

By establishing this Agents.md file, the AI acknowledges and binds itself to these exact constraints for the entirety of the project lifecycle.

---

The extended coding rules and implementation guides live in modular files under .claude/rules/. See the rule file index below for loading guidance when needed.

RULE FILE INDEX

1. .claude/rules/01-universal-philosophy.txt
   Load when: Always relevant - core architecture principles (Golden Rules, Hexagonal model, layer boundaries, SOLID/KISS, Definition of Done).

2. .claude/rules/02-framework-adapter-laravel.txt
   Load when: Working with Laravel specifics - directory structure, Shared Kernel, naming conventions, autoloading, canonical request flow, testing rules.

3. .claude/rules/03-module-scaffolding.txt
   Load when: ONLY when scaffolding a NEW module - execution guardrails, FULL-BASELINE MANDATE, SlugGenerator auto-generation, mandatory member rules, EMPTY-SKELETON GUARD.

4. .claude/rules/04-crud-pipeline.txt
   Load when: Implementing CRUD / use-case logic on an already-scaffolded module.
