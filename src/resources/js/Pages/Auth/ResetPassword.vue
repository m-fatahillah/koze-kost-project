<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { z } from 'zod';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const schema = z.object({
    email: z.string().email('Format email tidak valid'),
    password: z.string().min(8, 'Kata sandi minimal 8 karakter'),
    password_confirmation: z.string().min(8, 'Kata sandi minimal 8 karakter'),
}).refine((data) => data.password === data.password_confirmation, {
    message: "Konfirmasi kata sandi tidak cocok",
    path: ["password_confirmation"],
});

const validate = (state) => {
    const errors = [];
    Object.entries(form.errors).forEach(([key, message]) => {
        errors.push({ name: key, message });
    });
    return errors;
};

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const onInput = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};
</script>

<template>
    <Head title="Buat Password Baru" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">
                    Buat Password Baru
                </h2>
                <p class="text-center text-gray-500 dark:text-gray-400 mt-2 text-sm">
                    Silakan masukkan kata sandi baru Anda.
                </p>
            </template>

            <UForm :state="form" :schema="schema" :validate="validate" @submit="submit" class="space-y-4">
                <UFormField label="Pos-el" name="email">
                    <UInput
                        v-model="form.email"
                        type="email"
                        readonly
                        icon="i-heroicons-envelope"
                        class="w-full bg-gray-100 dark:bg-gray-800"
                    />
                </UFormField>

                <UFormField label="Kata Sandi Baru" name="password">
                    <UInput
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        icon="i-heroicons-lock-closed"
                        class="w-full"
                        autofocus
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

                <UFormField label="Konfirmasi Kata Sandi" name="password_confirmation">
                    <UInput
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        placeholder="••••••••"
                        icon="i-heroicons-lock-closed"
                        class="w-full"
                        @input="onInput('password_confirmation')"
                    >
                        <template #trailing>
                            <UButton
                                color="neutral"
                                variant="link"
                                :icon="showPasswordConfirmation ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                :padded="false"
                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                                aria-label="Toggle password visibility"
                            />
                        </template>
                    </UInput>
                </UFormField>

                <UButton
                    type="submit"
                    color="primary"
                    block
                    :loading="form.processing"
                    class="mt-6 w-full justify-center"
                >
                    Reset Password
                </UButton>
            </UForm>
            
            <template #footer>
                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    <Link href="/login" class="text-primary-600 hover:underline">
                        Kembali ke Login
                    </Link>
                </p>
            </template>
        </UCard>
    </div>
</template>
