<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { z } from 'zod';

// ==================== PROPS ====================
const props = defineProps({
    property_types: Object,
    filters: Object,
});

// ==================== FILTERS ====================
const search = ref(props.filters?.search || '');

let timer;
watch([search], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/property-types', {
            search: search.value,
        }, { preserveState: true, replace: true });
    }, 300);
});

// ==================== PAGINATION ====================
const currentPage = ref(props.property_types.current_page);
watch(currentPage, (page) => {
    router.get('/property-types', {
        search: search.value,
        page,
    }, { preserveState: true });
});

// ==================== TABLE COLUMNS ====================
const columns = [
    { id: 'name', header: 'Nama', accessorKey: 'name' },
    { id: 'description', header: 'Deskripsi', accessorKey: 'description' },
    { id: 'actions', header: 'Aksi' },
];

// ==================== FORM & MODAL ====================
const isModalOpen = ref(false);
const modalMode = ref('create');

const form = useForm({
    id: null,
    name: '',
    description: '',
});

// ==================== ZOD SCHEMAS ====================
const createSchema = z.object({
    name: z.string().min(1, 'Nama wajib diisi'),
    description: z.string().max(1000, 'Maksimal 1000 karakter').optional().nullable(),
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
    form.description = item.description || '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    if (modalMode.value === 'create') {
        form.post('/property-types', {
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(`/property-types/${form.id}`, {
            onSuccess: () => closeModal(),
        });
    }
};

const handleDelete = (item) => {
    if (confirm('Yakin ingin menghapus data ini?')) {
        router.delete(`/property-types/${item.id}`);
    }
};
</script>

<template>
    <DashboardLayout>
        <Head title="Tipe Properti" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tipe Properti</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data tipe properti</p>
                </div>
                <UButton icon="i-heroicons-plus" size="lg" color="primary" @click="openCreateModal">
                    Tambah Tipe
                </UButton>
            </div>

            <!-- Filters -->
            <UCard>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <UInput
                            v-model="search"
                            icon="i-heroicons-magnifying-glass"
                            placeholder="Cari nama tipe..."
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
                    :data="property_types.data"
                >
                    <template #empty>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Tidak ada data
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
                            Menampilkan {{ property_types.from ?? 0 }} - {{ property_types.to ?? 0 }} dari {{ property_types.total }} data
                        </span>
                        <UPagination
                            v-if="property_types.total > 0"
                            v-model:page="currentPage"
                            :total="property_types.total"
                            :items-per-page="25"
                        />
                    </div>
                </template>
            </UCard>
        </div>

        <!-- Create / Edit Modal -->
        <UModal
            v-model:open="isModalOpen"
            :title="modalMode === 'create' ? 'Tambah Tipe Properti' : 'Edit Tipe Properti'"
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
                    <UFormField label="Nama" name="name" required>
                        <UInput
                            v-model="form.name"
                            placeholder="Masukkan nama"
                            class="w-full"
                            @update:model-value="clearError('name')"
                        />
                    </UFormField>

                    <UFormField label="Deskripsi" name="description">
                        <UTextarea
                            v-model="form.description"
                            placeholder="Masukkan deskripsi (opsional)"
                            class="w-full"
                            autoresize
                            :rows="3"
                            @update:model-value="clearError('description')"
                        />
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
