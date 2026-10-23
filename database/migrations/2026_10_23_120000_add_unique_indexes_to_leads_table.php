<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the plain email/phone indexes with unique ones.
     *
     * The create migration declared `->unique()->index()` on both columns, but
     * that chain compiles to a single non-unique index, so the live table only
     * enforced uniqueness in the application. Neither a locking lookup nor a
     * reliable duplicate rejection survives concurrent writers without the
     * constraint, so it is added here.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);

            // Named explicitly so down() can find them; the default names
            // would not match the indexes dropped above.
            $table->unique('email', 'leads_email_unique');
            $table->unique('phone', 'leads_phone_unique');
        });
    }

    /**
     * Restores the original non-unique indexes.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_email_unique');
            $table->dropUnique('leads_phone_unique');

            $table->index('email');
            $table->index('phone');
        });
    }
};
