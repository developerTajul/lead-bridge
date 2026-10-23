# Step Brief 01 - Deal API Store Method

- **STEP ID:** 01
- **MODULE:** Deal
- **GOAL:** Implement the `store` use case for the Deal API (`DealController@store`) following the project architecture.
- **INPUTS:** 
  - Existing controller: `app/Http/Controllers/Api/V1/DealController.php` (`store` method)
  - Deal model and migration: `app/Models/Deal.php`, database tables
- **SKILL / RULES:** `AGENTS.md` and active rule files.
- **ACCEPTANCE:** `DealController@store` is refactored, input validation is extracted, core logic is framework-agnostic, and contract bindings are registered in `AppServiceProvider`.
- **OUT OF SCOPE:** Do not touch `index`, `show`, `update`, or `destroy` methods on `DealController`. Do not build Web controllers or views.