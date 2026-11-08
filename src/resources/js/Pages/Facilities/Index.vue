<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { z } from 'zod';

// ==================== PROPS ====================
const props = defineProps({
    facilities: Object,
    filters: Object,
});

// ==================== FILTERS ====================
const search = ref(props.filters?.search || '');

let timer;
watch([search], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/facilities', {
            search: search.value,
        }, { preserveState: true, replace: true });
    }, 300);
});

// ==================== PAGINATION ====================
const currentPage = ref(props.facilities.current_page);
watch(currentPage, (page) => {
    router.get('/facilities', {
        search: search.value,
        page,
    }, { preserveState: true });
});

// ==================== TABLE COLUMNS ====================
const columns = [
    { id: 'name', header: 'Nama', accessorKey: 'name' },
    { id: 'icon', header: 'Icon', accessorKey: 'icon' },
    { id: 'actions', header: 'Aksi' },
];

// ==================== FORM & MODAL ====================
const isModalOpen = ref(false);
const modalMode = ref('create');

const form = useForm({
    id: null,
    name: '',
    icon: '',
});

// ==================== ZOD SCHEMAS ====================
const createSchema = z.object({
    name: z.string().min(1, 'Nama wajib diisi'),
    icon: z.string().nullable().optional(),
});

const editSchema = createSchema;

// Bridge Inertia server errors into UForm
const validate = () => {
    return Object.entries(form.errors).map(([name, message]) => ({ name, message }));
};

const clearError = (field) => {
    if (form.errors[field]) form.clearErrors(field);
};

// ==================== MODAL ACTIONS ====================
const openCreateModal = () => {
    modalMode.value = 'create';
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    modalMode.value = 'edit';
    form.reset();
    form.clearErrors();
    form.id = item.id;
    form.name = item.name;
    form.icon = item.icon || '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    if (modalMode.value === 'create') {
        form.post('/facilities', {
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(`/facilities/${form.id}`, {
            onSuccess: () => closeModal(),
        });
    }
};

const handleDelete = (item) => {
    if (confirm('Yakin ingin menghapus data ini?')) {
        router.delete(`/facilities/${item.id}`);
    }
};
</script>

<template>
    <DashboardLayout>
        <Head title="Fasilitas" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Fasilitas</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data fasilitas</p>
                </div>
                <UButton icon="i-heroicons-plus" size="lg" color="primary" @click="openCreateModal">
                    Tambah Fasilitas
                </UButton>
            </div>

            <!-- Filters -->
            <UCard>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <UInput
                            v-model="search"
                            icon="i-heroicons-magnifying-glass"
                            placeholder="Cari nama fasilitas..."
                            size="lg"
                        />
                    </div>
                </div>
            </UCard>

            <!-- Table -->
            <UCard>
                <UTable
                    sticky
                    class="max-h-[calc(100vh-300px)]"
                    :columns="columns"
                    :data="facilities.data"
                >
                    <template #empty>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Tidak ada data
                        </div>
                    </template>
                    <template #icon-cell="{ row }">
                        <div class="flex items-center gap-2">
                            <UIcon v-if="row.original.icon" :name="row.original.icon" class="w-5 h-5 text-gray-500" />
                            <span class="text-sm text-gray-500">{{ row.original.icon || '-' }}</span>
                        </div>
                    </template>

                    <template #actions-cell="{ row }">
                        <div class="flex items-center gap-1">
                            <UButton
                                icon="i-heroicons-pencil-square"
                                size="xs"
                                color="warning"
                                variant="ghost"
                                square
                                @click="openEditModal(row.original)"
                            />
                            <UButton
                                icon="i-heroicons-trash"
                                size="xs"
                                color="error"
                                variant="ghost"
                                square
                                @click="handleDelete(row.original)"
                            />
                        </div>
                    </template>
                </UTable>

                <template #footer>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Menampilkan {{ facilities.from }} - {{ facilities.to }} dari {{ facilities.total }} data
                        </span>
                        <UPagination
                            v-model:page="currentPage"
                            :total="facilities.total"
                            :items-per-page="25"
                        />
                    </div>
                </template>
            </UCard>
        </div>

        <!-- Create / Edit Modal -->
        <UModal
            v-model:open="isModalOpen"
            :title="modalMode === 'create' ? 'Tambah Fasilitas' : 'Edit Fasilitas'"
            prevent-close
            :dismissible="false"
        >
            <template #body>
                <UForm
                    :schema="modalMode === 'create' ? createSchema : editSchema"
                    :validate="validate"
                    :state="form"
                    @submit="handleSubmit"
                    class="space-y-4"
                >
                    <UAlert
                        icon="i-heroicons-information-circle"
                        color="info"
                        variant="soft"
                        title="Panduan Icon"
                    >
                        <template #description>
                            Anda bisa mencari icon di <a href="https://icones.js.org/" target="_blank" class="font-semibold underline">icones.js.org</a>. Salin nama icon (contoh: <code>i-lucide-wifi</code>) dan masukkan ke kolom di bawah.
                        </template>
                    </UAlert>

                    <UFormField label="Nama" name="name" required>
                        <UInput
                            v-model="form.name"
                            placeholder="Masukkan nama fasilitas"
                            class="w-full"
                            @update:model-value="clearError('name')"
                        />
                    </UFormField>

                    <UFormField label="Icon (Opsional)" name="icon">
                        <UInput
                            v-model="form.icon"
                            placeholder="Contoh: i-heroicons-wifi"
                            class="w-full"
                            @update:model-value="clearError('icon')"
                        >
                            <template #leading v-if="form.icon">
                                <UIcon :name="form.icon" class="w-5 h-5" />
                            </template>
                        </UInput>
                        <template #hint>
                            <span class="text-xs text-gray-500">Gunakan icon dari iconify (misal: i-lucide-wifi)</span>
                        </template>
                    </UFormField>
                </UForm>
            </template>

            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <UButton color="neutral" variant="soft" @click="closeModal">Batal</UButton>
                    <UButton color="primary" :loading="form.processing" @click="handleSubmit">
                        {{ modalMode === 'create' ? 'Simpan' : 'Simpan Perubahan' }}
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
