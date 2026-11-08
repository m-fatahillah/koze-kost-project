<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { z } from 'zod'

// ==================== PROPS ====================
const props = defineProps({
    user: Object,
    isGoogleAccount: Boolean,
})

// ==================== STATE ====================
const avatarPreview = ref(props.user?.avatar || null)
const selectedFile = ref(null)
const avatarFileInput = ref(null)

// ==================== FORMS ====================
const profileForm = useForm({
    name: props.user?.name || '',
    avatar: null,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

// ==================== ZOD SCHEMAS ====================
const profileSchema = z.object({
    name: z.string().min(1, 'Nama wajib diisi'),
})

const passwordSchema = z.object({
    current_password: z.string().min(1, 'Password saat ini wajib diisi'),
    password: z.string().min(8, 'Password minimal 8 karakter'),
    password_confirmation: z.string().min(1, 'Konfirmasi password wajib diisi'),
}).refine((data) => data.password === data.password_confirmation, {
    message: 'Konfirmasi password tidak sesuai',
    path: ['password_confirmation'],
})

// ==================== ERROR BRIDGE ====================
const getProfileErrors = () => {
    return Object.entries(profileForm.errors).map(([name, message]) => ({ name, message }))
}

const getPasswordErrors = () => {
    return Object.entries(passwordForm.errors).map(([name, message]) => ({ name, message }))
}

const clearProfileError = (field) => {
    if (profileForm.errors[field]) profileForm.clearErrors(field)
}

const clearPasswordError = (field) => {
    if (passwordForm.errors[field]) passwordForm.clearErrors(field)
}

// ==================== AVATAR HANDLING ====================
const onAvatarChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        profileForm.avatar = file

        // Create preview
        const reader = new FileReader()
        reader.onload = (e) => {
            avatarPreview.value = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const removeAvatarFile = () => {
    selectedFile.value = null
    profileForm.avatar = null
    avatarPreview.value = props.user?.avatar || null
}

const handlePickAvatar = () => {
    avatarFileInput.value?.click()
}

// ==================== FORM SUBMISSIONS ====================
const handleSubmitProfile = () => {
    const formData = new FormData()
    formData.append('_method', 'put')
    formData.append('name', profileForm.name)
    if (profileForm.avatar) {
        formData.append('avatar', profileForm.avatar)
    }

    router.post('/profile', formData, {
        preserveState: true,
        onSuccess: () => {
            avatarPreview.value = null
            selectedFile.value = null
            profileForm.clearErrors()
        },
    })
}

const handleSubmitPassword = () => {
    passwordForm.put('/profile/password', {
        preserveState: true,
        onSuccess: () => {
            passwordForm.reset()
            passwordForm.clearErrors()
        },
    })
}
</script>

<template>
    <DashboardLayout>
        <Head title="Profil Pengguna" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profil Pengguna</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data profil dan keamanan akun Anda</p>
            </div>

            <!-- Info Profil Section -->
            <UCard class="space-y-4">
                <template #header>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Informasi Profil</h2>
                </template>

                <UForm
                    :schema="profileSchema"
                    :validate="getProfileErrors"
                    :state="profileForm"
                    @submit="handleSubmitProfile"
                    class="space-y-4"
                >
                    <!-- Avatar Section -->
                    <div class="flex flex-col gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <label class="text-sm font-medium text-gray-900 dark:text-white">Foto Profil</label>
                        <div class="flex items-end gap-4">
                            <!-- Avatar Display -->
                            <div class="flex flex-col items-center gap-2">
                                <UAvatar
                                    :alt="user.name"
                                    :src="avatarPreview || user.avatar"
                                    size="xl"
                                    class="border-2 border-gray-300 dark:border-gray-600"
                                />
                                <span class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                    {{ user.name }}
                                </span>
                            </div>

                            <!-- Upload Controls -->
                            <div class="flex flex-col gap-2 flex-1">
                                <div class="relative">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="onAvatarChange"
                                        ref="avatarFileInput"
                                    />
                                    <UButton
                                        icon="i-heroicons-photo"
                                        size="sm"
                                        color="primary"
                                        @click="handlePickAvatar"
                                    >
                                        Pilih Foto
                                    </UButton>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Format: JPG, PNG, WebP. Ukuran maksimal: 2MB
                                </p>
                                <UButton
                                    v-if="selectedFile"
                                    icon="i-heroicons-x-mark"
                                    size="sm"
                                    color="neutral"
                                    variant="soft"
                                    @click="removeAvatarFile"
                                >
                                    Batalkan Pilihan
                                </UButton>
                            </div>
                        </div>
                    </div>

                    <!-- Name Field -->
                    <UFormField label="Nama Lengkap" name="name" required>
                        <UInput
                            v-model="profileForm.name"
                            placeholder="Masukkan nama lengkap"
                            class="w-full"
                            @update:model-value="clearProfileError('name')"
                        />
                    </UFormField>

                    <!-- Email Field (Read-only) -->
                    <UFormField label="Email" name="email">
                        <UInput
                            :value="user.email"
                            disabled
                            placeholder="Email Anda"
                            class="w-full"
                            help-text="Email tidak dapat diubah saat ini"
                        />
                    </UFormField>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-2">
                        <UButton
                            type="submit"
                            color="primary"
                            :loading="profileForm.processing"
                        >
                            <span v-if="!profileForm.processing">Simpan Perubahan</span>
                            <span v-else>Menyimpan...</span>
                        </UButton>
                    </div>
                </UForm>
            </UCard>

            <!-- Keamanan Section -->
            <UCard class="space-y-4">
                <template #header>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Keamanan Akun</h2>
                </template>

                <!-- Google Account Warning -->
                <UAlert
                    v-if="isGoogleAccount"
                    icon="i-heroicons-information-circle"
                    title="Akun Terhubung Google"
                    description="Akun Anda terhubung dengan Google. Anda tidak dapat mengubah password dari halaman ini."
                    color="info"
                />

                <!-- Password Form (Hidden for Google Account) -->
                <div v-if="!isGoogleAccount">
                    <UForm
                        :schema="passwordSchema"
                        :validate="getPasswordErrors"
                        :state="passwordForm"
                        @submit="handleSubmitPassword"
                        class="space-y-4"
                    >
                        <!-- Current Password -->
                        <UFormField label="Password Saat Ini" name="current_password" required>
                            <UInput
                                v-model="passwordForm.current_password"
                                type="password"
                                placeholder="Masukkan password saat ini"
                                class="w-full"
                                @update:model-value="clearPasswordError('current_password')"
                            />
                        </UFormField>

                        <!-- New Password -->
                        <UFormField label="Password Baru" name="password" required>
                            <UInput
                                v-model="passwordForm.password"
                                type="password"
                                placeholder="Masukkan password baru minimal 8 karakter"
                                class="w-full"
                                @update:model-value="clearPasswordError('password')"
                            />
                        </UFormField>

                        <!-- Confirm Password -->
                        <UFormField label="Konfirmasi Password" name="password_confirmation" required>
                            <UInput
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                placeholder="Konfirmasi password baru"
                                class="w-full"
                                @update:model-value="clearPasswordError('password_confirmation')"
                            />
                        </UFormField>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-2">
                            <UButton
                                type="submit"
                                color="primary"
                                :loading="passwordForm.processing"
                            >
                                <span v-if="!passwordForm.processing">Ubah Password</span>
                                <span v-else>Mengubah Password...</span>
                            </UButton>
                        </div>
                    </UForm>
                </div>
            </UCard>
        </div>
    </DashboardLayout>
</template>
