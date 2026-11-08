<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    setting: Object,
});

const form = useForm({
    price_per_month: props.setting?.price_per_month ?? 0,
    bank_name: props.setting?.bank_name ?? '',
    account_number: props.setting?.account_number ?? '',
    account_holder_name: props.setting?.account_holder_name ?? '',
});

const handleSubmit = () => {
    form.put('/featured-listing/settings', {
        preserveScroll: true,
    });
};

const formatRupiah = (value) => {
    const number = Number(value || 0);
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(number);
};
</script>

<template>
    <DashboardLayout>
        <Head title="Pengaturan Iklan" />

        <div class="space-y-6 max-w-3xl">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pengaturan Iklan Eksklusif</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Atur harga per bulan dan informasi rekening yang ditampilkan ke pemilik properti saat mengajukan iklan.
                </p>
            </div>

            <UCard>
                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <UFormField label="Harga per bulan" name="price_per_month" required
                                :error="form.errors.price_per_month">
                        <UInput
                            v-model="form.price_per_month"
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="50000"
                            size="lg"
                            class="w-full"
                        >
                            <template #leading>
                                <span class="text-sm text-gray-500">Rp</span>
                            </template>
                        </UInput>
                        <template #hint>
                            <span class="text-xs text-gray-500">
                                Preview: {{ formatRupiah(form.price_per_month) }} / bulan
                            </span>
                        </template>
                    </UFormField>

                    <UFormField label="Nama Bank" name="bank_name" required :error="form.errors.bank_name">
                        <UInput
                            v-model="form.bank_name"
                            placeholder="Contoh: BCA"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField label="Nomor Rekening" name="account_number" required :error="form.errors.account_number">
                        <UInput
                            v-model="form.account_number"
                            placeholder="Contoh: 1234567890"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField label="Atas Nama" name="account_holder_name" required :error="form.errors.account_holder_name">
                        <UInput
                            v-model="form.account_holder_name"
                            placeholder="Nama pemilik rekening"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="flex justify-end">
                        <UButton
                            type="submit"
                            color="primary"
                            size="lg"
                            :loading="form.processing"
                        >
                            Simpan Pengaturan
                        </UButton>
                    </div>
                </form>
            </UCard>
        </div>
    </DashboardLayout>
</template>
