<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'CiCi Gaming Admin');
        $email = env('ADMIN_EMAIL', 'admin@cici.local');
        $password = env('ADMIN_PASSWORD');

        if (empty($password)) {
            throw new RuntimeException(
                'Vui lòng cấu hình ADMIN_PASSWORD trong file .env trước khi chạy Seeder.'
            );
        }

        // Tạo Admin nếu chưa tồn tại, không ghi đè mật khẩu cũ.
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
            ]
        );

        // Đảm bảo tài khoản có quyền Admin và đã xác minh email.
        $admin->forceFill([
            'role' => 'admin',
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        $this->command?->info('Đã kiểm tra tài khoản Admin: ' . $email);
    }
}