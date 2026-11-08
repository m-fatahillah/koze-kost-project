<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PropertyForm from '@/Pages/Properties/Partials/PropertyForm.vue';

defineProps({
    property: {
        type: Object,
        required: true,
    },
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
        <Head title="Edit Properti" />

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Properti</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui data properti, foto, dan titik lokasi pada peta.</p>
            </div>

            <PropertyForm
                mode="edit"
                :property="property"
                :available-tags="availableTags"
                :available-facilities="availableFacilities"
                :available-property-types="availablePropertyTypes"
            />
        </div>
    </DashboardLayout>
</template>
