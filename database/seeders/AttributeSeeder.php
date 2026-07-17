// database/seeders/AttributeSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'motif' => ['Lung-lungan', 'Majapahit', 'Klasik Tradisional', 'Kontemporer'],
            'jenis_ukiran' => ['Relief Jepara', 'Ukir Kerawang', 'Ukir Timbul', 'Ukir Tembus'],
            'bahan' => ['Kayu Jati', 'Kayu Mahoni', 'Kayu Sonokeling', 'Kayu Mindi'],
            'ukuran' => ['Custom Order', 'Kecil (< 50cm)', 'Sedang (50-150cm)', 'Besar (> 150cm)'],
        ];

        foreach ($data as $type => $values) {
            foreach ($values as $value) {
                Attribute::firstOrCreate(['type' => $type, 'value' => $value]);
            }
        }
    }
}
