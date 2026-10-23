# Step Brief 03 - Deal API Update and Destroy Methods

- **STEP ID:** 03
- **MODULE:** Deal
- **GOAL:** Implement the `update` (with row locking/stamping close dates for Won/Lost stages) and `destroy` use cases for the Deal API.
- **INPUTS:** Step 01 & 02 artifacts, `app/Http/Controllers/Api/V1/DealController.php` (`update` and `destroy` methods).
- **SKILL / RULES:** `AGENTS.md` and active rule files.
- **ACCEPTANCE:** `DealController@update` and `destroy` are fully refactored, removing direct Eloquent/DB facade calls from the controller. All 5 API endpoints are complete and functional.
- **OUT OF SCOPE:** Web interface (`routes/web.php`, Blade views).