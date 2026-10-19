<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites trackable_type from a fully qualified model name to its morph
 * alias.
 *
 * Rows written before the morph map was registered in AppServiceProvider hold
 * the model's fully qualified name, while Eloquent now writes the alias. Any
 * read filtering on one shape misses the other, so the column is normalized
 * once here and reads can go back to a plain equality match.
 */
return new class extends Migration
{
    /**
     * Rewrites every stored value that still holds a model class name.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (Relation::morphMap() as $alias => $modelClass) {
            // Matched on the exact stored class name, so a value that is
            // already an alias is never touched.
            DB::table('activities')
                ->where('trackable_type', $modelClass)
                ->update(['trackable_type' => $alias]);
        }
    }

    /**
     * Restores the fully qualified model name for every mapped alias.
     *
     * @return void
     */
    public function down(): void
    {
        foreach (Relation::morphMap() as $alias => $modelClass) {
            DB::table('activities')
                ->where('trackable_type', $alias)
                ->update(['trackable_type' => $modelClass]);
        }
    }
};
