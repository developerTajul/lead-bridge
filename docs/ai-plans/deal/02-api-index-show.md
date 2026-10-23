# Step Brief 02 - Deal API Index and Show Methods

- **STEP ID:** 02
- **MODULE:** Deal
- **GOAL:** Implement the read-side use cases (`index` and `show`) for the Deal API, including relations (`company`, `contact`, `pipeline`, `stage`, `activities`) and filters (`pipeline_id`, `deal_stage_id`).
- **INPUTS:** Step 01 artifacts, `app/Http/Controllers/Api/V1/DealController.php` (`index` and `show` methods).
- **SKILL / RULES:** `AGENTS.md` and active rule files.
- **ACCEPTANCE:** `DealController@index` and `show` are refactored to use query services and response DTOs without mutating state or mixing write logic.
- **OUT OF SCOPE:** `update` and `destroy` methods, Web interface.