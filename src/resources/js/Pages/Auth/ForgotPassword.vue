<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { z } from 'zod';

const form = useForm({
    email: '',
});

const schema = z.object({
    email: z.string().min(1, 'Email wajib diisi').email('Format email tidak valid'),
});

const validate = (state) => {
    const errors = [];
    Object.entries(form.errors).forEach(([key, message]) => {
        errors.push({ name: key, message });
    });
    return errors;
};

const submit = () => {
    form.post('/forgot-password');
};

const onInput = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};
</script>

<template>
    <Head title="Lupa Password" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">
                    Lupa Password?
                </h2>
                <p class="text-center text-gray-500 dark:text-gray-400 mt-2 text-sm leading-relaxed">
                    Tidak masalah. Masukkan pos-el Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.
                </p>
            </template>

            <div v-if="$page.props.flash?.success" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 p-3 rounded-md">
                {{ $page.props.flash.success }}
            </div>

            <UForm :state="form" :schema="schema" :validate="validate" @submit="submit" class="space-y-4">
                <UFormField label="Pos-el" name="email">
                    <UInput
                        v-model="form.email"
                        type="email"
                        placeholder="anda@contoh.com"
                        icon="i-heroicons-envelope"
                        autofocus
                        class="w-full"
                        @update:model-value="onInput('email')"
                    />
                </UFormField>

                <UButton
                    type="submit"
                    color="primary"
                    block
                    :loading="form.processing"
                    class="mt-6 w-full justify-center"
                >
                    Kirim Tautan Reset
                </UButton>
            </UForm>

            <template #footer>
                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    Ingat password Anda?
                    <Link href="/login" class="text-primary-600 hover:underline">
                        Kembali ke Login
                    </Link>
                </p>
            </template>
        </UCard>
    </div>
</template>
