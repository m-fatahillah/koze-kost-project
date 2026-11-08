<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { z } from 'zod';

const props = defineProps({
    users: Object,
    filters: Object,
});

// Filters
const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || null);

let timer;
watch([search, roleFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/users', {
            search: search.value,
            role: roleFilter.value,
        }, { preserveState: true, replace: true });
    }, 300);
});

// Pagination
const currentPage = ref(props.users.current_page);
watch(currentPage, (page) => {
    router.get('/users', {
        search: search.value,
        role: roleFilter.value,
        page,
    }, { preserveState: true });
});

// Table columns
const columns = [
    { id: 'name', header: 'Nama', accessorKey: 'name' },
    { id: 'email', header: 'Email', accessorKey: 'email' },
    { id: 'role', header: 'Peran', accessorKey: 'role' },
    { id: 'actions', header: 'Aksi' },
];

const roleFilterOptions = ['Admin', 'Seeker', 'Owner'];
const roleOptions = [
    { label: 'Admin', value: 'Admin' },
    { label: 'Pencari Kos (Seeker)', value: 'Seeker' },
    { label: 'Pemilik Kos (Owner)', value: 'Owner' },
];

// Modal
const isModalOpen = ref(false);
const modalMode = ref('create');
const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'Seeker',
});

// Validation Schemas
const createSchema = z.object({
    name: z.string().min(1, 'Nama lengkap wajib diisi'),
    email: z.email('Format email tidak valid'),
    password: z.string().min(8, 'Kata sandi minimal 8 karakter'),
    password_confirmation: z.string(),
    role: z.enum(['Admin', 'Seeker', 'Owner'], { required_error: 'Pilih opsi ini' }),
}).refine(data => data.password === data.password_confirmation, {
    message: 'Konfirmasi kata sandi tidak cocok',
    path: ['password_confirmation']
});

const editSchema = z.object({
    name: z.string().min(1, 'Nama lengkap wajib diisi'),
    email: z.email('Format email tidak valid'),
    password: z.union([z.string().min(8, 'Kata sandi minimal 8 karakter'), z.string().length(0)]).optional(),
    password_confirmation: z.string().optional(),
    role: z.enum(['Admin', 'Seeker', 'Owner'], { required_error: 'Pilih opsi ini' }),
}).refine(data => {
    if (data.password && data.password.length > 0) {
        return data.password === data.password_confirmation;
    }
    return true;
}, {
    message: 'Konfirmasi kata sandi tidak cocok',
    path: ['password_confirmation']
});

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
    form.role = 'Seeker';
    showPassword.value = false;
    showPasswordConfirm.value = false;
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    modalMode.value = 'edit';
    form.reset();
    form.clearErrors();
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
    showPassword.value = false;
    showPasswordConfirm.value = false;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    if (modalMode.value === 'create') {
        form.post('/users', {
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(`/users/${form.id}`, {
            onSuccess: () => closeModal(),
        });
    }
};
</script>

<template>
    <DashboardLayout>
        <Head title="Manajemen Pengguna" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manajemen Pengguna</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data pengguna aplikasi</p>
                </div>
                <UButton icon="i-heroicons-plus" size="lg" color="primary" @click="openCreateModal">
                    Tambah Pengguna
                </UButton>
            </div>

            <!-- Filters -->
            <UCard>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <UInput
                            v-model="search"
                            icon="i-heroicons-magnifying-glass"
                            placeholder="Cari nama atau email..."
                            size="lg"
                        />
                    </div>
                    <div class="flex items-center gap-1">
                        <USelect
                            v-model="roleFilter"
                            :items="roleFilterOptions"
                            placeholder="Semua Peran"
                            size="lg"
                            class="w-40"
                        />
                        <UButton
                            v-if="roleFilter"
                            icon="i-heroicons-x-mark"
                            size="lg"
                            color="neutral"
                            variant="ghost"
                            square
                            @click="roleFilter = null"
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
                    :data="users.data"
                >
                    <template #empty>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Tidak ada data
                        </div>
                    </template>
                    <template #role-cell="{ row }">
                        <UBadge
                            :color="row.original.role === 'Admin' ? 'error' : row.original.role === 'Owner' ? 'primary' : 'neutral'"
                            variant="subtle"
                            size="sm"
                        >
                            {{ row.original.role }}
                        </UBadge>
                    </template>

                    <template #actions-cell="{ row }">
                        <UButton
                            icon="i-heroicons-pencil-square"
                            size="xs"
                            color="warning"
                            variant="ghost"
                            square
                            @click="openEditModal(row.original)"
                        />
                    </template>
                </UTable>

                <template #footer>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Menampilkan {{ users.from }} - {{ users.to }} dari {{ users.total }} pengguna
                        </span>
                        <UPagination
                            v-model:page="currentPage"
                            :total="users.total"
                            :items-per-page="25"
                        />
                    </div>
                </template>
            </UCard>
        </div>

        <!-- Create / Edit Modal -->
        <UModal
            v-model:open="isModalOpen"
            :title="modalMode === 'create' ? 'Tambah Pengguna Baru' : 'Edit Pengguna'"
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <UFormField label="Nama Lengkap" name="name" required>
                            <UInput v-model="form.name" placeholder="Masukkan nama" class="w-full" @update:model-value="clearError('name')" />
                        </UFormField>

                        <UFormField label="Email" name="email" required>
                            <UInput v-model="form.email" type="email" placeholder="email@contoh.com" class="w-full" @update:model-value="clearError('email')" />
                        </UFormField>

                        <UFormField
                            :label="modalMode === 'edit' ? 'Kata Sandi (Kosongkan jika tidak diubah)' : 'Kata Sandi'"
                            name="password"
                            :required="modalMode === 'create'"
                        >
                            <UInput
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
                                class="w-full"
                                @update:model-value="clearError('password')"
                            >
                                <template #trailing>
                                    <UButton
                                        color="neutral"
                                        variant="link"
                                        :icon="showPassword ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                        :padded="false"
                                        @click="showPassword = !showPassword"
                                    />
                                </template>
                            </UInput>
                        </UFormField>

                        <UFormField
                            label="Konfirmasi Kata Sandi"
                            name="password_confirmation"
                            v-if="modalMode === 'create' || form.password"
                            :required="modalMode === 'create'"
                        >
                            <UInput
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                placeholder="••••••••"
                                class="w-full"
                                @update:model-value="clearError('password_confirmation')"
                            >
                                <template #trailing>
                                    <UButton
                                        color="neutral"
                                        variant="link"
                                        :icon="showPasswordConfirm ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                        :padded="false"
                                        @click="showPasswordConfirm = !showPasswordConfirm"
                                    />
                                </template>
                            </UInput>
                        </UFormField>

                        <UFormField label="Peran" name="role" required>
                            <USelect v-model="form.role" :items="roleOptions" class="w-full" @update:model-value="clearError('role')" />
                        </UFormField>
                    </div>
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
