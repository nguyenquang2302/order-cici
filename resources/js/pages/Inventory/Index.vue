<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Package, Plus, X, Search, ArrowDownToLine, History } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    unit: string;
    stock_quantity: number;
    cost_price: string | number;
}

interface Transaction {
    id: number;
    product_id: number;
    product_name: string;
    unit: string;
    type: string;
    quantity: number;
    unit_cost: string | number;
    note: string | null;
    created_by_name: string | null;
    created_at: string;
}

const props = defineProps<{
    products: Product[];
    transactions: {
        data: Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

defineOptions({ layout: AppLayout });

const showModal = ref(false);
const search = ref('');

const form = useForm({
    product_id: '',
    quantity: 1,
    unit_cost: 0,
    note: '',
});

const filteredProducts = computed(() => {
    const keyword = search.value.toLowerCase().trim();

    if (!keyword) return props.products;

    return props.products.filter(product =>
        product.name.toLowerCase().includes(keyword) ||
        (product.sku ?? '').toLowerCase().includes(keyword)
    );
});

const totalProducts = computed(() => props.products.length);

const totalStock = computed(() =>
    props.products.reduce((sum, product) => sum + product.stock_quantity, 0)
);

const lowStockProducts = computed(() =>
    props.products.filter(product => product.stock_quantity <= 5).length
);

function openModal() {
    form.reset();
    form.product_id = '';
    form.quantity = 1;
    form.unit_cost = 0;
    showModal.value = true;
}

function selectProduct() {
    const product = props.products.find(
        item => item.id === Number(form.product_id)
    );

    if (product) {
        form.unit_cost = Number(product.cost_price);
    }
}

function submitForm() {
    form.post('/inventory', {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
}

function formatMoney(value: string | number) {
    return new Intl.NumberFormat('vi-VN').format(Number(value)) + 'đ';
}

function formatDate(value: string) {
    return new Date(value).toLocaleString('vi-VN');
}
</script>

<template>
    <Head title="Quản lý kho" />

    <div class="min-h-screen space-y-6 bg-[#09090b] p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-red-500">
                    <Package class="h-4 w-4" />
                    CICI GAMING
                </div>
                <h1 class="mt-2 text-2xl font-bold">Quản lý kho</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Theo dõi số lượng tồn kho và lịch sử nhập hàng.
                </p>
            </div>

            <button
                @click="openModal"
                class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold hover:bg-red-700"
            >
                <Plus class="h-4 w-4" />
                Nhập kho
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Sản phẩm đang kinh doanh</p>
                <p class="mt-2 text-3xl font-bold">{{ totalProducts }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Tổng số lượng tồn</p>
                <p class="mt-2 text-3xl font-bold">{{ totalStock }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Sản phẩm tồn thấp</p>
                <p class="mt-2 text-3xl font-bold text-red-400">
                    {{ lowStockProducts }}
                </p>
                <p class="mt-1 text-xs text-zinc-500">Tạm tính theo ngưỡng 5 đơn vị</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-5 py-4">
                <h2 class="font-semibold">Tồn kho sản phẩm</h2>

                <div class="relative">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-zinc-500" />
                    <input
                        v-model="search"
                        placeholder="Tìm sản phẩm..."
                        class="rounded-lg border border-zinc-700 bg-zinc-950 py-2 pl-9 pr-3 text-sm outline-none focus:border-red-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-950 text-zinc-400">
                        <tr>
                            <th class="px-5 py-4">Sản phẩm</th>
                            <th class="px-5 py-4">Mã SKU</th>
                            <th class="px-5 py-4">Tồn kho</th>
                            <th class="px-5 py-4">Giá nhập gần nhất</th>
                            <th class="px-5 py-4">Trạng thái</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="product in filteredProducts" :key="product.id">
                            <td class="px-5 py-4 font-medium">{{ product.name }}</td>
                            <td class="px-5 py-4 text-zinc-400">{{ product.sku || '—' }}</td>
                            <td class="px-5 py-4">
                                {{ product.stock_quantity }} {{ product.unit }}
                            </td>
                            <td class="px-5 py-4">{{ formatMoney(product.cost_price) }}</td>
                            <td class="px-5 py-4">
                                <span
                                    :class="product.stock_quantity <= 5
                                        ? 'text-red-400'
                                        : 'text-green-400'"
                                >
                                    {{ product.stock_quantity <= 5 ? 'Sắp hết hàng' : 'Còn hàng' }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="filteredProducts.length === 0">
                            <td colspan="5" class="px-5 py-10 text-center text-zinc-500">
                                Không tìm thấy sản phẩm.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="flex items-center gap-2 border-b border-zinc-800 px-5 py-4">
                <History class="h-5 w-5 text-red-500" />
                <h2 class="font-semibold">Lịch sử nhập kho</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-950 text-zinc-400">
                        <tr>
                            <th class="px-5 py-4">Thời gian</th>
                            <th class="px-5 py-4">Sản phẩm</th>
                            <th class="px-5 py-4">Số lượng</th>
                            <th class="px-5 py-4">Giá nhập</th>
                            <th class="px-5 py-4">Người nhập</th>
                            <th class="px-5 py-4">Ghi chú</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="item in transactions.data" :key="item.id">
                            <td class="whitespace-nowrap px-5 py-4 text-zinc-400">
                                {{ formatDate(item.created_at) }}
                            </td>
                            <td class="px-5 py-4 font-medium">{{ item.product_name }}</td>
                            <td class="px-5 py-4 text-green-400">
                                +{{ item.quantity }} {{ item.unit }}
                            </td>
                            <td class="px-5 py-4">{{ formatMoney(item.unit_cost) }}</td>
                            <td class="px-5 py-4">{{ item.created_by_name || 'Hệ thống' }}</td>
                            <td class="px-5 py-4 text-zinc-400">{{ item.note || '—' }}</td>
                        </tr>

                        <tr v-if="transactions.data.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-zinc-500">
                                Chưa có lịch sử nhập kho.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-zinc-800 p-4">
                <button
                    v-for="link in transactions.links"
                    :key="link.label"
                    :disabled="!link.url"
                    @click="link.url && router.visit(link.url)"
                    class="rounded-lg border border-zinc-700 px-3 py-2 text-sm disabled:opacity-30"
                    :class="link.active ? 'bg-red-600 border-red-600' : 'hover:bg-zinc-800'"
                    v-html="link.label"
                />
            </div>
        </section>

        <!-- Modal nhập kho -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="showModal = false"
        >
            <div class="w-full max-w-lg rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Nhập kho</h2>
                        <p class="mt-1 text-sm text-zinc-400">Tạo phiếu nhập hàng mới.</p>
                    </div>
                    <button @click="showModal = false">
                        <X class="h-5 w-5 text-zinc-400" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">Sản phẩm *</label>
                        <select
                            v-model="form.product_id"
                            @change="selectProduct"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white"
                        >
                            <option value="" disabled>Chọn sản phẩm</option>
                            <option v-for="product in products" :key="product.id" :value="String(product.id)">
                                {{ product.name }} (Tồn: {{ product.stock_quantity }})
                            </option>
                        </select>
                        <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-400">
                            {{ form.errors.product_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">Số lượng nhập *</label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white"
                        />
                        <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-400">
                            {{ form.errors.quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">Giá nhập / đơn vị *</label>
                        <input
                            v-model.number="form.unit_cost"
                            type="number"
                            min="0"
                            step="100"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white"
                        />
                        <p v-if="form.errors.unit_cost" class="mt-1 text-sm text-red-400">
                            {{ form.errors.unit_cost }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">Ghi chú</label>
                        <textarea
                            v-model="form.note"
                            rows="2"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white"
                        />
                    </div>

                    <div class="rounded-lg bg-zinc-950 p-4">
                        <p class="text-sm text-zinc-400">Tổng tiền nhập dự kiến</p>
                        <p class="mt-1 text-xl font-bold text-red-400">
                            {{ formatMoney(form.quantity * form.unit_cost) }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-zinc-700 px-4 py-2 text-sm"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Đang lưu...' : 'Xác nhận nhập kho' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
