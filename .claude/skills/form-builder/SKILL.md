# Form Builder Skill (Delivery Layer Scaffolding)

## Description

This skill automatically generates the full vertical Laravel delivery layer required to render a form based STRICTLY on a provided database schema. It strictly follows the logical request lifecycle (Route -> Controller -> View) and prepares the user interface gateway without implementing any data-persistence logic.

## Context & Rules

- **Source of Truth:** You MUST analyze the provided SQL database schema or field list exactly. Explicitly skip primary keys (e.g., `id`), auto-managed timestamps (e.g., `created_at`, `updated_at`), soft deletes (e.g., `deleted_at`), and domain-generated fields (e.g., `slug`, `uuid`, `token`). Do not invent or assume extra fields outside of this context.
- **Empty-Skeleton Guard:** The controller must act only as a view renderer at this stage. Do NOT write `store()` or data-processing logic yet.
- **Form Method Policy:** Forms must strictly use the POST method, preparing them for future data submission.

## Execution Steps

1. **Route Architecture Wiring (`routes/web.php`):**
    - Provide the explicit routing snippet to be appended to the route file.
    - Map the GET request of the form URI to the Controller's `create` method.

2. **Controller Generation (`App\Http\Controllers\Admin\`):**
    - Generate a thin HTTP Controller named `[Module]Controller.php`.
    - Include an empty constructor ready for future Service dependency injection.
    - Create a single `create()` method that strictly returns the specific Blade view skeleton (e.g., `view('admin.[module].create')`).

3. **Blade Form View Generation (`resources/views/admin/`):**
    - Create a semantic, accessible HTML/Blade form structure.
    - STRICTLY set `enctype="multipart/form-data"` ONLY if the schema includes file/image columns (e.g., thumbnail, image, logo).
    - Always include a hidden CSRF token placeholder (e.g., `{{ csrf_token() }}`).
    - Map columns 1:1 to appropriate HTML5 input types (VARCHAR = text, TEXT = textarea, DECIMAL = number, TINYINT(1)/Enum = select dropdown).
    - Inject Laravel `old()` helpers inside value attributes to preserve state upon validation failure.

## Testing & Verification

- **Temporary Action:** For initial scaffolding, set the form `action` attribute to `#`. This allows the form to render without triggering a `RouteNotFoundException` before the store pipeline is implemented.
- **Visual Feedback:** Include a small alert or comment block inside the view reminding that the route is not yet defined, facilitating easy identification during early UI testing.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks like `php` and `html`) to strictly preserve line breaks, indentation, and formatting. Keep conversational text to an absolute minimum.
