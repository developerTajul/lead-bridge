# Module: Company Management (Master Execution Plan)

## 1. Database Schema

Use the exact schema for the tables as provided below. Do not alter or add any columns:

```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('name')->index();
    $table->string('website')->nullable();
    $table->string('industry')->nullable()->index();
    $table->string('phone')->nullable();
    $table->text('address')->nullable();
    $table->timestamps();
});
```

## 2. Global Execution Rules (For ALL Steps)

* **Brain Integration:** The "How" (Architecture, Validation mechanisms, Design Patterns, Queues/Events) must strictly follow the guidelines defined in our `agents.md` file. Do not deviate from it.
* **Strict Scope:** Execute ONLY the requested step. Provide the complete code and wait for explicit approval before moving to the next step.
* **Database Limits:** Rely only on the provided schema. Do not invent new columns or tables.
* **Data Transfer Object (DTO):** All incoming company data must pass through a CompanyDTO before interacting with the Service or Repository layer.
* **Access Control:** Ensure proper authorization based on the user's role (e.g., all authenticated users can view, but only certain roles can edit or delete).

## 3. Phase 1: API Development (Company Management)

### Step 1: Company Creation API (Store)

**Business Requirements:**

* Process incoming company data (either created manually by a user or automatically during Lead Conversion).
* Validate inputs: `name` is required. `website` (if provided) must be a valid URL format.
* Save the company record in the database.

### Step 2: Company Listing & Filtering API (Index)

**Business Requirements:**

* Return a paginated list of companies for the frontend.
* Allow searching by company `name` and `phone`.
* Allow filtering by `industry`.

### Step 3: Company Update & Details API (Show, Update)

**Business Requirements:**

* Provide an endpoint to view details of a specific company.
* Allow users to update the company's details (`name`, `website`, `industry`, `phone`, `address`).
* Ensure input validation is applied during the update process.

## 4. Phase 2: Web Development (UI & Views)

*(Do NOT start this phase until Phase 1 is fully completed and approved)*

### Step 4: Company Management Web Views

**Business Requirements:**

* Build the user interface to list all companies in a clean table or grid view.
* Implement search and industry filtering functionalities in the UI.
* Connect this interface to the Company Listing API.

### Step 5: Company Creation & Editing Web Views

**Business Requirements:**

* Build the form for manual company entry.
* Build the interface for users to update existing company profiles.