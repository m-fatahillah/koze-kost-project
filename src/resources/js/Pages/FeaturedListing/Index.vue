<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    payments: Object,
    filters: Object,
    isAdmin: Boolean,
    setting: Object,
});

const statusFilter = ref(props.filters?.status || null);

let timer;
watch([statusFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/featured-listing', {
            status: statusFilter.value,
        }, { preserveState: true, replace: true });
    }, 200);
});

const currentPage = ref(props.payments.current_page);
watch(currentPage, (page) => {
    router.get('/featured-listing', {
        status: statusFilter.value,
        page,
    }, { preserveState: true });
});

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(number || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusColor = (status) => {
    if (status === 'verified') return 'success';
    if (status === 'rejected') return 'error';
    return 'warning';
};

const statusLabel = (status) => {
    if (status === 'verified') return 'Terverifikasi';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu Verifikasi';
};

const statusOptions = [
    { label: 'Semua status', value: null },
    { label: 'Menunggu', value: 'pending' },
    { label: 'Terverifikasi', value: 'verified' },
    { label: 'Ditolak', value: 'rejected' },
];

// ====== Verify ======
const verifyForm = useForm({});
const verifyPayment = (id) => {
    if (!confirm('Verifikasi pembayaran ini? Iklan akan langsung aktif.')) return;
    verifyForm.post(`/featured-listing/payments/${id}/verify`, {
        preserveScroll: true,
    });
};

// ====== Reject ======
const rejectModalOpen = ref(false);
const selectedPayment = ref(null);
const rejectForm = useForm({
    rejection_reason: '',
});

const openRejectModal = (payment) => {
    selectedPayment.value = payment;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectModalOpen.value = true;
};

const submitReject = () => {
    if (!selectedPayment.value) return;
    rejectForm.post(`/featured-listing/payments/${selectedPayment.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectModalOpen.value = false;
            selectedPayment.value = null;
        },
    });
};

// ====== Proof preview ======
const proofModalOpen = ref(false);
const previewUrl = ref(null);
const openProof = (path) => {
    previewUrl.value = `/storage/${path}`;
    proofModalOpen.value = true;
};
</script>

<template>
    <DashboardLayout>
        <Head title="Iklan Eksklusif" />

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ isAdmin ? 'Verifikasi Iklan Eksklusif' : 'Iklan Eksklusif Saya' }}
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ isAdmin
                                ? 'Verifikasi pembayaran iklan eksklusif dari pemilik properti.'
                                : 'Riwayat pengajuan iklan eksklusif dan status pembayaran Anda.' }}
                        </p>
                    </div>
                    <div v-if="!isAdmin && setting?.price_per_month" class="rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950 px-4 py-3 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Tarif saat ini:</span>
                        <span class="font-bold text-primary-700 dark:text-primary-300 ml-1">
                            {{ formatRupiah(setting.price_per_month) }} / bulan
                        </span>
                    </div>
                </div>
            </div>

            <UCard>
                <div class="flex items-center gap-3">
                    <USelect
                        v-model="statusFilter"
                        :items="statusOptions"
                        placeholder="Filter status"
                        size="lg"
                        class="w-56"
                    />
                </div>
            </UCard>

            <div v-if="payments.data && payments.data.length > 0" class="space-y-4">
                <UCard v-for="item in payments.data" :key="item.id">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-bold text-gray-900 dark:text-white">
                                    {{ item.property?.name || 'Properti dihapus' }}
                                </h3>
                                <UBadge :color="statusColor(item.status)" variant="subtle">
                                    {{ statusLabel(item.status) }}
                                </UBadge>
                            </div>

                            <div v-if="isAdmin" class="text-xs text-gray-500">
                                Diajukan oleh: <span class="font-medium text-gray-700 dark:text-gray-300">{{ item.owner?.name }}</span>
                                ({{ item.owner?.email }})
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-2.5">
                                    <span class="block text-xs text-gray-500">Durasi</span>
                                    <span class="font-semibold">{{ item.months }} bulan</span>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-2.5">
                                    <span class="block text-xs text-gray-500">Harga/bulan</span>
                                    <span class="font-semibold">{{ formatRupiah(item.price_per_month) }}</span>
                                </div>
                                <div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-2.5">
                                    <span class="block text-xs text-gray-500">Total</span>
                                    <span class="font-bold text-primary-700 dark:text-primary-300">{{ formatRupiah(item.total_amount) }}</span>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-2.5">
                                    <span class="block text-xs text-gray-500">Diajukan</span>
                                    <span class="font-semibold">{{ formatDate(item.created_at) }}</span>
                                </div>
                            </div>

                            <div v-if="item.status === 'verified'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-2.5">
                                    <span class="block text-xs text-gray-500">Mulai aktif</span>
                                    <span class="font-semibold">{{ formatDate(item.starts_at) }}</span>
                                </div>
                                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-2.5">
                                    <span class="block text-xs text-gray-500">Berakhir</span>
                                    <span class="font-semibold">{{ formatDate(item.expires_at) }}</span>
                                </div>
                            </div>

                            <div v-if="item.status === 'rejected' && item.rejection_reason" class="rounded-lg border border-error-200 bg-error-50 dark:bg-error-950 dark:border-error-800 p-3 text-sm">
                                <span class="block text-xs font-semibold text-error-700 dark:text-error-300 mb-1">Alasan Penolakan</span>
                                <p class="text-error-800 dark:text-error-200">{{ item.rejection_reason }}</p>
                            </div>

                            <div class="text-xs text-gray-500">
                                Transfer ke: <span class="font-medium text-gray-700 dark:text-gray-300">{{ item.bank_name }}</span>
                                — {{ item.account_number }} a.n. {{ item.account_holder_name }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 lg:w-48">
                            <UButton
                                icon="i-lucide-image"
                                color="neutral"
                                variant="soft"
                                size="sm"
                                block
                                @click="openProof(item.payment_proof)"
                            >
                                Lihat Bukti
                            </UButton>
                            <template v-if="isAdmin && item.status === 'pending'">
                                <UButton
                                    icon="i-lucide-check-circle"
                                    color="success"
                                    size="sm"
                                    block
                                    @click="verifyPayment(item.id)"
                                >
                                    Verifikasi
                                </UButton>
                                <UButton
                                    icon="i-lucide-x-circle"
                                    color="error"
                                    variant="soft"
                                    size="sm"
                                    block
                                    @click="openRejectModal(item)"
                                >
                                    Tolak
                                </UButton>
                            </template>
                        </div>
                    </div>
                </UCard>

                <UCard>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Menampilkan {{ payments.from || 0 }} - {{ payments.to || 0 }} dari {{ payments.total }} data
                        </span>
                        <UPagination
                            v-if="payments.total > payments.per_page"
                            v-model:page="currentPage"
                            :total="payments.total"
                            :items-per-page="payments.per_page"
                        />
                    </div>
                </UCard>
            </div>

            <UCard v-else class="text-center py-12">
                <div class="i-lucide-receipt text-4xl text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum ada pengajuan</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    {{ isAdmin
                        ? 'Belum ada owner yang mengajukan iklan premium.'
                        : 'Ajukan iklan premium melalui halaman properti Anda.' }}
                </p>
            </UCard>
        </div>

        <UModal v-model:open="proofModalOpen" title="Bukti Pembayaran">
            <template #body>
                <div class="flex justify-center">
                    <img v-if="previewUrl" :src="previewUrl" alt="Bukti pembayaran" class="max-h-[70vh] rounded-lg" />
                </div>
            </template>
        </UModal>

        <UModal v-model:open="rejectModalOpen" title="Tolak Pengajuan" prevent-close :dismissible="false">
            <template #body>
                <form @submit.prevent="submitReject" class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Berikan alasan penolakan agar pemilik dapat memperbaiki pengajuan.
                    </p>
                    <UFormField label="Alasan Penolakan" name="rejection_reason" required
                                :error="rejectForm.errors.rejection_reason">
                        <UTextarea
                            v-model="rejectForm.rejection_reason"
                            placeholder="Contoh: Bukti pembayaran tidak jelas / nominal tidak sesuai."
                            :rows="4"
                            class="w-full"
                        />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <UButton color="neutral" variant="soft" @click="rejectModalOpen = false">Batal</UButton>
                    <UButton color="error" :loading="rejectForm.processing" @click="submitReject">
                        Tolak Pengajuan
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
