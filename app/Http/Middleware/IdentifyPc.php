<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyPc
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        $pc = DB::table('pcs')
            ->where('ip_address', $ip)
            ->where('status', 'active')
            ->first();

        // Cho phép máy phát triển truy cập để kiểm tra giao diện.
        // Không gán máy này thành một máy khách.
        if (!$pc && app()->environment('local')) {
            $request->attributes->set('current_pc', null);

            return $next($request);
        }

        if (!$pc) {
            abort(403, 'Máy tính này chưa được đăng ký trong hệ thống CiCi Gaming.');
        }

        $request->attributes->set('current_pc', $pc);

        return $next($request);
    }
}
