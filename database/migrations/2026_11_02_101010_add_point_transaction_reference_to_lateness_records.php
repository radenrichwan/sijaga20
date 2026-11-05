<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lateness_records', function (Blueprint $table): void {
            $table->foreignId('point_transaction_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('point_transactions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lateness_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('point_transaction_id');
        });
    }
};
