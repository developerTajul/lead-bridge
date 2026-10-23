# Module: Lead Management (Master Execution Plan)

## 1. Database Schema

Use the exact schema for the tables as provided below. Do not alter or add any columns:

```php
Schema::create('leads', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name')->nullable();
    $table->string('email')->nullable(); // Either email or phone must be provided
    $table->string('phone')->nullable();
    $table->string('source')->default('MANUAL'); // e.g., WEBSITE, FACEBOOK, WHATSAPP, MANUAL
    $table->string('status')->default('NEW'); // e.g., NEW, CONTACTED, QUALIFIED, LOST, CONVERTED
    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

## 2. Global Execution Rules (For ALL Steps)

- **Brain Integration:** The "How" (Architecture, Validation mechanisms, Design Patterns, Queues/Events) must strictly follow the guidelines defined in our `agents.md` file. Do not deviate from it.
- **Strict Scope:** Execute ONLY the requested step. Provide the complete code and wait for explicit approval before moving to the next step.
- **Database Limits:** Rely only on the provided schema. Do not invent new columns or tables.
- **Data Transfer Object (DTO):** All incoming lead data must pass through a LeadDTO before interacting with the Service or Repository layer.
- **Data Isolation:** Lead queries must automatically filter based on the authenticated user's role (Admin sees all, Sales Rep sees only leads where `assigned_to` matches their ID).

## 3. Phase 1: API Development (Lead Ingestion & Management)

### Step 1: Lead Capture API (Store)

**Business Requirements:**

- Process incoming lead data from various sources (Manual entry, Webhook from WP Contact Form 7, Facebook Ads).
- Validate inputs: `first_name` is required. Either `email` or `phone` must be present.
- Save the lead with the default status 'NEW'.

### Step 2: Lead Listing & Filtering API (Index)

**Business Requirements:**

- Return a paginated list of leads for the Dashboard and Leads page.
- Enforce strict role-based data isolation (Admins view all, Sales Reps view only assigned leads).
- Allow filtering by `status` and `source`, and searching by name, email, or phone.

### Step 3: Lead Assignment & Update API (Update)

**Business Requirements:**

- Allow Admins to assign a lead to a specific Sales Representative (update `assigned_to`).
- Allow assigned Sales Reps to update the lead's `status` (e.g., from 'NEW' to 'CONTACTED') and `notes`.
- Prevent invalid status transitions (e.g., a 'CONVERTED' lead cannot be changed back to 'NEW' through this endpoint).

## 4. Phase 2: Web Development (UI & Views)

_(Do NOT start this phase until Phase 1 is fully completed and approved)_

### Step 4: Lead Management Dashboard Web Views

**Business Requirements:**

- Build the user interface to list all leads (Table or List view).
- Implement filtering (by status, source) and search functionalities in the UI.
- Connect this interface to the Lead Listing API.

### Step 5: Lead Creation & Editing Web Views

**Business Requirements:**

- Build the form for manual lead entry.
- Build the interface for Admins to assign leads to Sales Reps.
- Build the interface for Sales Reps to update lead statuses and add notes.