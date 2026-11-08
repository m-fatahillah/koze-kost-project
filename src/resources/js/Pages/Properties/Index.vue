<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const page = usePage();
const isOwner = computed(() => page.props.auth.user?.roles?.includes('Owner'));

const props = defineProps({
    properties: Object,
    filters: Object,
    availablePropertyTypes: {
        type: Array,
        default: () => [],
    },
});

// ===== Featured listing application =====
const applyModalOpen = ref(false);
const applyContext = ref(null); // { property, setting }
const isLoadingContext = ref(false);

const applyForm = useForm({
    property_id: null,
    months: 1,
    payment_proof: null,
});

const openApplyModal = async (property) => {
    isLoadingContext.value = true;
    applyContext.value = null;
    applyForm.reset();
    applyForm.clearErrors();
    applyForm.property_id = property.id;
    applyForm.months = 1;
    applyModalOpen.value = true;

    try {
        const res = await fetch(`/featured-listing/create?property_id=${property.id}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Gagal memuat data');
        applyContext.value = await res.json();
    } catch (err) {
        alert(err.message || 'Gagal memuat data pengajuan.');
        applyModalOpen.value = false;
    } finally {
        isLoadingContext.value = false;
    }
};

const onProofChange = (event) => {
    const file = event.target.files?.[0];
    applyForm.payment_proof = file || null;
};

const totalAmount = computed(() => {
    const price = Number(applyContext.value?.setting?.price_per_month || 0);
    const months = Number(applyForm.months || 0);
    return price * months;
});

const settingReady = computed(() => {
    return Number(applyContext.value?.setting?.price_per_month || 0) > 0
        && applyContext.value?.setting?.bank_name;
});

const submitApply = () => {
    applyForm.post('/featured-listing', {
        forceFormData: true,
        onSuccess: () => {
            applyModalOpen.value = false;
            applyContext.value = null;
        },
    });
};

const search = ref(props.filters?.search || '');
const typeFilter = ref(props.filters?.type || null);

let timer;
watch([search, typeFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/properties', {
            search: search.value,
            type: typeFilter.value,
        }, { preserveState: true, replace: true });
    }, 300);
});

const currentPage = ref(props.properties.current_page);
watch(currentPage, (pageNumber) => {
    router.get('/properties', {
        search: search.value,
        type: typeFilter.value,
        page: pageNumber,
    }, { preserveState: true });
});

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(number);
};

const typeFilterItems = computed(() => {
    return props.availablePropertyTypes.map(t => ({
        label: t.name,
        value: t.id,
    }));
});
const labelGender = (value) => {
    if (value === 'putra') return 'Putra';
    if (value === 'putri') return 'Putri';
    return 'Campur';
};
const labelPriceType = (value) => {
    if (value === 'daily') return '/ hari';
    if (value === 'yearly') return '/ tahun';
    return '/ bulan';
};

const scrollNext = (id) => {
    const el = document.getElementById(id);
    if (el) el.scrollBy({ left: el.clientWidth, behavior: 'smooth' });
};

const scrollPrev = (id) => {
    const el = document.getElementById(id);
    if (el) el.scrollBy({ left: -el.clientWidth, behavior: 'smooth' });
};

const openCreatePage = () => {
    router.get('/properties/create');
};

const openEditPage = (id) => {
    router.get(`/properties/${id}/edit`);
};

const handleDelete = (id) => {
    if (confirm('Yakin ingin menghapus properti ini beserta semua fotonya?')) {
        router.delete(`/properties/${id}`, { preserveScroll: true });
    }
};

const toggleStatus = (item) => {
    const nextStatus = item.status === 'active' ? 'inactive' : 'active';
    router.patch(`/properties/${item.id}/status`, {
        status: nextStatus,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <DashboardLayout>
        <Head title="Properti" />

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Properti</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data kos dan kontrakan Anda dengan alur kerja yang lebih rapi.</p>
                    </div>
                    <UButton v-if="isOwner" icon="i-heroicons-plus" size="lg" color="primary" @click="openCreatePage">
                        Tambah Properti
                    </UButton>
                </div>
            </div>

            <UCard>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <UInput
                        v-model="search"
                        icon="i-heroicons-magnifying-glass"
                        placeholder="Cari nama properti..."
                        size="lg"
                        class="flex-1"
                    />
                    <div class="flex items-center gap-1">
                        <USelect
                            v-model="typeFilter"
                            :items="typeFilterItems"
                            placeholder="Semua Tipe"
                            size="lg"
                            class="w-44"
                        />
                        <UButton
                            v-if="typeFilter"
                            icon="i-heroicons-x-mark"
                            size="lg"
                            color="neutral"
                            variant="ghost"
                            square
                            @click="typeFilter = null"
                        />
                    </div>
                </div>
            </UCard>

            <div v-if="properties.data && properties.data.length > 0" class="flex flex-col gap-6">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <UCard
                        v-for="item in properties.data"
                        :key="item.id"
                        class="overflow-hidden"
                        :ui="{ body: { padding: 'p-0 sm:p-0' } }"
                    >
                        <div class="relative h-52 w-full group overflow-hidden">
                            <div :id="`carousel-${item.id}`" class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth w-full h-full [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                <template v-if="item.images && item.images.length > 0">
                                    <div v-for="(img, idx) in item.images" :key="img.id" class="flex-none w-full h-full snap-center relative">
                                        <img :src="`/storage/${img.image_url}`" class="w-full h-full object-cover" :alt="`${item.name} - Foto ${idx + 1}`" />
                                        <div v-if="item.images.length > 1" class="absolute bottom-2 left-1/2 -translate-x-1/2 bg-black/50 text-white text-[10px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm pointer-events-none">
                                            {{ idx + 1 }} / {{ item.images.length }}
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="w-full h-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center flex-none snap-center">
                                    <span class="text-gray-400 text-sm">Tidak ada foto</span>
                                </div>
                            </div>

                            <template v-if="item.images && item.images.length > 1">
                                <UButton
                                    icon="i-heroicons-chevron-left"
                                    color="white"
                                    size="xs"
                                    class="absolute left-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity z-10 !rounded-full shadow-md"
                                    square
                                    @click.stop="scrollPrev(`carousel-${item.id}`)"
                                />
                                <UButton
                                    icon="i-heroicons-chevron-right"
                                    color="white"
                                    size="xs"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity z-10 !rounded-full shadow-md"
                                    square
                                    @click.stop="scrollNext(`carousel-${item.id}`)"
                                />
                            </template>

                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                <UBadge color="primary" variant="solid" class="uppercase shadow-sm">
                                    {{ item.property_type?.name || '-' }}
                                </UBadge>
                                <UBadge :color="item.status === 'active' ? 'success' : 'neutral'" variant="solid" class="uppercase shadow-sm">
                                    {{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </UBadge>
                            </div>

                            <div v-if="isOwner" class="absolute top-2 right-2 flex items-center gap-1 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-md p-1 shadow-sm">
                                <UButton icon="i-heroicons-pencil-square" size="xs" color="warning" variant="ghost" square @click="openEditPage(item.id)" />
                                <UButton icon="i-heroicons-trash" size="xs" color="error" variant="ghost" square @click="handleDelete(item.id)" />
                            </div>
                        </div>

                        <div class="p-4 space-y-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white line-clamp-1" :title="item.name">{{ item.name }}</h3>
                                <p class="text-primary-600 dark:text-primary-400 font-bold mt-1">
                                    {{ formatRupiah(item.price) }} <span class="text-xs font-normal text-gray-500">{{ labelPriceType(item.price_type) }}</span>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-2">
                                    <span class="block text-gray-500">Jenis Kelamin</span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ labelGender(item.gender_restriction) }}</span>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-2">
                                    <span class="block text-gray-500">Kamar</span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ item.available_rooms }}/{{ item.total_rooms }} tersedia</span>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 leading-snug">
                                {{ item.description }}
                            </p>

                            <div class="flex flex-wrap gap-1">
                                <template v-if="item.tags && item.tags.length > 0">
                                    <UBadge v-for="tag in item.tags.slice(0, 3)" :key="tag.id" color="neutral" variant="subtle" size="xs">
                                        {{ tag.name }}
                                    </UBadge>
                                    <UBadge v-if="item.tags.length > 3" color="neutral" variant="subtle" size="xs">
                                        +{{ item.tags.length - 3 }}
                                    </UBadge>
                                </template>
                                <span v-else class="text-xs text-gray-400">Belum ada tag</span>
                            </div>

                            <div v-if="isOwner" class="pt-2 space-y-2">
                                <UButton
                                    size="sm"
                                    :color="item.status === 'active' ? 'warning' : 'success'"
                                    variant="soft"
                                    class="w-full justify-center"
                                    @click="toggleStatus(item)"
                                >
                                    {{ item.status === 'active' ? 'Nonaktifkan Properti' : 'Aktifkan Properti' }}
                                </UButton>
                                <UButton
                                    size="sm"
                                    color="primary"
                                    variant="solid"
                                    icon="i-lucide-star"
                                    class="w-full justify-center"
                                    @click="openApplyModal(item)"
                                >
                                    {{ item.is_featured ? 'Perpanjang Iklan' : 'Ajukan Iklan Premium' }}
                                </UButton>
                                <p v-if="item.is_featured && item.featured_until" class="text-[11px] text-center text-gray-500">
                                    Aktif sampai {{ new Date(item.featured_until).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                </p>
                            </div>
                        </div>
                    </UCard>
                </div>

                <UCard>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Menampilkan {{ properties.from || 0 }} - {{ properties.to || 0 }} dari {{ properties.total }} data
                        </span>
                        <UPagination
                            v-if="properties.total > properties.per_page"
                            v-model:page="currentPage"
                            :total="properties.total"
                            :items-per-page="properties.per_page"
                        />
                    </div>
                </UCard>
            </div>

            <UCard v-else class="text-center py-12">
                <div class="i-heroicons-home text-4xl text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum ada properti</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Mulai tambahkan daftar kos atau kontrakan Anda sekarang.</p>
                <UButton v-if="isOwner" class="mt-4" color="primary" @click="openCreatePage">Tambah Properti</UButton>
            </UCard>
        </div>

        <UModal v-model:open="applyModalOpen" title="Ajukan Iklan Premium" prevent-close :dismissible="false">
            <template #body>
                <div v-if="isLoadingContext" class="py-8 text-center text-sm text-gray-500">
                    Memuat data...
                </div>
                <div v-else-if="!settingReady" class="rounded-lg border border-warning-200 bg-warning-50 dark:bg-warning-950 dark:border-warning-800 p-4 text-sm">
                    Admin belum mengatur harga dan rekening untuk iklan premium. Silakan hubungi admin terlebih dahulu.
                </div>
                <form v-else @submit.prevent="submitApply" class="space-y-4">
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-sm">
                        <div class="text-xs text-gray-500">Properti</div>
                        <div class="font-semibold">{{ applyContext.property.name }}</div>
                        <div v-if="applyContext.property.is_featured && applyContext.property.featured_until" class="text-xs text-success-600 mt-1">
                            Saat ini aktif sampai {{ new Date(applyContext.property.featured_until).toLocaleString('id-ID') }}. Durasi baru akan ditambahkan dari tanggal tersebut.
                        </div>
                    </div>

                    <UFormField label="Durasi (bulan)" name="months" required :error="applyForm.errors.months">
                        <UInput
                            v-model.number="applyForm.months"
                            type="number"
                            min="1"
                            max="24"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                            <div class="text-xs text-gray-500">Harga per bulan</div>
                            <div class="font-semibold">{{ formatRupiah(applyContext.setting.price_per_month) }}</div>
                        </div>
                        <div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-3">
                            <div class="text-xs text-gray-500">Total bayar</div>
                            <div class="font-bold text-primary-700 dark:text-primary-300">{{ formatRupiah(totalAmount) }}</div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm space-y-1">
                        <div class="text-xs text-gray-500 font-semibold uppercase">Transfer ke</div>
                        <div><span class="text-gray-500">Bank:</span> <span class="font-semibold">{{ applyContext.setting.bank_name }}</span></div>
                        <div><span class="text-gray-500">No. Rek:</span> <span class="font-mono font-semibold">{{ applyContext.setting.account_number }}</span></div>
                        <div><span class="text-gray-500">a.n.:</span> <span class="font-semibold">{{ applyContext.setting.account_holder_name }}</span></div>
                    </div>

                    <UFormField label="Upload Bukti Pembayaran" name="payment_proof" required :error="applyForm.errors.payment_proof">
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:bg-primary-600 file:text-white file:text-xs file:cursor-pointer"
                            @change="onProofChange"
                        />
                        <template #hint>
                            <span class="text-xs text-gray-500">Format: JPG/PNG, maks 5MB.</span>
                        </template>
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <UButton color="neutral" variant="soft" @click="applyModalOpen = false">Batal</UButton>
                    <UButton
                        color="primary"
                        :disabled="!settingReady || !applyForm.payment_proof"
                        :loading="applyForm.processing"
                        @click="submitApply"
                    >
                        Kirim Pengajuan
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
