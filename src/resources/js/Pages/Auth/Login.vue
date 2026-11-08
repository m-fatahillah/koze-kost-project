<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { z } from 'zod';

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

const schema = z.object({
    email: z.string().email('Format email tidak valid'),
    password: z.string().min(8, 'Kata sandi minimal 8 karakter'),
});

const validate = (state) => {
    // Return backend errors directly into the UI form error handling mechanism
    const errors = [];
    Object.entries(form.errors).forEach(([key, message]) => {
        errors.push({ name: key, message });
    });
    return errors;
};

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

// Clear backend errors when users type, allowing frontend Zod to take over
const onInput = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};
</script>

<template>
    <Head title="Masuk" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">
                    Selamat datang kembali
                </h2>
                <p class="text-center text-gray-500 dark:text-gray-400 mt-2">
                    Masukkan detail Anda untuk mengakses akun Anda.
                </p>
            </template>

            <UAlert v-if="$page.props.flash?.error" color="error" variant="subtle" :title="$page.props.flash.error" class="mb-4" />

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

                <UFormField label="Kata Sandi" name="password">
                    <UInput
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        icon="i-heroicons-lock-closed"
                        class="w-full"
                        @input="onInput('password')"
                    >
                        <template #trailing>
                            <UButton
                                color="neutral"
                                variant="link"
                                :icon="showPassword ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                :padded="false"
                                @click="showPassword = !showPassword"
                                aria-label="Toggle password visibility"
                            />
                        </template>
                    </UInput>
                </UFormField>

                <div class="flex items-center justify-end">
                    <Link href="/forgot-password" class="text-sm font-medium text-primary-600 hover:text-primary-500 hover:underline">
                        Lupa kata sandi?
                    </Link>
                </div>

                <UButton
                    type="submit"
                    color="primary"
                    block
                    :loading="form.processing"
                    class="mt-6 w-full text-center justify-center"
                >
                    Masuk
                </UButton>
            </UForm>

            <USeparator label="ATAU" class="my-6" />

            <a href="/auth/google" class="block w-full mb-4">
                <UButton
                    color="neutral"
                    variant="outline"
                    block
                    icon="i-logos-google-icon"
                >
                    Lanjutkan dengan Google
                </UButton>
            </a>

            <template #footer>
                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    Belum punya akun?
                    <Link href="/register" class="text-primary-600 hover:underline">
                        Daftar
                    </Link>
                </p>
            </template>
        </UCard>
    </div>
</template>
