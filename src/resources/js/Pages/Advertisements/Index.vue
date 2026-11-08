<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    advertisements: Object,
    filters: Object,
    placements: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const placementFilter = ref(props.filters?.placement || null);
const statusFilter = ref(props.filters?.status || null);

let timer;
watch([search, placementFilter, statusFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/advertisements', {
            search: search.value,
            placement: placementFilter.value,
            status: statusFilter.value,
        }, { preserveState: true, replace: true });
    }, 250);
});

const currentPage = ref(props.advertisements.current_page);
watch(currentPage, (page) => {
    router.get('/advertisements', {
        search: search.value,
        placement: placementFilter.value,
        status: statusFilter.value,
        page,
    }, { preserveState: true });
});

const placementOptions = props.placements.map((p) => ({ label: labelPlacement(p), value: p }));
const statusOptions = props.statuses.map((s) => ({ label: labelStatus(s), value: s }));

function labelPlacement(value) {
    return ({
        header: 'Header',
        sidebar: 'Sidebar (Welcome/Discover)',
        interstitial: 'Interstitial',
        detail: 'Halaman Detail',
    })[value] || value;
}

function labelStatus(value) {
    return ({
        active: 'Aktif',
        inactive: 'Nonaktif',
        completed: 'Selesai',
    })[value] || value;
}

function statusColor(value) {
    if (value === 'active') return 'success';
    if (value === 'inactive') return 'neutral';
    return 'warning';
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ===== Modal =====
const modalOpen = ref(false);
const mode = ref('create');
const imagePreview = ref(null);

const form = useForm({
    id: null,
    title: '',
    advertiser_name: '',
    image: null,
    target_url: '',
    placement: 'sidebar',
    start_date: '',
    end_date: '',
    status: 'active',
});

const openCreate = () => {
    mode.value = 'create';
    form.reset();
    form.clearErrors();
    imagePreview.value = null;
    modalOpen.value = true;
};

const openEdit = (ad) => {
    mode.value = 'edit';
    form.reset();
    form.clearErrors();
    form.id = ad.id;
    form.title = ad.title;
    form.advertiser_name = ad.advertiser_name;
    form.target_url = ad.target_url;
    form.placement = ad.placement;
    form.start_date = ad.start_date?.substring(0, 10) || '';
    form.end_date = ad.end_date?.substring(0, 10) || '';
    form.status = ad.status;
    form.image = null;
    imagePreview.value = ad.image_url ? `/storage/${ad.image_url}` : null;
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    form.reset();
    form.clearErrors();
    imagePreview.value = null;
};

const onImageChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        form.image = null;
        return;
    }
    form.image = file;
    const reader = new FileReader();
    reader.onload = (e) => { imagePreview.value = e.target.result; };
    reader.readAsDataURL(file);
};

const submit = () => {
    if (mode.value === 'create') {
        form.post('/advertisements', {
            forceFormData: true,
            onSuccess: () => closeModal(),
        });
    } else {
        // Laravel doesn't accept multipart on PUT — use POST with method spoofing on backend.
        form.post(`/advertisements/${form.id}`, {
            forceFormData: true,
            onSuccess: () => closeModal(),
        });
    }
};

const handleDelete = (ad) => {
    if (!confirm(`Hapus iklan "${ad.title}"?`)) return;
    router.delete(`/advertisements/${ad.id}`, { preserveScroll: true });
};
</script>

<template>
    <DashboardLayout>
        <Head title="Iklan" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manajemen Iklan</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Atur papan iklan yang tampil di halaman depan, jelajahi, dan halaman lainnya.
                    </p>
                </div>
                <UButton icon="i-heroicons-plus" size="lg" color="primary" @click="openCreate">
                    Tambah Iklan
                </UButton>
            </div>

            <UCard>
                <div class="flex flex-col sm:flex-row gap-3">
                    <UInput
                        v-model="search"
                        icon="i-heroicons-magnifying-glass"
                        placeholder="Cari judul atau pengiklan..."
                        size="lg"
                        class="flex-1"
                    />
                    <USelect
                        v-model="placementFilter"
                        :items="[{ label: 'Semua Penempatan', value: null }, ...placementOptions]"
                        placeholder="Penempatan"
                        size="lg"
                        class="w-56"
                    />
                    <USelect
                        v-model="statusFilter"
                        :items="[{ label: 'Semua Status', value: null }, ...statusOptions]"
                        placeholder="Status"
                        size="lg"
                        class="w-44"
                    />
                </div>
            </UCard>

            <div v-if="advertisements.data && advertisements.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <UCard
                    v-for="ad in advertisements.data"
                    :key="ad.id"
                    class="overflow-hidden"
                    :ui="{ body: { padding: 'p-0 sm:p-0' } }"
                >
                    <div class="relative h-44 bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <img v-if="ad.image_url" :src="`/storage/${ad.image_url}`" class="absolute inset-0 w-full h-full object-cover" :alt="ad.title" />
                        <div v-else class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">
                            Tidak ada gambar
                        </div>
                        <div class="absolute top-2 left-2 flex gap-1">
                            <UBadge color="primary" variant="solid" class="uppercase">{{ labelPlacement(ad.placement) }}</UBadge>
                            <UBadge :color="statusColor(ad.status)" variant="solid" class="uppercase">{{ labelStatus(ad.status) }}</UBadge>
                        </div>
                        <div class="absolute top-2 right-2 flex items-center gap-1 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-md p-1 shadow-sm">
                            <UButton icon="i-heroicons-pencil-square" size="xs" color="warning" variant="ghost" square @click="openEdit(ad)" />
                            <UButton icon="i-heroicons-trash" size="xs" color="error" variant="ghost" square @click="handleDelete(ad)" />
                        </div>
                    </div>
                    <div class="p-4 space-y-2">
                        <h3 class="font-bold text-gray-900 dark:text-white truncate" :title="ad.title">{{ ad.title }}</h3>
                        <p class="text-xs text-gray-500 truncate">Pengiklan: <span class="font-medium text-gray-700 dark:text-gray-300">{{ ad.advertiser_name }}</span></p>
                        <p class="text-xs text-gray-500 truncate">
                            <UIcon name="i-heroicons-link" class="w-3 h-3 inline" />
                            <a :href="ad.target_url" target="_blank" class="text-primary-600 hover:underline ml-1">{{ ad.target_url }}</a>
                        </p>
                        <div class="text-xs text-gray-500 pt-1 border-t border-gray-100 dark:border-gray-800">
                            Berlaku: {{ formatDate(ad.start_date) }} - {{ formatDate(ad.end_date) }}
                        </div>
                    </div>
                </UCard>
            </div>

            <UCard v-else class="text-center py-12">
                <div class="i-heroicons-megaphone text-4xl text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum ada iklan</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Tambahkan papan iklan pertama untuk ditampilkan di situs.</p>
                <UButton class="mt-4" color="primary" @click="openCreate">Tambah Iklan</UButton>
            </UCard>

            <UCard v-if="advertisements.data && advertisements.data.length > 0">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ advertisements.from || 0 }} - {{ advertisements.to || 0 }} dari {{ advertisements.total }} data
                    </span>
                    <UPagination
                        v-if="advertisements.total > advertisements.per_page"
                        v-model:page="currentPage"
                        :total="advertisements.total"
                        :items-per-page="advertisements.per_page"
                    />
                </div>
            </UCard>
        </div>

        <UModal
            v-model:open="modalOpen"
            :title="mode === 'create' ? 'Tambah Iklan' : 'Edit Iklan'"
            prevent-close
            :dismissible="false"
        >
            <template #body>
                <form @submit.prevent="submit" class="space-y-4">
                    <UFormField label="Judul Kampanye" name="title" required :error="form.errors.title">
                        <UInput v-model="form.title" placeholder="Contoh: Promo Akhir Tahun" class="w-full" />
                    </UFormField>

                    <UFormField label="Nama Pengiklan" name="advertiser_name" required :error="form.errors.advertiser_name">
                        <UInput v-model="form.advertiser_name" placeholder="Nama brand/perusahaan" class="w-full" />
                    </UFormField>

                    <UFormField label="Foto Iklan" name="image" :required="mode === 'create'" :error="form.errors.image">
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:bg-primary-600 file:text-white file:text-xs file:cursor-pointer"
                            @change="onImageChange"
                        />
                        <template #hint>
                            <span class="text-xs text-gray-500">Format: JPG/PNG, maks 5MB. Rasio direkomendasikan 1:1 atau 4:3 untuk slot sidebar.</span>
                        </template>
                        <div v-if="imagePreview" class="mt-2">
                            <img :src="imagePreview" class="max-h-40 rounded-lg border border-gray-200" alt="Preview" />
                        </div>
                    </UFormField>

                    <UFormField label="URL Tujuan" name="target_url" required :error="form.errors.target_url">
                        <UInput v-model="form.target_url" type="url" placeholder="https://..." class="w-full" />
                    </UFormField>

                    <UFormField label="Penempatan" name="placement" required :error="form.errors.placement">
                        <USelect v-model="form.placement" :items="placementOptions" class="w-full" />
                    </UFormField>

                    <div class="grid grid-cols-2 gap-3">
                        <UFormField label="Tanggal Mulai" name="start_date" required :error="form.errors.start_date">
                            <UInput v-model="form.start_date" type="date" class="w-full" />
                        </UFormField>
                        <UFormField label="Tanggal Berakhir" name="end_date" required :error="form.errors.end_date">
                            <UInput v-model="form.end_date" type="date" class="w-full" />
                        </UFormField>
                    </div>

                    <UFormField label="Status" name="status" required :error="form.errors.status">
                        <USelect v-model="form.status" :items="statusOptions" class="w-full" />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <UButton color="neutral" variant="soft" @click="closeModal">Batal</UButton>
                    <UButton color="primary" :loading="form.processing" @click="submit">
                        {{ mode === 'create' ? 'Simpan' : 'Simpan Perubahan' }}
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
