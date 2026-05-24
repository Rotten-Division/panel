<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// rewrites servers.status enum values from the legacy bird/nest vocabulary
// to the squirrel/stash vocabulary. one-way migration, the column has no
// db-side enum constraint so the rewrite is a plain UPDATE per old value.
// rollback would require keeping the old enum cases alive, which we are
// explicitly not doing per the stash-manager-rename plan.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('servers')->where('status', 'nest')->update(['status' => 'stashed']);
        DB::table('servers')->where('status', 'hydrating')->update(['status' => 'retrieving']);
        DB::table('servers')->where('status', 'capturing')->update(['status' => 'stashing']);
    }

    public function down(): void
    {
        throw new RuntimeException('servers.status stash vocabulary migration cannot be reversed');
    }
};
