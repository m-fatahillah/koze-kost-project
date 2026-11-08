<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { z } from 'zod';

const props = defineProps({
    faqs: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/faqs', {
            search: search.value,
        }, { preserveState: true, replace: true });
    }, 300);
});

const currentPage = ref(props.faqs.current_page);
watch(currentPage, (page) => {
    router.get('/faqs', {
        search: search.value,
        page,
    }, { preserveState: true });
});

const columns = [
    { id: 'pertanyaan', header: 'Pertanyaan', accessorKey: 'pertanyaan' },
    { id: 'jawaban', header: 'Jawaban', accessorKey: 'jawaban' },
    { id: 'actions', header: 'Aksi' },
];

const isModalOpen = ref(false);
const modalMode = ref('create');

const form = useForm({
    id: null,
    pertanyaan: '',
    jawaban: '',
});

const createSchema = z.object({
    pertanyaan: z.string().min(1, 'Pertanyaan wajib diisi').max(255, 'Maksimal 255 karakter'),
    jawaban: z.string().min(1, 'Jawaban wajib diisi'),
});

const editSchema = createSchema;

const validate = () => {
    return Object.entries(form.errors).map(([name, message]) => ({ name, message }));
};

const clearError = (field) => {
    if (form.errors[field]) form.clearErrors(field);
};

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
    form.pertanyaan = item.pertanyaan;
    form.jawaban = item.jawaban;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    if (modalMode.value === 'create') {
        form.post('/faqs', {
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(`/faqs/${form.id}`, {
            onSuccess: () => closeModal(),
        });
    }
};

const handleDelete = (item) => {
    if (confirm('Yakin ingin menghapus data ini?')) {
        router.delete(`/faqs/${item.id}`);
    }
};
</script>

<template>
    <DashboardLayout>
        <Head title="FAQ" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Daftar Pertanyaan Umum</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data pertanyaan umum (FAQ)</p>
                </div>
                <UButton icon="i-heroicons-plus" size="lg" color="primary" @click="openCreateModal">
                    Tambah Pertanyaan
                </UButton>
            </div>

            <UCard>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <UInput
                            v-model="search"
                            icon="i-heroicons-magnifying-glass"
                            placeholder="Cari pertanyaan atau jawaban..."
                            size="lg"
                        />
                    </div>
                </div>
            </UCard>

            <UCard>
                <UTable
                    sticky
                    class="max-h-[calc(100vh-300px)]"
                    :columns="columns"
                    :data="faqs.data"
                >
                    <template #empty>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Tidak ada data
                        </div>
                    </template>
                    <template #jawaban-cell="{ row }">
                        <span class="truncate block max-w-xs" :title="row.original.jawaban">
                            {{ row.original.jawaban.length > 50 ? row.original.jawaban.substring(0, 50) + '...' : row.original.jawaban }}
                        </span>
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
                            Menampilkan {{ faqs.from || 0 }} - {{ faqs.to || 0 }} dari {{ faqs.total || 0 }} data
                        </span>
                        <UPagination
                            v-model:page="currentPage"
                            :total="faqs.total || 0"
                            :items-per-page="25"
                        />
                    </div>
                </template>
            </UCard>
        </div>

        <UModal
            v-model:open="isModalOpen"
            :title="modalMode === 'create' ? 'Tambah FAQ' : 'Edit FAQ'"
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
                    <UFormField label="Pertanyaan" name="pertanyaan" required>
                        <UInput
                            v-model="form.pertanyaan"
                            placeholder="Masukkan pertanyaan"
                            class="w-full"
                            @update:model-value="clearError('pertanyaan')"
                        />
                    </UFormField>

                    <UFormField label="Jawaban" name="jawaban" required>
                        <UTextarea
                            v-model="form.jawaban"
                            placeholder="Masukkan jawaban"
                            class="w-full"
                            :rows="4"
                            @update:model-value="clearError('jawaban')"
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
