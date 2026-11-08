<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification');
};
</script>

<template>
    <Head title="Verifikasi Email" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <div class="flex flex-col items-center">
                    <UIcon name="i-heroicons-envelope-open" class="w-12 h-12 text-primary mb-4" />
                    <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">
                        Verifikasi Email Anda
                    </h2>
                </div>
            </template>

            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 leading-relaxed text-center">
                Terima kasih telah mendaftar! Sebelum memulai, harap verifikasi pos-el Anda dengan mengeklik tautan yang baru saja kami kirimkan ke pos-el Anda. Jika Anda tidak menerima pos-el tersebut, kami akan dengan senang hati mengirimkan tautan yang lain.
            </div>

            <div v-if="$page.props.flash?.success" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 p-3 rounded-md">
                {{ $page.props.flash.success }}
            </div>

            <UForm :state="form" @submit="submit" class="mt-6 flex items-center justify-between">
                <UButton
                    type="submit"
                    color="primary"
                    :loading="form.processing"
                >
                    Kirim Ulang Email Verifikasi
                </UButton>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:underline focus:outline-none"
                >
                    Log Out
                </Link>
            </UForm>
        </UCard>
    </div>
</template>
