# Module: Auth & User Management (Master Execution Plan)

## 1. Database Schema

Use the exact schema for the tables as provided below. Do not alter or add any columns:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('image')->nullable();
    $table->string('role')->default('MEMBER');
    $table->string('status')->default('ACTIVE');
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});

Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

## 2. Global Execution Rules (For ALL Steps)

- **Brain Integration:** The "How" (Architecture, Validation mechanisms, Design Patterns, Queues/Events) must strictly follow the guidelines defined in our `agents.md` file. Do not deviate from it.
- **Strict Scope:** Execute ONLY the requested step. Provide the complete code and wait for explicit approval before moving to the next step.
- **Database Limits:** Rely only on the provided schema. Do not invent new columns or tables.

## 3. Phase 1: API Development (API First - JWT Authentication)

### Step 1: Registration API

**Business Requirements:**

- Process new user registration. Ensure all incoming data (name, email, password) is properly validated.
- The newly created user must be assigned the default role 'MEMBER' and the status 'PENDING'.
- Upon successful registration, send a "Welcome Email" asynchronously.
- We will use Mailtrap for email testing. Provide the dummy `.env` variables for Mailtrap.

### Step 2: Login API

**Business Requirements:**

- Process user login. Validate the incoming credentials (email, password).
- Ensure the system handles invalid credentials securely and gracefully.
- Upon successful verification, generate and return a secure JWT (JSON Web Token) for subsequent API requests.
- Provide any necessary `.env` variables or setup commands required for the JWT implementation.

### Step 3: Password Recovery API

**Business Requirements:**

- Process password reset link requests. Validate the user's email.
- Send the password reset link asynchronously via email (using Mailtrap).
- Process the actual password reset using the token, ensuring the new password is properly validated and updated in the database.

### Step 4: Role Assignment API

**Business Requirements:**

- Provide an endpoint for Admins to view the list of registered users.
- Allow Admins to approve users (change status from 'PENDING') and assign specific roles (e.g., Sales Representative, Admin).
- Protect this endpoint using JWT, ensuring only users with the 'Admin' role can perform these actions.

### Step 5: User Profile API

**Business Requirements:**

- Provide an endpoint for an authenticated user to view their own personal profile information.
- Allow the user to update their profile details (e.g., name, image uploading). Ensure the inputs are validated.
- Protect this endpoint using JWT so users can only modify their own data.

## 4. Phase 2: Web Development (UI & Views)

_(Do NOT start this phase until Phase 1 is fully completed and approved)_

### Step 6: Registration & Login Web Views

**Business Requirements:**

- Build the user interface (forms) for registration and login.
- Connect these interfaces to the authentication logic.

### Step 7: Password Recovery Web Views

**Business Requirements:**

- Build the user interface for requesting a password reset link.
- Build the interface for submitting the new password using the reset token.

### Step 8: Admin Dashboard Web Views

**Business Requirements:**

- Build the administrative UI to list all users.
- Build the interface for the Admin to approve statuses and manage assigned roles.

### Step 9: User Profile Web Views

**Business Requirements:**

- Build the UI for authenticated users to view, edit, and manage their profile details, including image uploads.
