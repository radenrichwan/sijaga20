<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ViolationCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $violations = [
            'Bet Kelas',
            'Bet Ambalan',
            'Bet Gudep',
            'Tidak Ada Bet Nama',
            'Tidak Pakai Dasi',
            'Ring Pramuka/Rotan',
            'Tidak Pakai Kacu',
            'Tidak Membawa Almet',
            'Name Tag',
            'Kerudung No Logo',
            'Kospen',
            'Dalaman Selain Putih',
            'Kaos Kaki Tidak Sesuai Hari',
            'Kaos Kaki Abu-Abu',
            'Tidak Pakai Pantofel',
            'Flatshoes',
            'Pakai Sendal',
            'Cat Rambut',
            'Make Up',
            'Hairnet',
            'Jedai',
            'Rok Ngatung',
            'Pelanggaran Berat',
            'Terlambat',
        ];

        foreach ($violations as $name) {
            ViolationCategory::query()->updateOrCreate(
                ['name' => $name],
                [
                    'type' => 'violation',
                    'points' => $name === 'Pelanggaran Berat' ? 10 : 5,
                    'description' => 'Pelanggaran tata tertib sekolah: '.$name.'.',
                ],
            );
        }
    }
}
