
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import {
    ShoppingCart,
    Plus,
    Minus,
    Gamepad2,
    X,
    Trash2,
    Wallet,
} from '@lucide/vue';

const topupInput = ref('');

function onTopupInput(event: Event) {
    const target = event.target as HTMLInputElement;

    // Chỉ giữ lại chữ số.
    const digits = target.value.replace(/\D/g, '');

    // Chuyển thành số nguyên để gửi lên Laravel.
    const amount = digits ? Number(digits) : 0;

    form.topup_amount = Number.isSafeInteger(amount) ? amount : 0;

    // Hiển thị có dấu phân cách hàng nghìn.
    topupInput.value = form.topup_amount > 0
        ? new Intl.NumberFormat('vi-VN').format(form.topup_amount)
        : '';
}

interface Product {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    unit: string;
    selling_price: string | number;
    image_path: string | null;
    stock_quantity: number;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    products: Product[];
}

interface CartItem {
    product: Product;
    quantity: number;
}

interface GroupPayment {
    id: number;
    payment_reference: string;
    total: number;
    order_count: number;
    qr_url: string | null;
}

interface PendingOrder {
    id: number;
    order_number: string;
    payment_reference: string;
    payment_method: string;
    payment_status: string;
    status: string;
    total: number;
    topup_amount: number | string | null;
    qr_url: string | null;
    items: {
        product_name: string;
        quantity: number;
        unit_price?: number;
        subtotal: number;
    }[];
}

const props = defineProps<{
    pc: {
        id: number | null;
        code: string;
        name: string;
    };
    categories: Category[];
    pendingOrders: PendingOrder[];
    groupPayment: GroupPayment | null;
    realtimeChannel: string;
}>();

// Cập nhật danh sách đơn khi ngân hàng xác nhận thanh toán.
useEchoPublic(props.realtimeChannel, 'OrderPaymentUpdated', () => {
    router.reload({
        only: ['pendingOrders', 'groupPayment'],
        preserveScroll: true,
        preserveState: true,
    });
});

const activeCategory = ref<number | null>(null);
const cart = ref<CartItem[]>([]);
const successMessage = ref('');
const mobileCartOpen = ref(false);
const copiedMessage = ref('');
const groupPaymentError = ref('');
const groupPaymentProcessing = ref(false);

// Form đặt món và nạp tiền.
const form = useForm({
    items: [] as { product_id: number; quantity: number }[],
    topup_amount: 0,
    note: '',
});

const visibleProducts = computed(() => {
    const categories = activeCategory.value === null
        ? props.categories
        : props.categories.filter(c => c.id === activeCategory.value);

    return categories.flatMap(category => category.products);
});

const cartCount = computed(() =>
    cart.value.reduce((sum, item) => sum + item.quantity, 0)
);

const productTotal = computed(() =>
    cart.value.reduce(
        (sum, item) => sum + Number(item.product.selling_price) * item.quantity,
        0
    )
);

const topupAmount = computed(() => {
    const value = Number(form.topup_amount);
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const total = computed(() => productTotal.value + topupAmount.value);

const canSubmit = computed(() =>
    (cart.value.length > 0 || topupAmount.value > 0) &&
    !form.processing
);

function formatMoney(value: number | string) {
    return new Intl.NumberFormat('vi-VN').format(Number(value)) + 'đ';
}

async function copyReference(reference: string) {
    if (!reference) return;

    try {
        await navigator.clipboard.writeText(reference);
        copiedMessage.value = reference;

        setTimeout(() => {
            if (copiedMessage.value === reference) {
                copiedMessage.value = '';
            }
        }, 2000);
    } catch {
        copiedMessage.value = 'error';
    }
}

function addToCart(product: Product) {
    if (product.stock_quantity <= 0) return;

    successMessage.value = '';

    const item = cart.value.find(i => i.product.id === product.id);

    if (item) {
        if (item.quantity < product.stock_quantity) {
            item.quantity++;
        }
    } else {
        cart.value.push({ product, quantity: 1 });
    }
}

function changeQuantity(productId: number, amount: number) {
    successMessage.value = '';

    const item = cart.value.find(i => i.product.id === productId);
    if (!item) return;

    const product = props.categories
        .flatMap(category => category.products)
        .find(p => p.id === productId);

    item.quantity += amount;

    if (item.quantity <= 0) {
        cart.value = cart.value.filter(i => i.product.id !== productId);
        return;
    }

    if (product && item.quantity > product.stock_quantity) {
        item.quantity = product.stock_quantity;
    }
}

function removeFromCart(productId: number) {
    successMessage.value = '';
    cart.value = cart.value.filter(i => i.product.id !== productId);
}

function createGroupPayment() {
    if (
        groupPaymentProcessing.value ||
        props.pendingOrders.length < 2 ||
        props.groupPayment
    ) {
        return;
    }

    groupPaymentError.value = '';
    groupPaymentProcessing.value = true;

    router.post('/thanh-toan-gop', {}, {
        preserveScroll: true,
        onError: (errors) => {
            groupPaymentError.value =
                errors.group_payment || 'Không thể tạo QR thanh toán gộp.';
        },
        onFinish: () => {
            groupPaymentProcessing.value = false;
        },
    });
}

function submitOrder() {
    if (!canSubmit.value) return;

    successMessage.value = '';

    form.items = cart.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
    }));

    form.topup_amount = topupAmount.value;

    form.post('/dat-mon', {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            form.reset();
            topupInput.value = '';
            mobileCartOpen.value = false;

            successMessage.value =
                'Đặt hàng thành công! Vui lòng quét mã QR để thanh toán.';
        },
    });
}
</script>

<template>
    <Head title="Gọi món - CiCi Gaming" />

    <div class="min-h-screen bg-[#09090b] pb-24 text-white lg:pb-0">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-zinc-800 bg-[#09090b]/95 backdrop-blur">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-3 px-4 py-4 lg:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-600">
                        <Gamepad2 class="h-6 w-6" />
                    </div>

                    <div>
                        <h1 class="text-lg font-black tracking-wide">
                            CICI <span class="text-red-500">GAMING</span>
                        </h1>
                        <p class="text-xs text-zinc-400">
                            Thế giới giải trí của bạn
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-right">
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500">
                        Máy đang sử dụng
                    </p>
                    <p class="font-bold text-red-400">{{ pc.code }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] px-4 py-6 lg:px-6">
            <!-- Thanh toán gộp -->
            <section
                v-if="pendingOrders.length >= 2"
                class="mb-6 overflow-hidden rounded-2xl border border-emerald-700/50 bg-zinc-900 shadow-xl"
            >
                <div class="border-b border-zinc-800 bg-emerald-950/30 px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">
                        THANH TOÁN TIỆN LỢI
                    </p>
                    <h2 class="mt-1 text-xl font-black">
                        Thanh toán gộp tất cả đơn
                    </h2>
                    <p class="mt-1 text-sm text-zinc-400">
                        Một mã QR cho toàn bộ {{ pendingOrders.length }} đơn chưa thanh toán của máy {{ pc.code }}.
                    </p>
                </div>

                <div v-if="groupPayment" class="grid gap-5 p-5 md:grid-cols-[190px_minmax(0,1fr)]">
                    <div class="flex flex-col items-center">
                        <div class="w-full max-w-[190px] rounded-xl bg-white p-3">
                            <img
                                v-if="groupPayment.qr_url"
                                :src="groupPayment.qr_url"
                                alt="QR thanh toán gộp CiCi Gaming"
                                class="aspect-square w-full object-contain"
                            />
                            <div
                                v-else
                                class="flex aspect-square items-center justify-center text-center text-sm text-zinc-500"
                            >
                                Chưa cấu hình tài khoản ngân hàng.
                            </div>
                        </div>
                        <p class="mt-2 text-center text-xs text-zinc-400">
                            Quét QR để thanh toán toàn bộ đơn.
                        </p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm text-zinc-400">
                            Tổng tiền cần thanh toán ({{ groupPayment.order_count }} đơn)
                        </p>
                        <p class="mt-1 text-3xl font-black text-emerald-400">
                            {{ formatMoney(groupPayment.total) }}
                        </p>

                        <div class="mt-3 rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                            <p class="text-xs text-zinc-500">Nội dung chuyển khoản</p>
                            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                                <span class="break-all font-bold tracking-wide text-green-400">
                                    {{ groupPayment.payment_reference }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyReference(groupPayment.payment_reference)"
                                    class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-800"
                                >
                                    Sao chép
                                </button>
                            </div>
                            <p
                                v-if="copiedMessage === groupPayment.payment_reference"
                                class="mt-2 text-xs text-green-400"
                            >
                                Đã sao chép mã chuyển khoản
                            </p>
                        </div>

                        <p class="mt-3 rounded-lg border border-yellow-800/40 bg-yellow-950/20 p-3 text-xs leading-relaxed text-yellow-300">
                            QR này bao gồm toàn bộ đơn chưa thanh toán tại thời điểm tạo.
                            Vui lòng chuyển đúng tổng tiền và giữ nguyên nội dung chuyển khoản.
                            Nếu chuyển thiếu hoặc sai số tiền, admin sẽ kiểm tra thủ công.
                        </p>
                    </div>
                </div>

                <div v-else class="p-5">
                    <p class="mb-3 text-sm text-zinc-300">
                        Bạn có thể thanh toán riêng từng đơn bằng QR bên dưới hoặc tạo một QR cho tất cả đơn.
                    </p>
                    <button
                        type="button"
                        @click="createGroupPayment"
                        :disabled="groupPaymentProcessing"
                        class="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ groupPaymentProcessing ? 'ĐANG TẠO QR...' : 'TẠO QR THANH TOÁN GỘP' }}
                    </button>
                    <p v-if="groupPaymentError" class="mt-3 text-sm text-red-400">
                        {{ groupPaymentError }}
                    </p>
                </div>
            </section>

            <!-- Các đơn đang chờ thanh toán -->
            <section
                v-for="pendingOrder in pendingOrders"
                :key="pendingOrder.id"
                class="mb-6 overflow-hidden rounded-2xl border border-red-700/50 bg-zinc-900 shadow-xl"
            >
                <div class="border-b border-zinc-800 bg-red-950/30 px-4 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="min-w-0 truncate text-sm font-bold text-zinc-100">
                            Thanh toán đơn hàng
                        </h2>
                        <span class="shrink-0 rounded-full border border-yellow-600/40 bg-yellow-500/10 px-2.5 py-1 text-[10px] font-bold tracking-wide text-yellow-400">
                            CHƯA THANH TOÁN
                        </span>
                    </div>
                </div>

                <div class="grid gap-6 p-5 md:grid-cols-[220px_minmax(0,1fr)]">
                    <!-- QR -->
                    <div class="flex flex-col items-center">
                        <div class="w-full max-w-[220px] rounded-xl bg-white p-3">
                            <img
                                v-if="pendingOrder.qr_url"
                                :src="pendingOrder.qr_url"
                                alt="Mã QR thanh toán CiCi Gaming"
                                class="aspect-square w-full object-contain"
                            />
                            <div
                                v-else
                                class="flex aspect-square items-center justify-center text-center text-sm text-zinc-500"
                            >
                                Chưa cấu hình thông tin tài khoản ngân hàng.
                            </div>
                        </div>
                        <p class="mt-3 text-center text-xs text-zinc-400">
                            Quét mã QR bằng ứng dụng ngân hàng để thanh toán.
                        </p>
                    </div>

                    <!-- Thông tin đơn -->
                    <div class="min-w-0">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                                <p class="text-xs text-zinc-500">Mã đơn hàng</p>
                                <p class="mt-1 break-all font-bold">
                                    {{ pendingOrder.order_number }}
                                </p>
                            </div>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                                <p class="text-xs text-zinc-500">Máy</p>
                                <p class="mt-1 font-bold">{{ pc.code }}</p>
                            </div>
                        </div>

                        <div class="mt-3 rounded-lg border border-red-900/50 bg-red-950/20 p-4">
                            <p class="text-xs text-zinc-400">Số tiền cần thanh toán</p>
                            <p class="mt-1 text-3xl font-black text-red-400">
                                {{ formatMoney(pendingOrder.total) }}
                            </p>
                        </div>

                        <div
                            v-if="Number(pendingOrder.topup_amount || 0) > 0"
                            class="mt-3 rounded-lg border border-emerald-800/50 bg-emerald-950/20 p-3"
                        >
                            <div class="flex items-center gap-2 text-emerald-400">
                                <Wallet class="h-4 w-4" />
                                <span class="text-sm font-bold">Tiền nạp tài khoản game</span>
                            </div>
                            <p class="mt-1 text-xl font-black text-emerald-400">
                                {{ formatMoney(pendingOrder.topup_amount || 0) }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-400">
                                Nhân viên sẽ kiểm tra thanh toán và thực hiện nạp tiền.
                            </p>
                        </div>

                        <div class="mt-3 rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                            <p class="text-xs text-zinc-500">Nội dung chuyển khoản</p>
                            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                                <span class="break-all font-bold tracking-wide text-green-400">
                                    {{ pendingOrder.payment_reference }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyReference(pendingOrder.payment_reference)"
                                    class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-800"
                                >
                                    Sao chép
                                </button>
                            </div>

                            <p
                                v-if="copiedMessage === pendingOrder.payment_reference"
                                class="mt-2 text-xs text-green-400"
                            >
                                Đã sao chép mã chuyển khoản
                            </p>
                            <p
                                v-else-if="copiedMessage === 'error'"
                                class="mt-2 text-xs text-red-400"
                            >
                                Không thể sao chép. Vui lòng nhập thủ công.
                            </p>
                        </div>

                        <div v-if="pendingOrder.items.length" class="mt-4">
                            <p class="mb-2 text-sm font-bold text-zinc-300">
                                Chi tiết đơn hàng
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="(item, index) in pendingOrder.items"
                                    :key="index"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-zinc-300">
                                        {{ item.product_name }} × {{ item.quantity }}
                                    </span>
                                    <span class="shrink-0 font-semibold">
                                        {{ formatMoney(item.subtotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="mt-4 rounded-lg border border-yellow-800/40 bg-yellow-950/20 p-3 text-xs leading-relaxed text-yellow-300">
                            Vui lòng chuyển khoản đúng số tiền và giữ nguyên nội dung chuyển khoản.
                            Mã QR này vẫn sử dụng được khi bạn tải lại trang.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Thông báo -->
            <div
                v-if="successMessage"
                class="mb-5 rounded-lg border border-green-800 bg-green-950/50 p-4 text-sm text-green-300"
            >
                {{ successMessage }}
            </div>

            <!-- Khu vực đặt món -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_390px]">
                <div class="min-w-0">
                    <section class="mb-7 rounded-2xl border border-zinc-800 bg-gradient-to-r from-red-950/50 to-zinc-900 p-6">
                        <p class="text-sm font-semibold text-red-400">
                            CICI GAMING MENU
                        </p>
                        <h2 class="mt-2 text-2xl font-black sm:text-3xl">
                            Bạn muốn dùng gì hôm nay?
                        </h2>
                        <p class="mt-2 text-sm text-zinc-400">
                            Chọn món yêu thích, chúng tôi sẽ phục vụ tận máy {{ pc.code }}.
                        </p>
                    </section>

                    <!-- Danh mục -->
                    <div class="mb-6 flex flex-wrap gap-2">
                        <button
                            type="button"
                            @click="activeCategory = null"
                            class="rounded-full border px-5 py-2 text-sm font-semibold transition"
                            :class="activeCategory === null
                                ? 'border-red-600 bg-red-600 text-white'
                                : 'border-zinc-700 bg-zinc-900 text-zinc-300 hover:border-red-500'"
                        >
                            Tất cả
                        </button>

                        <button
                            v-for="category in categories"
                            :key="category.id"
                            type="button"
                            @click="activeCategory = category.id"
                            class="rounded-full border px-5 py-2 text-sm font-semibold transition"
                            :class="activeCategory === category.id
                                ? 'border-red-600 bg-red-600 text-white'
                                : 'border-zinc-700 bg-zinc-900 text-zinc-300 hover:border-red-500'"
                        >
                            {{ category.name }}
                        </button>
                    </div>

                    <!-- Sản phẩm -->
                    <div
                        v-if="visibleProducts.length"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                    >
                        <article
                            v-for="product in visibleProducts"
                            :key="product.id"
                            class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 transition hover:border-zinc-600"
                        >
                            <div class="flex aspect-[4/3] items-center justify-center bg-zinc-800">
                                <img
                                    v-if="product.image_path"
                                    :src="`/storage/${product.image_path}`"
                                    :alt="product.name"
                                    class="h-full w-full object-cover"
                                />
                                <ShoppingCart
                                    v-else
                                    class="h-10 w-10 text-zinc-600"
                                />
                            </div>

                            <div class="p-3">
                                <h3 class="line-clamp-2 min-h-10 font-bold">
                                    {{ product.name }}
                                </h3>
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ product.description || product.unit }}
                                </p>
                                <p class="mt-3 font-bold text-red-400">
                                    {{ formatMoney(product.selling_price) }}
                                </p>
                                <p class="mt-1 text-xs text-zinc-500">
                                    Còn {{ product.stock_quantity }} {{ product.unit }}
                                </p>

                                <div
                                    v-if="cart.some(i => i.product.id === product.id)"
                                    class="mt-2 text-xs font-semibold text-green-400"
                                >
                                    Đã thêm {{ cart.find(i => i.product.id === product.id)?.quantity }}
                                    {{ product.unit }}
                                </div>

                                <button
                                    type="button"
                                    @click="addToCart(product)"
                                    :disabled="product.stock_quantity <= 0 || (cart.find(i => i.product.id === product.id)?.quantity ?? 0) >= product.stock_quantity"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 py-2.5 text-sm font-bold transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-700"
                                >
                                    <Plus class="h-4 w-4" />
                                    {{ product.stock_quantity > 0 ? 'Thêm món' : 'Hết hàng' }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center text-zinc-500"
                    >
                        Chưa có sản phẩm trong danh mục này.
                    </div>
                </div>

                <!-- Giỏ hàng desktop -->
                <aside class="hidden lg:block">
                    <section class="sticky top-24 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-xl">
                        <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-950/70 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-600/15 text-red-400">
                                    <ShoppingCart class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="font-bold">Giỏ hàng</h2>
                                    <p class="text-xs text-zinc-500">
                                        Đơn mới sẽ có mã QR riêng
                                    </p>
                                </div>
                            </div>
                            <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-bold">
                                {{ cartCount }} món
                            </span>
                        </div>

                        <div class="max-h-[calc(100vh-470px)] min-h-[120px] overflow-y-auto px-5">
                            <div v-if="cart.length" class="divide-y divide-zinc-800">
                                <div
                                    v-for="item in cart"
                                    :key="item.product.id"
                                    class="flex gap-3 py-4"
                                >
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-zinc-800">
                                        <img
                                            v-if="item.product.image_path"
                                            :src="`/storage/${item.product.image_path}`"
                                            :alt="item.product.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full items-center justify-center">
                                            <ShoppingCart class="h-5 w-5 text-zinc-600" />
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">
                                            {{ item.product.name }}
                                        </p>
                                        <p class="mt-1 text-sm font-bold text-red-400">
                                            {{ formatMoney(Number(item.product.selling_price) * item.quantity) }}
                                        </p>

                                        <div class="mt-2 flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="changeQuantity(item.product.id, -1)"
                                                class="rounded-md border border-zinc-700 p-1.5 hover:bg-zinc-800"
                                            >
                                                <Minus class="h-3.5 w-3.5" />
                                            </button>

                                            <span class="w-6 text-center text-sm font-bold">
                                                {{ item.quantity }}
                                            </span>

                                            <button
                                                type="button"
                                                @click="changeQuantity(item.product.id, 1)"
                                                :disabled="item.quantity >= item.product.stock_quantity"
                                                class="rounded-md border border-zinc-700 p-1.5 hover:bg-zinc-800 disabled:opacity-30"
                                            >
                                                <Plus class="h-3.5 w-3.5" />
                                            </button>

                                            <button
                                                type="button"
                                                @click="removeFromCart(item.product.id)"
                                                class="ml-auto rounded-md p-1.5 text-zinc-500 hover:bg-red-950 hover:text-red-400"
                                                title="Xóa món"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex min-h-[130px] flex-col items-center justify-center text-center"
                            >
                                <ShoppingCart class="h-10 w-10 text-zinc-700" />
                                <p class="mt-3 text-sm font-semibold text-zinc-400">
                                    Giỏ hàng đang trống
                                </p>
                                <p class="mt-1 text-xs text-zinc-600">
                                    Chọn món bên trái để thêm vào đơn hàng.
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-zinc-800 p-5">
                            <!-- Nạp tiền -->
                            <div class="mb-5 rounded-xl border border-emerald-800/50 bg-emerald-950/20 p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <Wallet class="h-5 w-5 text-emerald-400" />
                                    <h3 class="font-bold text-emerald-400">
                                        Nạp tiền tài khoản game
                                    </h3>
                                </div>

                                <label class="mb-2 block text-sm text-zinc-300">
                                    Số tiền muốn nạp
                                </label>

                                <input
                                    :value="topupInput"
                                    @input="onTopupInput"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    placeholder="Ví dụ: 500.000"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-lg font-bold tracking-wide text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                                />

                                <p class="mt-2 text-xs text-emerald-400">
                                    Số tiền: {{ formatMoney(topupAmount) }}
                                </p>

                                <p
                                    v-if="form.errors.topup_amount"
                                    class="mt-2 text-xs text-red-400"
                                >
                                    {{ form.errors.topup_amount }}
                                </p>

                                <p
                                    v-if="form.errors.topup_amount"
                                    class="mt-2 text-xs text-red-400"
                                >
                                    {{ form.errors.topup_amount }}
                                </p>

                                <p class="mt-2 text-xs leading-relaxed text-zinc-400">
                                    Nhân viên sẽ xác nhận thanh toán và nạp tiền vào tài khoản game cho bạn.
                                </p>
                            </div>

                            <label class="mb-2 block text-sm font-semibold text-zinc-300">
                                Ghi chú cho nhân viên
                            </label>

                            <textarea
                                v-model="form.note"
                                rows="2"
                                maxlength="500"
                                placeholder="Ví dụ: Cho ít đá, giao nhanh giúp mình..."
                                class="w-full resize-none rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                            />

                            <p
                                v-if="form.errors.order"
                                class="mt-3 rounded-lg border border-red-800 bg-red-950/50 p-3 text-sm text-red-300"
                            >
                                {{ form.errors.order }}
                            </p>

                            <div class="mt-4 space-y-2 border-t border-zinc-800 pt-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-400">Tiền món</span>
                                    <span>{{ formatMoney(productTotal) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-400">Tiền nạp</span>
                                    <span class="text-emerald-400">
                                        {{ formatMoney(topupAmount) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between pt-2">
                                    <span class="font-semibold text-zinc-300">Tổng thanh toán</span>
                                    <span class="text-2xl font-black text-red-400">
                                        {{ formatMoney(total) }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="submitOrder"
                                :disabled="!canSubmit"
                                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-4 font-bold transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-700"
                            >
                                <ShoppingCart class="h-5 w-5" />
                                {{ form.processing ? 'ĐANG GỬI ĐƠN...' : 'GỬI ĐƠN HÀNG' }}
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </main>

        <!-- Thanh giỏ hàng mobile -->
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-800 bg-zinc-950/95 p-3 backdrop-blur lg:hidden">
            <button
                type="button"
                @click="mobileCartOpen = true"
                class="flex w-full items-center justify-between rounded-xl bg-red-600 px-4 py-3.5 font-bold hover:bg-red-700"
            >
                <span class="flex items-center gap-2">
                    <ShoppingCart class="h-5 w-5" />
                    Giỏ hàng ({{ cartCount }})
                </span>
                <span>
                    {{ formatMoney(total) }} · Xem giỏ hàng
                </span>
            </button>
        </div>

        <!-- Giỏ hàng mobile -->
        <div
            v-if="mobileCartOpen"
            class="fixed inset-0 z-50 flex items-end bg-black/70 lg:hidden"
            @click.self="mobileCartOpen = false"
        >
            <section class="max-h-[90vh] w-full overflow-hidden rounded-t-2xl border-t border-zinc-700 bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                    <div>
                        <h2 class="font-bold">Giỏ hàng của bạn</h2>
                        <p class="text-xs text-zinc-500">
                            {{ cartCount }} món đã chọn
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="mobileCartOpen = false"
                        class="rounded-lg border border-zinc-700 p-2"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[35vh] overflow-y-auto px-5">
                    <div v-if="cart.length" class="divide-y divide-zinc-800">
                        <div
                            v-for="item in cart"
                            :key="item.product.id"
                            class="flex items-center gap-3 py-4"
                        >
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-zinc-800">
                                <img
                                    v-if="item.product.image_path"
                                    :src="`/storage/${item.product.image_path}`"
                                    :alt="item.product.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <ShoppingCart class="h-5 w-5 text-zinc-600" />
                                </div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">
                                    {{ item.product.name }}
                                </p>
                                <p class="text-sm font-bold text-red-400">
                                    {{ formatMoney(Number(item.product.selling_price) * item.quantity) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="changeQuantity(item.product.id, -1)"
                                    class="rounded-md border border-zinc-700 p-2"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                                <span class="w-5 text-center text-sm font-bold">
                                    {{ item.quantity }}
                                </span>
                                <button
                                    type="button"
                                    @click="changeQuantity(item.product.id, 1)"
                                    :disabled="item.quantity >= item.product.stock_quantity"
                                    class="rounded-md border border-zinc-700 p-2 disabled:opacity-30"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <button
                                type="button"
                                @click="removeFromCart(item.product.id)"
                                class="text-zinc-500 hover:text-red-400"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-else class="py-6 text-center text-sm text-zinc-500">
                        Bạn chưa chọn món nào.
                    </div>
                </div>

                <div class="max-h-[48vh] overflow-y-auto border-t border-zinc-800 p-5">
                    <!-- Nạp tiền mobile -->
                    <div class="mb-5 rounded-xl border border-emerald-800/50 bg-emerald-950/20 p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <Wallet class="h-5 w-5 text-emerald-400" />
                            <h3 class="font-bold text-emerald-400">
                                Nạp tiền tài khoản game
                            </h3>
                        </div>

                        <label class="mb-2 block text-sm text-zinc-300">
                            Số tiền muốn nạp
                        </label>

                        <input
                            v-model.number="form.topup_amount"
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="Nhập số tiền cần nạp"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                        />

                        <p
                            v-if="form.errors.topup_amount"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.topup_amount }}
                        </p>

                        <p class="mt-2 text-xs leading-relaxed text-zinc-400">
                            Nhân viên sẽ xác nhận thanh toán và nạp tiền vào tài khoản game cho bạn.
                        </p>
                    </div>

                    <label class="mb-2 block text-sm font-semibold text-zinc-300">
                        Ghi chú cho nhân viên
                    </label>

                    <textarea
                        v-model="form.note"
                        rows="2"
                        maxlength="500"
                        placeholder="Ví dụ: Cho ít đá, giao nhanh giúp mình..."
                        class="w-full resize-none rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                    />

                    <p
                        v-if="form.errors.order"
                        class="mt-2 text-sm text-red-400"
                    >
                        {{ form.errors.order }}
                    </p>

                    <div class="mt-4 space-y-2 border-t border-zinc-800 pt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-400">Tiền món</span>
                            <span>{{ formatMoney(productTotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-400">Tiền nạp</span>
                            <span class="text-emerald-400">
                                {{ formatMoney(topupAmount) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between pt-2">
                            <span class="font-semibold text-zinc-300">Tổng thanh toán</span>
                            <span class="text-2xl font-black text-red-400">
                                {{ formatMoney(total) }}
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="submitOrder"
                        :disabled="!canSubmit"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-4 font-bold hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-700"
                    >
                        <ShoppingCart class="h-5 w-5" />
                        {{ form.processing ? 'ĐANG GỬI ĐƠN...' : 'GỬI ĐƠN HÀNG' }}
                    </button>
                </div>
            </section>
        </div>

        <footer class="border-t border-zinc-800 px-4 py-6 text-center text-xs text-zinc-600">
            © CiCi Gaming
        </footer>
    </div>
</template>
