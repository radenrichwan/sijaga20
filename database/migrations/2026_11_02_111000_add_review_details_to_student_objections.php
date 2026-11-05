<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_objections', function (Blueprint $table): void {
            $table->text('supporting_note')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('correction_transaction_id')
                ->nullable()
                ->constrained('point_transactions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_objections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('correction_transaction_id');
            $table->dropColumn(['supporting_note', 'evidence_path']);
        });
    }
};
