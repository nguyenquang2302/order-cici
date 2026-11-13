<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShoppingCart, Plus, Minus, Gamepad2 } from '@lucide/vue';

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

    item.quantity += amount;

    if (item.quantity <= 0) {
        cart.value = cart.value.filter(i => i.product.id !== productId);
    }
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

            successMessage.value = 'Đặt hàng thành công! Nhân viên sẽ phục vụ bạn trong giây lát.';
        },
    });
}
</script>

<template>
    <Head title="Gọi món - CiCi Gaming" />

    <div class="min-h-screen bg-[#09090b] text-white">
        <header class="sticky top-0 z-30 border-b border-zinc-800 bg-[#09090b]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4">
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
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500">Máy đang sử dụng</p>
                    <p class="font-bold text-red-400">{{ pc.code }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6">
            <section class="mb-7 rounded-2xl border border-zinc-800 bg-gradient-to-r from-red-950/50 to-zinc-900 p-6">
                <p class="text-sm font-semibold text-red-400">CICI GAMING MENU</p>
                <h2 class="mt-2 text-2xl font-black sm:text-3xl">
                    Bạn muốn dùng gì hôm nay?
                </h2>
                <p class="mt-2 text-sm text-zinc-400">
                    Chọn món yêu thích, chúng tôi sẽ phục vụ tận máy {{ pc.code }}.
                </p>
            </section>

            <div class="mb-6 flex flex-wrap gap-2">
                <button
                    @click="activeCategory = null"
                    class="rounded-full border px-5 py-2 text-sm font-semibold"
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
                    class="rounded-full border px-5 py-2 text-sm font-semibold"
                    :class="activeCategory === category.id
                        ? 'border-red-600 bg-red-600 text-white'
                        : 'border-zinc-700 bg-zinc-900 text-zinc-300 hover:border-red-500'"
                >
                    {{ category.name }}
                </button>
            </div>

            <div v-if="visibleProducts.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <article
                    v-for="product in visibleProducts"
                    :key="product.id"
                    class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900"
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

                        <button
                            @click="addToCart(product)"
                            :disabled="product.stock_quantity <= 0"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 py-2.5 text-sm font-bold hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-700"
                        >
                            <Plus class="h-4 w-4" />
                            {{ product.stock_quantity > 0 ? 'Thêm món' : 'Hết hàng' }}
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center text-zinc-500">
                Chưa có sản phẩm trong danh mục này.
            </div>

            <section class="mt-8 rounded-2xl border border-zinc-800 bg-zinc-900 p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <ShoppingCart class="h-6 w-6 text-red-500" />
                        <h2 class="text-lg font-bold">Giỏ hàng của bạn</h2>
                    </div>
                    <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-bold">
                        {{ cartCount }} món
                    </span>
                </div>

                <div v-if="cart.length" class="mt-5 divide-y divide-zinc-800">
                    <div
                        v-for="item in cart"
                        :key="item.product.id"
                        class="flex items-center justify-between gap-3 py-4"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ item.product.name }}</p>
                            <p class="mt-1 text-sm text-red-400">
                                {{ formatMoney(Number(item.product.selling_price) * item.quantity) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                @click="changeQuantity(item.product.id, -1)"
                                class="rounded-lg border border-zinc-700 p-2 hover:bg-zinc-800"
                            >
                                <Minus class="h-4 w-4" />
                            </button>

                            <span class="w-5 text-center font-bold">{{ item.quantity }}</span>

                            <button
                                @click="addToCart(item.product)"
                                :disabled="item.quantity >= item.product.stock_quantity"
                                class="rounded-lg border border-zinc-700 p-2 hover:bg-zinc-800 disabled:opacity-30"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="py-8 text-center text-sm text-zinc-500">
                    Bạn chưa chọn món nào.
                </div>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-semibold text-zinc-300">
                        Ghi chú cho nhân viên (không bắt buộc)
                    </label>
                    <textarea
                        v-model="form.note"
                        rows="2"
                        maxlength="500"
                        placeholder="Ví dụ: Cho ít đá, giao nhanh giúp mình..."
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-800 p-3 text-sm text-white placeholder-zinc-500 focus:border-red-500 focus:outline-none"
                    ></textarea>
                </div>

                <p v-if="form.errors.order" class="mt-3 rounded-lg border border-red-800 bg-red-950/50 p-3 text-sm text-red-300">
                    {{ form.errors.order }}
                </p>

                <p v-if="successMessage" class="mt-3 rounded-lg border border-green-800 bg-green-950/50 p-3 text-sm text-green-300">
                    {{ successMessage }}
                </p>

                <div class="mt-4 flex items-center justify-between border-t border-zinc-800 pt-5">
                    <span class="font-semibold text-zinc-400">Tổng cộng</span>
                    <span class="text-2xl font-black text-red-400">
                        {{ formatMoney(total) }}
                    </span>
                </div>

                <button
                    @click="submitOrder"
                    :disabled="!cart.length || form.processing"
                    class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-4 font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-700"
                >
                    <ShoppingCart class="h-5 w-5" />
                    {{ form.processing ? 'ĐANG GỬI ĐƠN...' : 'GỬI ĐƠN HÀNG' }}
                </button>
            </section>
        </main>

        <footer class="border-t border-zinc-800 px-4 py-6 text-center text-xs text-zinc-600">
            © CiCi Gaming
        </footer>
    </div>
</template>