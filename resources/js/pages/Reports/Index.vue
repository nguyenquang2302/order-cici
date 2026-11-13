<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    TrendingUp,
    ShoppingBag,
    Wallet,
    Package,
    CalendarDays,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Boxes,
    BarChart3,
    FileSpreadsheet,
} from '@lucide/vue';

interface DailyRevenue {
    date: string;
    revenue: number;
    orders: number;
}

interface TopProduct {
    product_id: number;
    product_name: string;
    quantity_sold: number;
    revenue: number;
}

interface CategoryStat {
    category_name: string;
    quantity_sold: number;
    revenue: number;
}

const props = defineProps<{
    filters: {
        start_date: string;
        end_date: string;
    };
    summary: {
        revenue: number;
        profit: number;
        paid_orders: number;
        total_orders: number;
        completed_orders: number;
        cancelled_orders: number;
        unpaid_orders: number;
    };
    dailyRevenue: DailyRevenue[];
    topProducts: TopProduct[];
    categoryStats: CategoryStat[];
    inventory: {
        total_products: number;
        low_stock: number;
        out_of_stock: number;
        stock_value: number;
    };
}>();

const filters = reactive({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const maxRevenue = computed(() =>
    Math.max(...props.dailyRevenue.map(item => Number(item.revenue)), 1)
);

function applyFilters() {
    router.get('/reports', filters, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    filters.start_date = new Date().toLocaleDateString('en-CA');
    filters.end_date = new Date().toLocaleDateString('en-CA');
    applyFilters();
}

function money(value: number | string) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function dateLabel(value: string) {
    const date = new Date(value + 'T00:00:00');
    return date.toLocaleDateString('vi-VN');
}

function barHeight(value: number | string) {
    return `${Math.max((Number(value) / maxRevenue.value) * 100, 2)}%`;
}
</script>

<template>
    <div class="min-h-screen space-y-6 bg-[#09090b] p-4 text-white sm:p-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <BarChart3 class="h-7 w-7 text-red-500" />
                    <h1 class="text-2xl font-bold">Báo cáo thống kê</h1>
                </div>
                <p class="mt-1 text-sm text-zinc-400">
                    Tổng quan hoạt động kinh doanh CiCi Gaming
                </p>
            </div>
        </div>

        <!-- Date filter -->
        <div class="rounded-xl border border-white/10 bg-[#151518] p-4">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold">
                <CalendarDays class="h-4 w-4 text-red-400" />
                Khoảng thời gian báo cáo
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-zinc-400">Từ ngày</label>
                    <input
                        v-model="filters.start_date"
                        type="date"
                        class="w-full rounded-lg border border-white/10 bg-[#09090b] px-3 py-2 text-sm text-white outline-none focus:border-red-500"
                    />
                </div>

                <div class="flex-1">
                    <label class="mb-1 block text-xs text-zinc-400">Đến ngày</label>
                    <input
                        v-model="filters.end_date"
                        type="date"
                        :min="filters.start_date"
                        class="w-full rounded-lg border border-white/10 bg-[#09090b] px-3 py-2 text-sm text-white outline-none focus:border-red-500"
                    />
                </div>

                <button
                    @click="applyFilters"
                    class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold hover:bg-red-500"
                >
                    Xem báo cáo
                </button>

                <button
                    @click="resetFilters"
                    class="rounded-lg border border-white/10 px-4 py-2.5 text-sm hover:bg-white/5"
                >
                    Hôm nay
                </button>
                <a
                    :href="`/reports/export?start_date=${encodeURIComponent(filters.start_date)}&end_date=${encodeURIComponent(filters.end_date)}`"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500"
                >
                    <FileSpreadsheet class="h-4 w-4" />
                    Xuất Excel
                </a>
                <a
                    :href="`/reports/export-pdf?start_date=${encodeURIComponent(filters.start_date)}&end_date=${encodeURIComponent(filters.end_date)}`"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-600"
                >
                    <FileSpreadsheet class="h-4 w-4" />
                    Xuất PDF
                </a>
            </div>
        </div>

        <!-- Main statistics -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-zinc-400">Doanh thu thực thu</span>
                    <Wallet class="h-5 w-5 text-emerald-400" />
                </div>
                <div class="mt-3 text-2xl font-bold text-emerald-400">
                    {{ money(summary.revenue) }}
                </div>
                <p class="mt-2 text-xs text-zinc-500">
                    Đơn hoàn thành và đã thanh toán
                </p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-zinc-400">Lợi nhuận gộp</span>
                    <TrendingUp class="h-5 w-5 text-blue-400" />
                </div>
                <div class="mt-3 text-2xl font-bold text-blue-400">
                    {{ money(summary.profit) }}
                </div>
                <p class="mt-2 text-xs text-zinc-500">
                    Doanh thu trừ giá vốn
                </p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-zinc-400">Tổng đơn hàng</span>
                    <ShoppingBag class="h-5 w-5 text-purple-400" />
                </div>
                <div class="mt-3 text-2xl font-bold">
                    {{ summary.total_orders }}
                </div>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ summary.completed_orders }} đơn hoàn thành
                </p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-zinc-400">Đơn chưa thanh toán</span>
                    <AlertTriangle class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="mt-3 text-2xl font-bold text-yellow-400">
                    {{ summary.unpaid_orders }}
                </div>
                <p class="mt-2 text-xs text-zinc-500">
                    Không tính vào doanh thu
                </p>
            </div>
        </div>

        <!-- Daily revenue chart -->
        <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="font-bold">Biểu đồ doanh thu</h2>
                    <p class="mt-1 text-xs text-zinc-500">
                        Doanh thu theo ngày trong khoảng đã chọn
                    </p>
                </div>
                <TrendingUp class="h-5 w-5 text-red-400" />
            </div>

            <div v-if="dailyRevenue.length" class="overflow-x-auto">
                <div class="flex h-64 min-w-max items-end gap-3 border-b border-white/10 px-2">
                    <div
                        v-for="item in dailyRevenue"
                        :key="item.date"
                        class="flex h-full w-16 flex-col items-center justify-end gap-2"
                    >
                        <span class="text-[10px] text-zinc-400">
                            {{ money(item.revenue) }}
                        </span>
                        <div
                            class="w-9 rounded-t-md bg-gradient-to-t from-red-700 to-red-400 transition-all"
                            :style="{ height: barHeight(item.revenue) }"
                            :title="`${money(item.revenue)} - ${item.orders} đơn`"
                        ></div>
                        <span class="pb-2 text-[10px] text-zinc-400">
                            {{ dateLabel(item.date) }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="py-12 text-center text-sm text-zinc-500">
                Chưa có dữ liệu doanh thu trong khoảng thời gian này.
            </div>
        </div>

        <!-- Order statistics -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="mb-4 flex items-center gap-2 font-bold">
                    <CheckCircle class="h-5 w-5 text-emerald-400" />
                    Đơn hoàn thành
                </div>
                <div class="text-3xl font-bold text-emerald-400">
                    {{ summary.completed_orders }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="mb-4 flex items-center gap-2 font-bold">
                    <XCircle class="h-5 w-5 text-red-400" />
                    Đơn đã hủy
                </div>
                <div class="text-3xl font-bold text-red-400">
                    {{ summary.cancelled_orders }}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
                <div class="mb-4 flex items-center gap-2 font-bold">
                    <ShoppingBag class="h-5 w-5 text-yellow-400" />
                    Đơn đã thanh toán
                </div>
                <div class="text-3xl font-bold text-yellow-400">
                    {{ summary.paid_orders }}
                </div>
            </div>
        </div>

        <!-- Product and category reports -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#151518]">
                <div class="border-b border-white/10 p-5">
                    <h2 class="font-bold">Top 10 sản phẩm bán chạy</h2>
                    <p class="mt-1 text-xs text-zinc-500">
                        Theo số lượng bán ra
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-white/5 text-xs text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Sản phẩm</th>
                                <th class="px-4 py-3 text-right">Số lượng</th>
                                <th class="px-4 py-3 text-right">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(product, index) in topProducts"
                                :key="product.product_id"
                                class="border-t border-white/5"
                            >
                                <td class="px-4 py-3">
                                    <span class="mr-2 text-zinc-500">{{ index + 1 }}.</span>
                                    {{ product.product_name }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ product.quantity_sold }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-400">
                                    {{ money(product.revenue) }}
                                </td>
                            </tr>
                            <tr v-if="!topProducts.length">
                                <td colspan="3" class="px-4 py-8 text-center text-zinc-500">
                                    Chưa có dữ liệu sản phẩm.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#151518]">
                <div class="border-b border-white/10 p-5">
                    <h2 class="font-bold">Doanh thu theo danh mục</h2>
                    <p class="mt-1 text-xs text-zinc-500">
                        Thống kê sản phẩm đã bán
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-white/5 text-xs text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Danh mục</th>
                                <th class="px-4 py-3 text-right">Số lượng</th>
                                <th class="px-4 py-3 text-right">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="category in categoryStats"
                                :key="category.category_name"
                                class="border-t border-white/5"
                            >
                                <td class="px-4 py-3">{{ category.category_name }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ category.quantity_sold }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-400">
                                    {{ money(category.revenue) }}
                                </td>
                            </tr>
                            <tr v-if="!categoryStats.length">
                                <td colspan="3" class="px-4 py-8 text-center text-zinc-500">
                                    Chưa có dữ liệu danh mục.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventory -->
        <div class="rounded-xl border border-white/10 bg-[#151518] p-5">
            <div class="mb-5 flex items-center gap-2">
                <Boxes class="h-5 w-5 text-red-400" />
                <h2 class="font-bold">Tình hình tồn kho</h2>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-white/5 p-4">
                    <Package class="mb-2 h-5 w-5 text-blue-400" />
                    <p class="text-xs text-zinc-400">Sản phẩm đang kinh doanh</p>
                    <p class="mt-2 text-2xl font-bold">{{ inventory.total_products }}</p>
                </div>

                <div class="rounded-lg bg-white/5 p-4">
                    <AlertTriangle class="mb-2 h-5 w-5 text-yellow-400" />
                    <p class="text-xs text-zinc-400">Sắp hết hàng</p>
                    <p class="mt-2 text-2xl font-bold text-yellow-400">
                        {{ inventory.low_stock }}
                    </p>
                </div>

                <div class="rounded-lg bg-white/5 p-4">
                    <XCircle class="mb-2 h-5 w-5 text-red-400" />
                    <p class="text-xs text-zinc-400">Hết hàng</p>
                    <p class="mt-2 text-2xl font-bold text-red-400">
                        {{ inventory.out_of_stock }}
                    </p>
                </div>

                <div class="rounded-lg bg-white/5 p-4">
                    <Wallet class="mb-2 h-5 w-5 text-emerald-400" />
                    <p class="text-xs text-zinc-400">Giá trị hàng tồn kho</p>
                    <p class="mt-2 text-xl font-bold text-emerald-400">
                        {{ money(inventory.stock_value) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>