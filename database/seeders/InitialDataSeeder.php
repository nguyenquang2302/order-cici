<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Nước uống',
                'slug' => 'nuoc-uong',
                'description' => 'Các loại nước giải khát',
            ],
            [
                'name' => 'Thức ăn',
                'slug' => 'thuc-an',
                'description' => 'Đồ ăn nhanh và thức ăn nhẹ',
            ],
            [
                'name' => 'Thuốc lá',
                'slug' => 'thuoc-la',
                'description' => 'Các sản phẩm thuốc lá',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
