<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// servers.node_id becomes nullable so a stashed server can sit without
// a host while it lives in cold storage. one way migration, narrowing back
// to NOT NULL after the stash manager plugin ships would corrupt every
// stashed row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('node_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('servers.node_id nullable migration cannot be reversed');
    }
};
