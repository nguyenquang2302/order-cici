
<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Search,
    Plus,
    Pencil,
    Trash2,
    Package,
    X,
    Power,
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Category = {
    id: number;
    name: string;
};

type Product = {
    id: number;
    category_id: number;
    sku: string | null;
    name: string;
    description: string | null;
    unit: string;
    units_per_package: number;
    cost_price: string | number;
    selling_price: string | number;
    stock_quantity: number;
    low_stock_threshold: number;
    is_active: boolean;
    image_path: string | null;
    category: Category;
};

const props = defineProps<{
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
    };
}>();

defineOptions({
    layout: AppLayout,
});

const search = ref(props.filters.search ?? '');
const categoryFilter = ref(props.filters.category_id ?? '');
const statusFilter = ref(props.filters.status ?? '');
const showModal = ref(false);
const editingProduct = ref<Product | null>(null);
const imagePreview = ref<string | null>(null);

const form = useForm({
    category_id: '',
    sku: '',
    name: '',
    description: '',
    unit: 'cái',
    units_per_package: 1,
    cost_price: 0,
    selling_price: 0,
    stock_quantity: 0,
    low_stock_threshold: 5,
    image: null as File | null,
});

const money = (value: string | number) =>
    new Intl.NumberFormat('vi-VN').format(Number(value));

const lowStockCount = computed(() =>
    props.products.data.filter(
        (p) => p.is_active && p.stock_quantity <= p.low_stock_threshold,
    ).length,
);

function clearImagePreview() {
    if (imagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }

    imagePreview.value = null;
}

function closeModal() {
    showModal.value = false;
    clearImagePreview();
}

function handleImageChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (imagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }

    form.image = file;

    if (file) {
        imagePreview.value = URL.createObjectURL(file);
    } else {
        imagePreview.value = editingProduct.value?.image_path
            ? `/storage/${editingProduct.value.image_path}`
            : null;
    }
}

function filterProducts() {
    router.get(
        '/products',
        {
            search: search.value || undefined,
            category_id: categoryFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function openCreate() {
    clearImagePreview();

    editingProduct.value = null;
    form.reset();
    form.clearErrors();

    form.unit = 'cái';
    form.units_per_package = 1;
    form.low_stock_threshold = 5;
    form.image = null;

    showModal.value = true;
}

function openEdit(product: Product) {
    clearImagePreview();

    editingProduct.value = product;
    form.clearErrors();

    form.category_id = String(product.category_id);
    form.sku = product.sku ?? '';
    form.name = product.name;
    form.description = product.description ?? '';
    form.unit = product.unit;
    form.units_per_package = product.units_per_package ?? 1;
    form.cost_price = Number(product.cost_price);
    form.selling_price = Number(product.selling_price);
    form.stock_quantity = product.stock_quantity;
    form.low_stock_threshold = product.low_stock_threshold;
    form.image = null;

    imagePreview.value = product.image_path
        ? `/storage/${product.image_path}`
        : null;

    showModal.value = true;
}

function submitForm() {
    if (editingProduct.value) {
        form
            .transform((data) => ({
                ...data,
                _method: 'put',
            }))
            .post(`/products/${editingProduct.value.id}`, {
                forceFormData: true,
                onSuccess: () => {
                    closeModal();
                    form.reset();
                    editingProduct.value = null;
                },
            });
    } else {
        form.post('/products', {
            forceFormData: true,
            onSuccess: () => {
                closeModal();
                form.reset();
            },
        });
    }
}

function toggleProductStatus(product: Product) {
    const action = product.is_active ? 'ngừng bán' : 'mở bán lại';

    if (!confirm(`Bạn có chắc muốn ${action} sản phẩm "${product.name}"?`)) {
        return;
    }

    router.post(
        `/products/${product.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function deleteProduct(product: Product) {
    if (!confirm(`Bạn có chắc muốn xóa sản phẩm "${product.name}"?`)) {
        return;
    }

    router.delete(`/products/${product.id}`, {
        preserveScroll: true,
    });
}

onBeforeUnmount(() => {
    clearImagePreview();
});
</script>

<template>
    <Head title="Quản lý sản phẩm - CiCi Gaming" />

    <div class="min-h-screen space-y-6 bg-background p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Package class="h-4 w-4" />
                    <span>CiCi Gaming / Quản lý</span>
                </div>

                <h1 class="mt-2 text-2xl font-black tracking-tight">
                    Quản lý sản phẩm
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Quản lý hàng hóa, hình ảnh, giá bán và tồn kho.
                </p>
            </div>

            <button
                @click="openCreate"
                class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700"
            >
                <Plus class="h-4 w-4" />
                Thêm sản phẩm
            </button>
        </div>

        <!-- Statistics -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Tổng sản phẩm</p>
                <p class="mt-2 text-3xl font-black">{{ products.total }}</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Danh mục</p>
                <p class="mt-2 text-3xl font-black">{{ categories.length }}</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    Sắp hết hàng (trang hiện tại)
                </p>
                <p class="mt-2 text-3xl font-black text-red-500">
                    {{ lowStockCount }}
                </p>
            </div>
        </div>

        <!-- Search and filters -->
        <div class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 md:flex-row">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />

                <input
                    v-model="search"
                    @keyup.enter="filterProducts"
                    placeholder="Tìm tên sản phẩm hoặc mã SKU..."
                    class="w-full rounded-lg border border-border bg-background py-2.5 pl-10 pr-3 text-sm outline-none focus:border-red-500"
                />
            </div>

            <select
                v-model="categoryFilter"
                @change="filterProducts"
                class="rounded-lg border border-border bg-background px-3 py-2.5 text-sm"
            >
                <option value="">Tất cả danh mục</option>

                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="String(category.id)"
                >
                    {{ category.name }}
                </option>
            </select>

            <select
                v-model="statusFilter"
                @change="filterProducts"
                class="rounded-lg border border-border bg-background px-3 py-2.5 text-sm"
            >
                <option value="">Tất cả trạng thái</option>
                <option value="active">Đang bán</option>
                <option value="inactive">Ngừng bán</option>
            </select>

            <button
                @click="filterProducts"
                class="rounded-lg bg-secondary px-5 py-2.5 text-sm font-semibold hover:bg-secondary/70"
            >
                Tìm kiếm
            </button>
        </div>

        <!-- Product table -->
        <div class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="border-b border-border px-5 py-4">
                <h2 class="font-bold">Danh sách sản phẩm</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Sản phẩm ngừng bán vẫn được lưu để bảo toàn lịch sử giao dịch.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-5 py-4">Sản phẩm</th>
                            <th class="px-5 py-4">Danh mục</th>
                            <th class="px-5 py-4">Giá vốn</th>
                            <th class="px-5 py-4">Giá bán</th>
                            <th class="px-5 py-4">Tồn kho</th>
                            <th class="px-5 py-4">Trạng thái</th>
                            <th class="px-5 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="transition hover:bg-secondary/30"
                            :class="{ 'opacity-60': !product.is_active }"
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="product.image_path"
                                        :src="`/storage/${product.image_path}`"
                                        :alt="product.name"
                                        class="h-12 w-12 rounded-lg border border-border object-cover"
                                    />

                                    <div
                                        v-else
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-secondary"
                                    >
                                        <Package class="h-6 w-6 text-muted-foreground" />
                                    </div>

                                    <div>
                                        <p class="font-semibold">
                                            {{ product.name }}
                                        </p>

                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ product.sku || 'Chưa có SKU' }}
                                        </p>

                                        <p class="mt-1 text-xs text-blue-400">
                                            {{ product.units_per_package }}
                                            {{ product.unit }}/thùng
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                {{ product.category?.name }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                {{ money(product.cost_price) }}đ
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 font-semibold">
                                {{ money(product.selling_price) }}đ
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    :class="
                                        product.stock_quantity <= product.low_stock_threshold
                                            ? 'text-red-500'
                                            : 'text-green-500'
                                    "
                                    class="font-bold"
                                >
                                    {{ product.stock_quantity }} {{ product.unit }}
                                </span>

                                <p
                                    v-if="product.stock_quantity <= product.low_stock_threshold"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    Sắp hết hàng
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    v-if="product.is_active"
                                    class="inline-flex whitespace-nowrap rounded-full bg-green-500/10 px-2.5 py-1 text-xs font-semibold text-green-500"
                                >
                                    Đang bán
                                </span>

                                <span
                                    v-else
                                    class="inline-flex whitespace-nowrap rounded-full bg-zinc-500/10 px-2.5 py-1 text-xs font-semibold text-zinc-400"
                                >
                                    Ngừng bán
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="toggleProductStatus(product)"
                                        :title="product.is_active ? 'Ngừng bán' : 'Mở bán lại'"
                                        :class="
                                            product.is_active
                                                ? 'text-amber-500 hover:bg-amber-500/10'
                                                : 'text-green-500 hover:bg-green-500/10'
                                        "
                                        class="flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-border px-2.5 py-2 text-xs font-semibold"
                                    >
                                        <Power class="h-4 w-4" />
                                        {{ product.is_active ? 'Ngừng bán' : 'Mở bán lại' }}
                                    </button>

                                    <button
                                        @click="openEdit(product)"
                                        title="Sửa"
                                        class="rounded-lg border border-border p-2 hover:bg-secondary"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>

                                    <button
                                        @click="deleteProduct(product)"
                                        title="Xóa"
                                        class="rounded-lg border border-border p-2 text-red-500 hover:bg-red-500/10"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="products.data.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-16 text-center text-muted-foreground"
                            >
                                Không tìm thấy sản phẩm phù hợp.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4">
                <p class="text-sm text-muted-foreground">
                    Tổng {{ products.total }} sản phẩm
                </p>

                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="(link, index) in products.links"
                        :key="index"
                    >
                        <button
                            v-if="link.url"
                            @click="router.visit(link.url, { preserveScroll: true })"
                            v-html="link.label"
                            :disabled="link.active"
                            :class="
                                link.active
                                    ? 'bg-red-600 text-white'
                                    : 'border border-border hover:bg-secondary'
                            "
                            class="min-w-9 rounded-md px-3 py-2 text-sm disabled:cursor-default"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="closeModal"
        >
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-border bg-card p-5">
                    <div>
                        <h2 class="text-lg font-black">
                            {{ editingProduct ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm mới' }}
                        </h2>

                        <p class="mt-1 text-xs text-muted-foreground">
                            Nhập thông tin, hình ảnh, giá bán và quy cách đóng thùng.
                        </p>
                    </div>

                    <button
                        @click="closeModal"
                        class="rounded-lg p-2 hover:bg-secondary"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form
                    @submit.prevent="submitForm"
                    class="space-y-4 p-5"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Image upload -->
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium">
                                Hình ảnh sản phẩm
                            </label>

                            <div class="flex flex-wrap items-start gap-4">
                                <div class="flex h-32 w-32 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-secondary">
                                    <img
                                        v-if="imagePreview"
                                        :src="imagePreview"
                                        alt="Ảnh xem trước"
                                        class="h-full w-full object-cover"
                                    />

                                    <Package
                                        v-else
                                        class="h-10 w-10 text-muted-foreground"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        @change="handleImageChange"
                                        class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium"
                                    />

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        JPG, PNG hoặc WEBP. Dung lượng tối đa 2MB.
                                    </p>

                                    <p
                                        v-if="form.errors.image"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.image }}
                                    </p>

                                    <p
                                        v-if="editingProduct"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Không chọn ảnh mới thì ảnh hiện tại sẽ được giữ nguyên.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Product name -->
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium">
                                Tên sản phẩm *
                            </label>

                            <input
                                v-model="form.name"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Danh mục *
                            </label>

                            <select
                                v-model="form.category_id"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            >
                                <option value="">Chọn danh mục</option>

                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.category_id"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.category_id }}
                            </p>
                        </div>

                        <!-- SKU -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Mã SKU
                            </label>

                            <input
                                v-model="form.sku"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.sku"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.sku }}
                            </p>
                        </div>

                        <!-- Cost price -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Giá vốn (VNĐ) *
                            </label>

                            <input
                                v-model.number="form.cost_price"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.cost_price"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.cost_price }}
                            </p>
                        </div>

                        <!-- Selling price -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Giá bán (VNĐ) *
                            </label>

                            <input
                                v-model.number="form.selling_price"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.selling_price"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.selling_price }}
                            </p>
                        </div>

                        <!-- Stock -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Số lượng tồn kho *
                            </label>

                            <input
                                v-model.number="form.stock_quantity"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.stock_quantity"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.stock_quantity }}
                            </p>
                        </div>

                        <!-- Low stock threshold -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Cảnh báo khi còn
                            </label>

                            <input
                                v-model.number="form.low_stock_threshold"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.low_stock_threshold"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.low_stock_threshold }}
                            </p>
                        </div>

                        <!-- Unit -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Đơn vị bán *
                            </label>

                            <input
                                v-model="form.unit"
                                required
                                placeholder="chai, lon, gói..."
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.unit"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.unit }}
                            </p>
                        </div>

                        <!-- Units per package -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Số {{ form.unit || 'đơn vị' }} trong 1 thùng *
                            </label>

                            <input
                                v-model.number="form.units_per_package"
                                type="number"
                                min="1"
                                max="10000"
                                required
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p class="mt-1 text-xs text-muted-foreground">
                                Ví dụ: 24 lon/thùng hoặc 24 chai/thùng.
                                Sản phẩm không đóng thùng thì nhập 1.
                            </p>

                            <p
                                v-if="form.errors.units_per_package"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.units_per_package }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium">
                                Mô tả
                            </label>

                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5"
                            />

                            <p
                                v-if="form.errors.description"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Package preview -->
                    <div class="rounded-lg border border-border bg-secondary/30 p-4">
                        <p class="text-sm font-semibold">
                            Quy cách đóng thùng
                        </p>

                        <p class="mt-1 text-sm text-muted-foreground">
                            1 thùng = {{ form.units_per_package || 1 }}
                            {{ form.unit || 'đơn vị' }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 border-t border-border pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded-lg border border-border px-4 py-2.5 text-sm"
                        >
                            Hủy
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Đang lưu...' : 'Lưu sản phẩm' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
