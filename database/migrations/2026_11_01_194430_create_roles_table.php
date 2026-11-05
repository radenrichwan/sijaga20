
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => 'guru', 'description' => 'Guru dengan hak administrasi sistem.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'siswa', 'description' => 'Siswa dengan akses ke data pribadinya.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'operasional', 'description' => 'Akun operasional OSIS/MPK.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
