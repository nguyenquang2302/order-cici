<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PcSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $machines = [];

        $groups = [
            'A' => 101,
            'B' => 111,
            'C' => 121,
        ];

        foreach ($groups as $prefix => $startIp) {
            for ($i = 1; $i <= 10; $i++) {
                $code = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);

                $machines[] = [
                    'code' => $code,
                    'name' => 'Máy ' . $code,
                    'ip_address' => '192.168.1.' . ($startIp + $i - 1),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($machines as $machine) {
            DB::table('pcs')->updateOrInsert(
                ['code' => $machine['code']],
                [
                    'name' => $machine['name'],
                    'ip_address' => $machine['ip_address'],
                    'status' => $machine['status'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
