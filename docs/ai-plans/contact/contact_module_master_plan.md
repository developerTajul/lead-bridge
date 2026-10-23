# Module: Contact Management (Master Execution Plan)

## 1. Database Schema

Use the exact schema for the tables as provided below. Do not alter or add any columns:

```
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
    $table->string('first_name');
    $table->string('last_name')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('job_title')->nullable();
    $table->timestamps();
});
```

## 2. Global Execution Rules (For ALL Steps)

* **Brain Integration:** The "How" (Architecture, Validation mechanisms, Design Patterns, Queues/Events) must strictly follow the guidelines defined in our `agents.md` file. Do not deviate from it.

* **Strict Scope:** Execute ONLY the requested step. Provide the complete code and wait for explicit approval before moving to the next step.

* **Database Limits:** Rely only on the provided schema. Do not invent new columns or tables.

* **Data Transfer Object (DTO):** All incoming contact data must pass through a ContactDTO before interacting with the Service or Repository layer.

* **Relationship Handling:** When creating or updating a contact, ensure the relationship with the `company_id` is handled securely.

## 3. Phase 1: API Development (Contact Management)

### Step 1: Contact Creation API (Store)

**Business Requirements:**

* Process incoming contact data (created manually or automatically during Lead Conversion).
* Validate inputs: `first_name` is required. `email` (if provided) must be a valid email format.
* Optionally link the contact to a specific company via `company_id`.
* Save the contact record in the database.

### Step 2: Contact Listing & Filtering API (Index)

**Business Requirements:**

* Return a paginated list of contacts for the frontend.
* Include the associated company data (eager loading) to minimize N+1 query issues.
* Allow searching by contact `first_name`, `last_name`, `email`, and `phone`.
* Allow filtering by `company_id`.

### Step 3: Contact Update & Details API (Show, Update)

**Business Requirements:**

* Provide an endpoint to view details of a specific contact.
* Allow users to update the contact's details (`first_name`, `last_name`, `email`, `phone`, `job_title`, `company_id`).
* Ensure input validation is applied during the update process.

## 4. Phase 2: Web Development (UI & Views)

*(Do NOT start this phase until Phase 1 is fully completed and approved)*

### Step 4: Contact Management Dashboard Web Views

**Business Requirements:**

* Build the user interface to list all contacts in a clean table view, showing their associated company name.
* Implement search and filtering functionalities in the UI.
* Connect this interface to the Contact Listing API.

### Step 5: Contact Creation & Editing Web Views

**Business Requirements:**

* Build the form for manual contact entry, including a dropdown/search mechanism to select a Company.
* Build the interface for users to update existing contact profiles.