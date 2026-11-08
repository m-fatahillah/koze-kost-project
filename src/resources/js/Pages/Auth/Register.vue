<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { z } from 'zod';

const props = defineProps({
    googleData: Object,
});

const form = useForm({
    name: props.googleData?.name || '',
    email: props.googleData?.email || '',
    password: '',
    password_confirmation: '',
    role: 'Seeker', // Default role
});

const showPassword = ref(false);
const showPasswordConfig = ref(false);

const schema = z.object({
    name: z.string().min(1, 'Nama lengkap wajib diisi'),
    email: z.string().email('Format email tidak valid').min(1, 'Email wajib diisi'),
    password: z.string().min(8, 'Kata sandi minimal 8 karakter'),
    password_confirmation: z.string(),
    role: z.enum(['Seeker', 'Owner'], { required_error: 'Pilih opsi ini' }),
}).refine(data => data.password === data.password_confirmation, {
    message: 'Konfirmasi kata sandi tidak cocok',
    path: ['password_confirmation']
});

const validate = () => {
    const errors = [];
    Object.entries(form.errors).forEach(([key, message]) => {
        errors.push({ name: key, message });
    });
    return errors;
};

const clearError = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const roleOptions = [
    { value: 'Seeker', title: 'Pencari Kos', description: 'Mencari kamar', icon: 'i-heroicons-home' },
    { value: 'Owner', title: 'Pemilik Kos', description: 'Menyewakan properti', icon: 'i-heroicons-building-office-2' },
];
</script>

<template>
    <Head title="Daftar" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">
                    Buat akun baru
                </h2>
                <p class="text-center text-gray-500 dark:text-gray-400 mt-2">
                    Bergabunglah sekarang untuk mencari atau mendaftarkan properti Anda.
                </p>
            </template>

            <UAlert v-if="$page.props.flash?.error" color="error" variant="subtle" :title="$page.props.flash.error" class="mb-4" />

            <UForm :schema="schema" :validate="validate" :state="form" @submit="submit" class="space-y-4">
                <UFormField label="Nama Lengkap" name="name">
                    <UInput
                        v-model="form.name"
                        type="text"
                        placeholder="Budi Santoso"
                        icon="i-heroicons-user"
                        autofocus
                        class="w-full"
                        :readonly="!!props.googleData"
                        @update:model-value="clearError('name')"
                    />
                </UFormField>

                <UFormField label="Pos-el" name="email">
                    <UInput
                        v-model="form.email"
                        type="email"
                        placeholder="anda@contoh.com"
                        icon="i-heroicons-envelope"
                        class="w-full"
                        :readonly="!!props.googleData"
                        @update:model-value="clearError('email')"
                    />
                </UFormField>

                <UFormField label="Kata Sandi" name="password">
                    <UInput
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        icon="i-heroicons-lock-closed"
                        class="w-full"
                        @update:model-value="clearError('password')"
                    >
                        <template #trailing>
                            <UButton
                                color="neutral"
                                variant="link"
                                :icon="showPassword ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                :padded="false"
                                @click="showPassword = !showPassword"
                                aria-label="Tampilkan kata sandi"
                            />
                        </template>
                    </UInput>
                </UFormField>

                <UFormField label="Konfirmasi Kata Sandi" name="password_confirmation">
                    <UInput
                        v-model="form.password_confirmation"
                        :type="showPasswordConfig ? 'text' : 'password'"
                        placeholder="••••••••"
                        icon="i-heroicons-lock-closed"
                        class="w-full"
                        @update:model-value="clearError('password_confirmation')"
                    >
                        <template #trailing>
                            <UButton
                                color="neutral"
                                variant="link"
                                :icon="showPasswordConfig ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
                                :padded="false"
                                @click="showPasswordConfig = !showPasswordConfig"
                                aria-label="Tampilkan konfirmasi kata sandi"
                            />
                        </template>
                    </UInput>
                </UFormField>

                <UFormField label="Saya ingin mendaftar sebagai" name="role">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                        <div
                            v-for="option in roleOptions"
                            :key="option.value"
                            @click="form.role = option.value"
                            class="cursor-pointer border rounded-xl p-4 text-center transition-all duration-200"
                            :class="form.role === option.value ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/20 ring-1 ring-primary-500' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'"
                        >
                            <UIcon :name="option.icon" class="w-8 h-8 mx-auto mb-2" :class="form.role === option.value ? 'text-primary-500' : 'text-gray-400'" />
                            <div class="font-semibold text-gray-900 dark:text-white">{{ option.title }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ option.description }}
                            </div>
                        </div>
                    </div>
                </UFormField>

                <UButton
                    type="submit"
                    color="primary"
                    block
                    :loading="form.processing"
                    class="mt-6 w-full text-center justify-center"
                >
                    Daftar
                </UButton>
            </UForm>

            <USeparator label="ATAU" class="my-6" />

            <a href="/auth/google" class="block w-full mb-4">
                <UButton
                    color="neutral"
                    variant="outline"
                    block
                    icon="i-logos-google-icon"
                    class="w-full text-center justify-center"
                >
                    Lanjutkan dengan Google
                </UButton>
            </a>

            <template #footer>
                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    Sudah punya akun?
                    <Link href="/login" class="text-primary-600 hover:underline">
                        Masuk
                    </Link>
                </p>
            </template>
        </UCard>
    </div>
</template>
