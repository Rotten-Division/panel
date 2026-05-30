<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// generic clock for the most recent status change. populated today only at the
// stash transient transitions (Stashing, Retrieving) by ospite-stash-manager,
// which is the orphan sweep's wedge-detection signal. a future feature that
// wants a general status clock should set it at its own transition sites and
// mind that mass UPDATE skips model observers.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
