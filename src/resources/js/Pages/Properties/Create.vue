<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PropertyForm from '@/Pages/Properties/Partials/PropertyForm.vue';

defineProps({
    availableTags: {
        type: Array,
        default: () => [],
    },
    availableFacilities: {
        type: Array,
        default: () => [],
    },
    availablePropertyTypes: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <DashboardLayout>
        <Head title="Tambah Properti" />

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Properti</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi detail properti, pin lokasi di peta, lalu simpan.</p>
            </div>

            <PropertyForm
                mode="create"
                :available-tags="availableTags"
                :available-facilities="availableFacilities"
                :available-property-types="availablePropertyTypes"
            />
        </div>
    </DashboardLayout>
</template>
