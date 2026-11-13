<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Tags, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    products_count: number;
    is_active: boolean;
}

const props = defineProps<{
    categories: Category[];
}>();

defineOptions({ layout: AppLayout });

const showModal = ref(false);
const editingCategory = ref<Category | null>(null);

const form = useForm({
    name: '',
    description: '',
});

function openCreate() {
    editingCategory.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(category: Category) {
    editingCategory.value = category;
    form.name = category.name;
    form.description = category.description ?? '';
    showModal.value = true;
}

function submitForm() {
    if (editingCategory.value) {
        form.put(`/categories/${editingCategory.value.id}`, {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/categories', {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
}

function deleteCategory(category: Category) {
    if (category.products_count > 0) {
        alert('Danh mục đang có sản phẩm, không thể xóa.');
        return;
    }

    if (confirm(`Bạn có chắc muốn xóa danh mục "${category.name}"?`)) {
        router.delete(`/categories/${category.id}`);
    }
}
</script>

<template>
    <Head title="Quản lý danh mục" />

    <div class="min-h-screen space-y-6 bg-[#09090b] p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-red-500">
                    <Tags class="h-4 w-4" />
                    CICI GAMING
                </div>
                <h1 class="mt-2 text-2xl font-bold">Quản lý danh mục</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Quản lý các nhóm sản phẩm trong cửa hàng.
                </p>
            </div>

            <button
                @click="openCreate"
                class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold hover:bg-red-700"
            >
                <Plus class="h-4 w-4" />
                Thêm danh mục
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Tổng danh mục</p>
                <p class="mt-2 text-3xl font-bold">{{ categories.length }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Tổng sản phẩm</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ categories.reduce((sum, item) => sum + item.products_count, 0) }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <p class="text-sm text-zinc-400">Danh mục đang hoạt động</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ categories.filter(item => item.is_active).length }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="border-b border-zinc-800 px-5 py-4">
                <h2 class="font-semibold">Danh sách danh mục</h2>
            </div>

            <div v-if="categories.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-950 text-zinc-400">
                        <tr>
                            <th class="px-5 py-4">Tên danh mục</th>
                            <th class="px-5 py-4">Slug</th>
                            <th class="px-5 py-4">Số sản phẩm</th>
                            <th class="px-5 py-4">Trạng thái</th>
                            <th class="px-5 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-800">
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            class="hover:bg-zinc-800/50"
                        >
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ category.name }}</div>
                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ category.description || 'Chưa có mô tả' }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-zinc-400">
                                {{ category.slug }}
                            </td>

                            <td class="px-5 py-4">
                                {{ category.products_count }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs"
                                    :class="category.is_active
                                        ? 'bg-green-500/10 text-green-400'
                                        : 'bg-zinc-700 text-zinc-400'"
                                >
                                    {{ category.is_active ? 'Hoạt động' : 'Đã tắt' }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="openEdit(category)"
                                        class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white"
                                        title="Sửa"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>

                                    <button
                                        @click="deleteCategory(category)"
                                        class="rounded-lg p-2 text-zinc-400 hover:bg-red-500/10 hover:text-red-400"
                                        title="Xóa"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="p-10 text-center text-zinc-500">
                Chưa có danh mục nào.
            </div>
        </div>

        <!-- Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="showModal = false"
        >
            <div class="w-full max-w-lg rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-bold">
                        {{ editingCategory ? 'Cập nhật danh mục' : 'Thêm danh mục mới' }}
                    </h2>

                    <button @click="showModal = false" class="text-zinc-400 hover:text-white">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">
                            Tên danh mục *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="100"
                            placeholder="Ví dụ: Nước uống"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white outline-none focus:border-red-500"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm text-zinc-300">Mô tả</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            maxlength="500"
                            placeholder="Mô tả danh mục..."
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white outline-none focus:border-red-500"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-400">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 pt-3">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-zinc-700 px-4 py-2 text-sm hover:bg-zinc-800"
                        >
                            Hủy
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Đang lưu...' : 'Lưu danh mục' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>