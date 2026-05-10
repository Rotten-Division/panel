<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// the eviction sweep filters on (status='stopped', last_panel_activity_at <=
// now() - 15 min), the compound index keeps the sweep cheap as the table
// grows. nullable so the column does not lie about freshly created servers
// that have not had any panel-side request yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('last_panel_activity_at')->nullable()->after('installed_at');
            $table->index(['status', 'last_panel_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_panel_activity_at']);
            $table->dropColumn('last_panel_activity_at');
        });
    }
};
