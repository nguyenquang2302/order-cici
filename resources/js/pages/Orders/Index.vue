<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ShoppingCart,
    RefreshCw,
    Clock,
    CheckCircle,
    Package,
    Monitor,
    Wallet,
    XCircle,
} from '@lucide/vue';

interface ManualOrderProduct {
    id: number;
    name: string;
    selling_price: number | string;
    stock_quantity: number;
}

interface ManualOrderItem {
    product_id: number;
    quantity: number;
    note: string;
}

interface OrderItem {
    id: number;
    product_name: string;
    quantity: number;
    unit_price: number | string;
    subtotal: number | string;
    note: string | null;
}

interface Payment {
    id: number;
    amount: number | string;
    method: string;
    status: string;
    paid_at: string | null;
    note: string | null;
}

interface Order {
    id: number;
    order_number: string;
    pc_id: number | null;
    pc_code: string | null;
    pc_name: string | null;
    customer_name: string | null;
    status: string;
    payment_status: string;
    subtotal: number | string;
    discount: number | string;
    total: number | string;
    paid_total: number | string;
    remaining: number | string;
    note: string | null;
    created_at: string;
    completed_at: string | null;
    items: OrderItem[];
    payments: Payment[];
}

const props = defineProps<{
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    filters: {
        status: string | null;
        payment_status: string | null;
    };
    pendingItems: {
        product_id: number;
        product_name: string;
        total_quantity: number | string;
        customer_names: string | null;
    }[];
    pendingOrderCount: number;
    products: ManualOrderProduct[];
}>();

// ==========================================
// TẠO ĐƠN TẠI QUẦY
// ==========================================

const showManualOrderModal = ref(false);
const productSearch = ref('');

const manualOrderForm = useForm({
    customer_name: '',
    note: '',
    items: [] as ManualOrderItem[],
});

const filteredManualProducts = computed(() => {
    const keyword = productSearch.value.trim().toLowerCase();

    return props.products.filter((product) =>
        product.name.toLowerCase().includes(keyword)
    );
});

const manualOrderTotal = computed(() => {
    return manualOrderForm.items.reduce((total, item) => {
        const product = getManualProduct(item.product_id);

        return total + (
            product
                ? Number(product.selling_price) * Number(item.quantity || 0)
                : 0
        );
    }, 0);
});

function getManualProduct(productId: number) {
    return props.products.find((product) => product.id === productId);
}

function addManualProduct(product: ManualOrderProduct) {
    const existing = manualOrderForm.items.find(
        (item) => item.product_id === product.id
    );

    if (existing) {
        existing.quantity++;
        return;
    }

    manualOrderForm.items.push({
        product_id: product.id,
        quantity: 1,
        note: '',
    });
}

function removeManualProduct(productId: number) {
    manualOrderForm.items = manualOrderForm.items.filter(
        (item) => item.product_id !== productId
    );
}

function submitManualOrder() {
    if (manualOrderForm.items.length === 0) {
        alert('Vui lòng chọn ít nhất một sản phẩm.');
        return;
    }

    const invalidQuantity = manualOrderForm.items.some(
        (item) => !Number.isInteger(Number(item.quantity))
            || Number(item.quantity) < 1
            || Number(item.quantity) > 999
    );

    if (invalidQuantity) {
        alert('Số lượng sản phẩm phải từ 1 đến 999.');
        return;
    }

    manualOrderForm.post('/orders/manual', {
        preserveScroll: true,
        onSuccess: () => {
            showManualOrderModal.value = false;
            manualOrderForm.reset();
            productSearch.value = '';
        },
    });
}

function closeManualOrderModal() {
    if (manualOrderForm.processing) return;

    showManualOrderModal.value = false;
}

// ==========================================
// LỌC ĐƠN HÀNG
// ==========================================

const statuses = [
    { value: '', label: 'Tất cả đơn' },
    { value: 'pending', label: 'Đơn mới' },
    { value: 'processing', label: 'Đang xử lý' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'cancelled', label: 'Đã hủy' },
];

const paymentStatuses = [
    { value: '', label: 'Tất cả thanh toán' },
    { value: 'unpaid', label: 'Chưa thu tiền' },
    { value: 'paid', label: 'Đã thu đủ' },
];

const activePaymentStatus = computed(
    () => props.filters.payment_status || ''
);

const activeStatus = computed(() => props.filters.status || '');

function filterOrders(status: string) {
    router.get('/orders', {
        ...(status ? { status } : {}),
        ...(activePaymentStatus.value
            ? { payment_status: activePaymentStatus.value }
            : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function filterPaymentStatus(paymentStatus: string) {
    router.get('/orders', {
        ...(activeStatus.value
            ? { status: activeStatus.value }
            : {}),
        ...(paymentStatus
            ? { payment_status: paymentStatus }
            : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function refreshOrders() {
    router.reload({ preserveScroll: true });
}

// ==========================================
// XỬ LÝ ĐƠN HÀNG
// ==========================================

function confirmOrder(order: Order) {
    if (!window.confirm(
        `Xác nhận đơn ${order.order_number}? Hệ thống sẽ trừ kho.`
    )) {
        return;
    }

    router.post(`/orders/${order.id}/confirm`, {}, {
        preserveScroll: true,
    });
}

function completeOrder(order: Order) {
    if (!window.confirm(
        `Xác nhận đơn ${order.order_number} đã hoàn thành?`
    )) {
        return;
    }

    router.post(`/orders/${order.id}/complete`, {}, {
        preserveScroll: true,
    });
}

function cancelOrder(order: Order) {
    if (!window.confirm(
        `Bạn có chắc muốn hủy đơn ${order.order_number}?\nNếu đơn đã trừ kho, hệ thống sẽ hoàn kho.`
    )) {
        return;
    }

    router.post(`/orders/${order.id}/cancel`, {}, {
        preserveScroll: true,
    });
}

function payOrder(order: Order) {
    const remaining = Number(order.remaining);

    if (remaining <= 0) {
        alert('Đơn hàng đã thanh toán.');
        return;
    }

    if (!window.confirm(
        `Xác nhận thu đủ ${formatMoney(remaining)} cho đơn ${order.order_number}?`
    )) {
        return;
    }

    router.post(`/orders/${order.id}/payments`, {
        amount: remaining,
        method: 'cash',
    }, {
        preserveScroll: true,
    });
}

// ==========================================
// HIỂN THỊ
// ==========================================

function formatMoney(value: number | string | null | undefined) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + 'đ';
}

function formatDate(value: string | null) {
    if (!value) return '-';

    return new Date(value).toLocaleString('vi-VN');
}

function statusLabel(status: string) {
    return {
        pending: 'Đơn mới',
        processing: 'Đang xử lý',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
    }[status] || status;
}

function statusClass(status: string) {
    return {
        pending: 'border-yellow-700 bg-yellow-950 text-yellow-400',
        processing: 'border-blue-700 bg-blue-950 text-blue-400',
        completed: 'border-green-700 bg-green-950 text-green-400',
        cancelled: 'border-red-800 bg-red-950 text-red-400',
    }[status] || 'border-zinc-700 bg-zinc-800 text-zinc-400';
}

function paymentStatusLabel(status: string) {
    return {
        unpaid: 'Chưa thanh toán',
        partial: 'Thanh toán một phần',
        paid: 'Đã thanh toán',
    }[status] || status;
}

function paymentStatusClass(status: string) {
    return {
        unpaid: 'text-red-400',
        partial: 'text-yellow-400',
        paid: 'text-green-400',
    }[status] || 'text-zinc-400';
}

function paymentMethodLabel(method: string) {
    return {
        cash: 'Tiền mặt',
    }[method] || method;
}
</script>

<template>
    <Head title="Quản lý đơn hàng - CiCi Gaming" />

    <div class="min-h-screen bg-[#09090b] p-4 text-white sm:p-6">
        <div class="mx-auto max-w-7xl">

            <!-- HEADER -->
            <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-red-600 p-3">
                        <ShoppingCart class="h-6 w-6" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-black">QUẢN LÝ ĐƠN HÀNG</h1>
                        <p class="text-sm text-zinc-400">
                            CiCi Gaming - Quản lý phục vụ
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="showManualOrderModal = true"
                        class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700"
                    >
                        <ShoppingCart class="h-4 w-4" />
                        Tạo đơn tại quầy
                    </button>

                    <button
                        type="button"
                        @click="refreshOrders"
                        class="flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-900 px-4 py-2.5 text-sm font-semibold hover:bg-zinc-800"
                    >
                        <RefreshCw class="h-4 w-4" />
                        Làm mới
                    </button>
                </div>
            </div>

            <!-- TỔNG HỢP MÓN CẦN CHUẨN BỊ -->
            <div class="mb-6 rounded-xl border border-yellow-800/60 bg-yellow-950/20 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-black text-yellow-400">
                            TỔNG MÓN CẦN CHUẨN BỊ
                        </h2>
                        <p class="mt-1 text-sm text-zinc-400">
                            Tổng hợp từ tất cả khách đang chờ xác nhận
                        </p>
                    </div>

                    <span class="rounded-lg bg-yellow-900/50 px-3 py-2 text-sm font-bold text-yellow-300">
                        {{ pendingOrderCount }} đơn mới
                    </span>
                </div>

                <div
                    v-if="pendingItems.length"
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="item in pendingItems"
                        :key="item.product_id"
                        class="rounded-lg border border-zinc-800 bg-zinc-900 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-zinc-200">
                                {{ item.product_name }}
                            </span>

                            <span class="rounded-lg bg-red-950 px-3 py-1 text-lg font-black text-red-400">
                                × {{ item.total_quantity }}
                            </span>
                        </div>

                        <p class="mt-2 text-xs text-zinc-400">
                            Khách / Máy: {{ item.customer_names || 'Không xác định' }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg bg-zinc-900 p-5 text-center text-sm text-zinc-500"
                >
                    Chưa có món nào trong đơn mới.
                </div>
            </div>

            <!-- THỐNG KÊ -->
            <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-sm text-zinc-400">Tổng đơn hiện tại</p>
                    <p class="mt-2 text-3xl font-black">{{ orders.total }}</p>
                </div>

                <div class="rounded-xl border border-yellow-900 bg-zinc-900 p-4">
                    <p class="text-sm text-yellow-500">Đơn mới</p>
                    <p class="mt-2 text-3xl font-black text-yellow-400">
                        {{ orders.data.filter(o => o.status === 'pending').length }}
                    </p>
                </div>

                <div class="rounded-xl border border-blue-900 bg-zinc-900 p-4">
                    <p class="text-sm text-blue-400">Đang xử lý</p>
                    <p class="mt-2 text-3xl font-black text-blue-400">
                        {{ orders.data.filter(o => o.status === 'processing').length }}
                    </p>
                </div>

                <div class="rounded-xl border border-green-900 bg-zinc-900 p-4">
                    <p class="text-sm text-green-400">Hoàn thành</p>
                    <p class="mt-2 text-3xl font-black text-green-400">
                        {{ orders.data.filter(o => o.status === 'completed').length }}
                    </p>
                </div>
            </div>

            <!-- LỌC TRẠNG THÁI -->
            <div class="mb-5 flex flex-wrap gap-2">
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    @click="filterOrders(status.value)"
                    class="rounded-lg border px-4 py-2 text-sm font-semibold transition"
                    :class="activeStatus === status.value
                        ? 'border-red-600 bg-red-600 text-white'
                        : 'border-zinc-700 bg-zinc-900 text-zinc-400 hover:border-red-500 hover:text-white'"
                >
                    {{ status.label }}
                </button>
            </div>

            <!-- LỌC THANH TOÁN -->
            <div class="mb-5 flex flex-wrap gap-2">
                <button
                    v-for="payment in paymentStatuses"
                    :key="payment.value"
                    @click="filterPaymentStatus(payment.value)"
                    class="rounded-lg border px-4 py-2 text-sm font-semibold transition"
                    :class="activePaymentStatus === payment.value
                        ? 'border-green-600 bg-green-600 text-white'
                        : 'border-zinc-700 bg-zinc-900 text-zinc-400 hover:border-green-500 hover:text-white'"
                >
                    {{ payment.label }}
                </button>
            </div>

            <!-- DANH SÁCH ĐƠN -->
            <div v-if="orders.data.length" class="space-y-4">
                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900"
                >
                    <!-- HEADER ĐƠN -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 p-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-black text-white">
                                    {{ order.order_number }}
                                </h2>

                                <span
                                    class="rounded-full border px-3 py-1 text-xs font-bold"
                                    :class="statusClass(order.status)"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>

                                <span
                                    v-if="order.pc_id === null"
                                    class="rounded-full border border-purple-800 bg-purple-950 px-3 py-1 text-xs font-bold text-purple-300"
                                >
                                    TẠI QUẦY
                                </span>
                            </div>

                            <p class="mt-1 flex items-center gap-2 text-xs text-zinc-500">
                                <Clock class="h-3.5 w-3.5" />
                                {{ formatDate(order.created_at) }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-red-900 bg-red-950/40 px-4 py-2 text-right">
                            <p class="text-xs text-red-300">Tổng thanh toán</p>
                            <p class="text-xl font-black text-red-400">
                                {{ formatMoney(order.total) }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 p-4 lg:grid-cols-[1fr_300px]">

                        <!-- CHI TIẾT MÓN -->
                        <div>
                            <div class="mb-3 flex items-center gap-2 text-sm font-bold text-zinc-300">
                                <Package class="h-4 w-4 text-red-500" />
                                Danh sách món
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 rounded-lg bg-zinc-800/70 p-3"
                                >
                                    <div>
                                        <p class="font-semibold">
                                            {{ item.product_name }}
                                        </p>

                                        <p class="text-xs text-zinc-500">
                                            {{ formatMoney(item.unit_price) }} × {{ item.quantity }}
                                        </p>

                                        <p
                                            v-if="item.note"
                                            class="mt-1 text-xs text-yellow-400"
                                        >
                                            Ghi chú: {{ item.note }}
                                        </p>
                                    </div>

                                    <p class="whitespace-nowrap font-bold text-zinc-200">
                                        {{ formatMoney(item.subtotal) }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="order.note"
                                class="mt-3 rounded-lg bg-yellow-950/40 p-3 text-sm text-yellow-300"
                            >
                                Ghi chú đơn hàng: {{ order.note }}
                            </p>

                            <!-- LỊCH SỬ THANH TOÁN -->
                            <div class="mt-5 rounded-xl border border-zinc-800 bg-[#111113] p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <Wallet class="h-4 w-4 text-green-400" />
                                    <h3 class="text-sm font-bold text-zinc-200">
                                        LỊCH SỬ THANH TOÁN
                                    </h3>
                                </div>

                                <div
                                    v-if="order.payments?.length"
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="payment in order.payments"
                                        :key="payment.id"
                                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-800/70 p-3"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold text-green-400">
                                                {{ formatMoney(payment.amount) }}
                                            </p>

                                            <p class="mt-1 text-xs text-zinc-500">
                                                {{ paymentMethodLabel(payment.method) }}
                                                · {{ formatDate(payment.paid_at) }}
                                            </p>

                                            <p
                                                v-if="payment.note"
                                                class="mt-1 text-xs text-zinc-400"
                                            >
                                                {{ payment.note }}
                                            </p>
                                        </div>

                                        <span class="text-xs font-bold text-green-400">
                                            Đã thu
                                        </span>
                                    </div>
                                </div>

                                <p v-else class="text-sm text-zinc-500">
                                    Chưa có giao dịch thanh toán.
                                </p>
                            </div>
                        </div>

                        <!-- THÔNG TIN ĐƠN VÀ THAO TÁC -->
                        <aside class="rounded-xl border border-zinc-800 bg-[#111113] p-4">

                        <div class="flex items-center gap-2 text-sm text-zinc-400">
                            <Monitor class="h-4 w-4" />
                            {{ order.pc_id === null ? 'Khách hàng' : 'Máy đặt hàng' }}
                        </div>

                        <p
                            class="mt-2 break-words text-2xl font-black"
                            :class="order.pc_id === null ? 'text-purple-400' : 'text-red-400'"
                        >
                            {{ order.pc_id === null
                                ? (order.customer_name || 'Chưa nhập tên khách')
                                : order.pc_code
                            }}
                        </p>

                        <p class="text-sm text-zinc-500">
                            {{ order.pc_id === null ? 'Đơn hàng tại quầy' : order.pc_name }}
                        </p>


                            <!-- TỔNG TIỀN -->
                            <div class="mt-5 space-y-3 border-t border-zinc-800 pt-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400">Tổng tiền</span>
                                    <span class="font-bold">
                                        {{ formatMoney(order.total) }}
                                    </span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400">Đã thu</span>
                                    <span class="font-bold text-green-400">
                                        {{ formatMoney(order.paid_total) }}
                                    </span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400">Còn nợ</span>
                                    <span class="font-bold text-red-400">
                                        {{ formatMoney(order.remaining) }}
                                    </span>
                                </div>

                                <div class="border-t border-zinc-800 pt-3">
                                    <p class="text-xs text-zinc-500">
                                        Trạng thái thanh toán
                                    </p>

                                    <p
                                        class="mt-1 font-bold"
                                        :class="paymentStatusClass(order.payment_status)"
                                    >
                                        {{ paymentStatusLabel(order.payment_status) }}
                                    </p>
                                </div>
                            </div>

                            <!-- SỐ LƯỢNG MÓN -->
                            <div class="mt-4 border-t border-zinc-800 pt-4">
                                <p class="text-xs text-zinc-500">
                                    Số lượng món
                                </p>
                                <p class="mt-1 text-xl font-black">
                                    {{ order.items.reduce((sum, item) => sum + item.quantity, 0) }}
                                </p>
                            </div>

                            <!-- THU TIỀN -->
                            <button
                                v-if="Number(order.remaining) > 0 && order.status !== 'cancelled'"
                                @click="payOrder(order)"
                                class="mt-5 w-full rounded-lg bg-green-600 py-3 text-sm font-bold text-white hover:bg-green-700"
                            >
                                <Wallet class="mr-2 inline h-4 w-4" />
                                THU TIỀN
                            </button>

                            <div
                                v-else-if="order.payment_status === 'paid'"
                                class="mt-5 flex items-center justify-center gap-2 rounded-lg bg-green-950 py-3 text-sm font-bold text-green-400"
                            >
                                <CheckCircle class="h-4 w-4" />
                                ĐÃ THANH TOÁN ĐỦ
                            </div>

                            <!-- XÁC NHẬN -->
                            <button
                                v-if="order.status === 'pending'"
                                @click="confirmOrder(order)"
                                class="mt-5 w-full rounded-lg bg-green-600 py-3 text-sm font-bold text-white hover:bg-green-700"
                            >
                                <CheckCircle class="mr-2 inline h-4 w-4" />
                                XÁC NHẬN ĐƠN
                            </button>

                            <!-- HOÀN THÀNH -->
                            <button
                                v-if="['pending', 'processing'].includes(order.status)"
                                @click="completeOrder(order)"
                                class="mt-2 w-full rounded-lg bg-blue-600 py-3 text-sm font-bold text-white hover:bg-blue-700"
                            >
                                <CheckCircle class="mr-2 inline h-4 w-4" />
                                HOÀN THÀNH ĐƠN
                            </button>

                            <!-- HỦY -->
                            <button
                                v-if="['pending', 'processing'].includes(order.status)"
                                @click="cancelOrder(order)"
                                class="mt-2 w-full rounded-lg bg-red-600 py-3 text-sm font-bold text-white hover:bg-red-700"
                            >
                                <XCircle class="mr-2 inline h-4 w-4" />
                                HỦY ĐƠN
                            </button>

                        </aside>
                    </div>
                </article>
            </div>

            <!-- CHƯA CÓ ĐƠN -->
            <div
                v-else
                class="rounded-xl border border-zinc-800 bg-zinc-900 p-16 text-center"
            >
                <ShoppingCart class="mx-auto h-12 w-12 text-zinc-700" />
                <p class="mt-4 font-bold text-zinc-300">
                    Chưa có đơn hàng nào
                </p>
                <p class="mt-1 text-sm text-zinc-500">
                    Các đơn khách đặt sẽ xuất hiện ở đây.
                </p>
            </div>

            <!-- PHÂN TRANG -->
            <div
                v-if="orders.last_page > 1"
                class="mt-6 flex flex-wrap justify-center gap-2"
            >
                <template
                    v-for="(link, index) in orders.links"
                    :key="index"
                >
                    <button
                        v-if="link.url"
                        @click="router.visit(link.url, { preserveScroll: true })"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="link.active
                            ? 'border-red-600 bg-red-600 text-white'
                            : 'border-zinc-700 bg-zinc-900 text-zinc-400'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-600"
                        v-html="link.label"
                    />
                </template>
            </div>

        </div>
    </div>

    <!-- ====================================== -->
    <!-- MODAL TẠO ĐƠN TẠI QUẦY -->
    <!-- ====================================== -->

    <div
        v-if="showManualOrderModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3 sm:p-5"
        @click.self="closeManualOrderModal"
    >
        <div class="flex max-h-[95vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-zinc-700 bg-[#151515] shadow-2xl">

            <!-- MODAL HEADER -->
            <div class="flex items-center justify-between border-b border-zinc-800 p-4 sm:p-5">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="rounded-lg bg-red-600 p-2">
                            <ShoppingCart class="h-5 w-5 text-white" />
                        </div>

                        <h2 class="text-lg font-black text-white">
                            TẠO ĐƠN TẠI QUẦY
                        </h2>
                    </div>

                    <p class="mt-2 text-sm text-zinc-400">
                        Tạo đơn cho khách mua trực tiếp tại quầy
                    </p>
                </div>

                <button
                    type="button"
                    @click="closeManualOrderModal"
                    :disabled="manualOrderForm.processing"
                    class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-800 hover:text-white disabled:opacity-50"
                >
                    <XCircle class="h-6 w-6" />
                </button>
            </div>

            <!-- MODAL CONTENT -->
            <div class="grid min-h-0 flex-1 gap-5 overflow-y-auto p-4 md:grid-cols-2 sm:p-5">

                <!-- CHỌN SẢN PHẨM -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white">
                            CHỌN SẢN PHẨM
                        </h3>

                        <span class="text-xs text-zinc-500">
                            {{ filteredManualProducts.length }} sản phẩm
                        </span>
                    </div>

                    <input
                        v-model="productSearch"
                        type="text"
                        placeholder="Tìm tên sản phẩm..."
                        class="w-full rounded-lg border border-zinc-700 bg-[#202020] px-3 py-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                    />

                    <div class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                        <button
                            v-for="product in filteredManualProducts"
                            :key="product.id"
                            type="button"
                            @click="addManualProduct(product)"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-800 bg-[#202020] p-3 text-left transition hover:border-red-500 hover:bg-red-950/20"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white">
                                    {{ product.name }}
                                </p>

                                <p class="mt-1 text-sm font-bold text-red-400">
                                    {{ formatMoney(product.selling_price) }}
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <span class="block text-xs text-zinc-500">
                                    Tồn kho
                                </span>
                                <span
                                    class="text-sm font-bold"
                                    :class="product.stock_quantity > 0
                                        ? 'text-green-400'
                                        : 'text-red-400'"
                                >
                                    {{ product.stock_quantity }}
                                </span>
                            </div>
                        </button>

                        <p
                            v-if="filteredManualProducts.length === 0"
                            class="rounded-lg border border-dashed border-zinc-700 py-10 text-center text-sm text-zinc-500"
                        >
                            Không tìm thấy sản phẩm
                        </p>
                    </div>
                </div>

                <!-- GIỎ HÀNG -->
                <div class="space-y-4">
                    <h3 class="font-bold text-white">
                        THÔNG TIN ĐƠN HÀNG
                    </h3>

                    <div class="space-y-2">
                        <input
                            v-model="manualOrderForm.customer_name"
                            type="text"
                            maxlength="100"
                            placeholder="Tên khách hàng (không bắt buộc)"
                            class="w-full rounded-lg border border-zinc-700 bg-[#202020] px-3 py-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                        />

                        <textarea
                            v-model="manualOrderForm.note"
                            rows="2"
                            maxlength="1000"
                            placeholder="Ghi chú đơn hàng..."
                            class="w-full resize-none rounded-lg border border-zinc-700 bg-[#202020] px-3 py-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-zinc-300">
                            GIỎ HÀNG
                        </h3>

                        <span class="text-xs text-zinc-500">
                            {{ manualOrderForm.items.length }} món
                        </span>
                    </div>

                    <div class="max-h-[280px] space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="item in manualOrderForm.items"
                            :key="item.product_id"
                            class="rounded-lg border border-zinc-800 bg-[#202020] p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">
                                        {{ getManualProduct(item.product_id)?.name }}
                                    </p>

                                    <p class="mt-1 text-xs text-red-400">
                                        {{ formatMoney(getManualProduct(item.product_id)?.selling_price) }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="removeManualProduct(item.product_id)"
                                    class="shrink-0 text-xs font-semibold text-red-400 hover:text-red-300"
                                >
                                    Xóa
                                </button>
                            </div>

                            <div class="mt-3 flex items-center gap-3">
                                <label class="text-xs text-zinc-400">
                                    Số lượng
                                </label>

                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="w-20 rounded-lg border border-zinc-700 bg-[#151515] px-2 py-1.5 text-center text-sm text-white focus:border-red-500 focus:outline-none"
                                />

                                <span class="ml-auto text-sm font-bold text-white">
                                    {{ formatMoney(Number(getManualProduct(item.product_id)?.selling_price || 0) * Number(item.quantity || 0)) }}
                                </span>
                            </div>

                            <input
                                v-model="item.note"
                                type="text"
                                maxlength="255"
                                placeholder="Ghi chú món..."
                                class="mt-3 w-full rounded-lg border border-zinc-700 bg-[#151515] px-3 py-2 text-xs text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                            />
                        </div>

                        <p
                            v-if="manualOrderForm.items.length === 0"
                            class="rounded-lg border border-dashed border-zinc-700 py-10 text-center text-sm text-zinc-500"
                        >
                            Chưa chọn sản phẩm
                        </p>
                    </div>

                    <!-- TỔNG TIỀN -->
                    <div class="rounded-lg border border-red-900/60 bg-red-950/20 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-400">
                                Tổng tiền đơn hàng
                            </span>

                            <span class="text-2xl font-black text-red-400">
                                {{ formatMoney(manualOrderTotal) }}
                            </span>
                        </div>
                    </div>

                    <!-- LỖI VALIDATION -->
                    <div
                        v-if="manualOrderForm.errors.items"
                        class="rounded-lg border border-red-800 bg-red-950/30 p-3 text-sm text-red-400"
                    >
                        {{ manualOrderForm.errors.items }}
                    </div>

                    <div
                        v-if="manualOrderForm.errors.customer_name"
                        class="text-sm text-red-400"
                    >
                        {{ manualOrderForm.errors.customer_name }}
                    </div>

                    <div
                        v-if="manualOrderForm.errors.note"
                        class="text-sm text-red-400"
                    >
                        {{ manualOrderForm.errors.note }}
                    </div>
                </div>
            </div>

            <!-- MODAL FOOTER -->
            <div class="flex flex-wrap justify-end gap-3 border-t border-zinc-800 p-4 sm:p-5">
                <button
                    type="button"
                    @click="closeManualOrderModal"
                    :disabled="manualOrderForm.processing"
                    class="rounded-lg border border-zinc-700 px-5 py-3 text-sm font-semibold text-zinc-300 hover:bg-zinc-800 disabled:opacity-50"
                >
                    HỦY
                </button>

                <button
                    type="button"
                    @click="submitManualOrder"
                    :disabled="manualOrderForm.processing || manualOrderForm.items.length === 0"
                    class="rounded-lg bg-red-600 px-6 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ manualOrderForm.processing ? 'ĐANG TẠO...' : 'TẠO ĐƠN HÀNG' }}
                </button>
            </div>
        </div>
    </div>
</template>
