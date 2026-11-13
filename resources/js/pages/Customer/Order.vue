<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Plus,
    Minus,
    Gamepad2,
    X,
    Trash2,
} from '@lucide/vue';

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

const props = defineProps<{
    pc: {
        id: number | null;
        code: string;
        name: string;
    };
    categories: Category[];
}>();

const activeCategory = ref<number | null>(null);
const cart = ref<CartItem[]>([]);
const successMessage = ref('');
const mobileCartOpen = ref(false);

const form = useForm({
    items: [] as { product_id: number; quantity: number }[],
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

const total = computed(() =>
    cart.value.reduce(
        (sum, item) => sum + Number(item.product.selling_price) * item.quantity,
        0
    )
);

function formatMoney(value: number | string) {
    return new Intl.NumberFormat('vi-VN').format(Number(value)) + 'đ';
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

function submitOrder() {
    if (!cart.value.length || form.processing) return;

    successMessage.value = '';

    form.items = cart.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
    }));

    form.post('/dat-mon', {
        preserveScroll: true,

        onSuccess: () => {
            cart.value = [];
            form.reset();
            mobileCartOpen.value = false;

            successMessage.value =
                'Đặt hàng thành công! Nhân viên sẽ phục vụ bạn trong giây lát.';
        },
    });
}
</script>

<template>
    <Head title="Gọi món - CiCi Gaming" />

    <div class="min-h-screen bg-[#09090b] text-white pb-24 lg:pb-0">
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
                        <p class="text-xs text-zinc-400">Thế giới giải trí của bạn</p>
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
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_390px]">
                <!-- Khu vực danh sách sản phẩm -->
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
                                <ShoppingCart v-else class="h-10 w-10 text-zinc-600" />
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

                <!-- Sidebar giỏ hàng desktop -->
                <aside class="hidden lg:block">
                    <section class="sticky top-24 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-xl">
                        <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-950/70 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-600/15 text-red-400">
                                    <ShoppingCart class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="font-bold">Giỏ hàng</h2>
                                    <p class="text-xs text-zinc-500">Đơn hàng của bạn</p>
                                </div>
                            </div>

                            <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-bold">
                                {{ cartCount }} món
                            </span>
                        </div>

                        <div class="max-h-[calc(100vh-390px)] min-h-[150px] overflow-y-auto px-5">
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
                                                @click="changeQuantity(item.product.id, -1)"
                                                class="rounded-md border border-zinc-700 p-1.5 hover:bg-zinc-800"
                                            >
                                                <Minus class="h-3.5 w-3.5" />
                                            </button>

                                            <span class="w-6 text-center text-sm font-bold">
                                                {{ item.quantity }}
                                            </span>

                                            <button
                                                @click="changeQuantity(item.product.id, 1)"
                                                :disabled="item.quantity >= item.product.stock_quantity"
                                                class="rounded-md border border-zinc-700 p-1.5 hover:bg-zinc-800 disabled:opacity-30"
                                            >
                                                <Plus class="h-3.5 w-3.5" />
                                            </button>

                                            <button
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

                            <div v-else class="flex min-h-[180px] flex-col items-center justify-center text-center">
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

                            <p v-if="form.errors.order" class="mt-3 rounded-lg border border-red-800 bg-red-950/50 p-3 text-sm text-red-300">
                                {{ form.errors.order }}
                            </p>

                            <p v-if="successMessage" class="mt-3 rounded-lg border border-green-800 bg-green-950/50 p-3 text-sm text-green-300">
                                {{ successMessage }}
                            </p>

                            <div class="mt-4 flex items-center justify-between border-t border-zinc-800 pt-4">
                                <span class="font-semibold text-zinc-400">Tổng cộng</span>
                                <span class="text-2xl font-black text-red-400">
                                    {{ formatMoney(total) }}
                                </span>
                            </div>

                            <button
                                @click="submitOrder"
                                :disabled="!cart.length || form.processing"
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
                @click="mobileCartOpen = true"
                class="flex w-full items-center justify-between rounded-xl bg-red-600 px-4 py-3.5 font-bold hover:bg-red-700"
            >
                <span class="flex items-center gap-2">
                    <ShoppingCart class="h-5 w-5" />
                    Giỏ hàng ({{ cartCount }})
                </span>
                <span>{{ formatMoney(total) }} · Xem giỏ hàng</span>
            </button>
        </div>

        <!-- Giỏ hàng dạng drawer trên mobile -->
        <div
            v-if="mobileCartOpen"
            class="fixed inset-0 z-50 flex items-end bg-black/70 lg:hidden"
            @click.self="mobileCartOpen = false"
        >
            <section class="max-h-[90vh] w-full overflow-hidden rounded-t-2xl border-t border-zinc-700 bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                    <div>
                        <h2 class="font-bold">Giỏ hàng của bạn</h2>
                        <p class="text-xs text-zinc-500">{{ cartCount }} món đã chọn</p>
                    </div>
                    <button
                        @click="mobileCartOpen = false"
                        class="rounded-lg border border-zinc-700 p-2"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[45vh] overflow-y-auto px-5">
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
                                    @click="changeQuantity(item.product.id, -1)"
                                    class="rounded-md border border-zinc-700 p-2"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>

                                <span class="w-5 text-center text-sm font-bold">
                                    {{ item.quantity }}
                                </span>

                                <button
                                    @click="changeQuantity(item.product.id, 1)"
                                    :disabled="item.quantity >= item.product.stock_quantity"
                                    class="rounded-md border border-zinc-700 p-2 disabled:opacity-30"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <button
                                @click="removeFromCart(item.product.id)"
                                class="text-zinc-500 hover:text-red-400"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-else class="py-10 text-center text-sm text-zinc-500">
                        Bạn chưa chọn món nào.
                    </div>
                </div>

                <div class="border-t border-zinc-800 p-5">
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

                    <p v-if="form.errors.order" class="mt-2 text-sm text-red-400">
                        {{ form.errors.order }}
                    </p>

                    <p v-if="successMessage" class="mt-2 text-sm text-green-400">
                        {{ successMessage }}
                    </p>

                    <div class="mt-4 flex items-center justify-between">
                        <span class="font-semibold text-zinc-400">Tổng cộng</span>
                        <span class="text-2xl font-black text-red-400">
                            {{ formatMoney(total) }}
                        </span>
                    </div>

                    <button
                        @click="submitOrder"
                        :disabled="!cart.length || form.processing"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-4 font-bold hover:bg-red-700 disabled:bg-zinc-700"
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
