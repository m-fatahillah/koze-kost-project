<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FloatingChatWidget from '../Components/FloatingChatWidget.vue';

const page = usePage();

// Paksa light mode selama berada di landing page, lalu pulihkan saat keluar.
let hadDarkClass = false;

onMounted(() => {
    hadDarkClass = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
});

onBeforeUnmount(() => {
    if (hadDarkClass) {
        document.documentElement.classList.add('dark');
    }
});
const authUser = computed(() => page.props.auth?.user);
const isAuthenticated = computed(() => !!authUser.value);

const profileMenuItems = computed(() => [
    {
        label: authUser.value?.name || 'Profil',
        slot: 'profile',
        disabled: true,
        type: 'label',
    },
    {
        type: 'separator',
    },
    {
        label: 'Profil',
        icon: 'i-heroicons-user',
        onSelect: () => router.visit('/profile'),
    },
    {
        label: 'Properti Tersimpan',
        icon: 'i-heroicons-bookmark',
        onSelect: () => router.visit('/saved'),
    },
    {
        label: 'Pesan',
        icon: 'i-heroicons-chat-bubble-left',
        onSelect: () => router.visit('/messages'),
    },
    {
        type: 'separator',
    },
    {
        label: 'Keluar',
        icon: 'i-heroicons-arrow-left-on-rectangle',
        onSelect: () => router.post('/logout'),
    },
]);
</script>

<template>
    <div class="min-h-screen bg-[#F4F7FA] flex flex-col font-sans">
        <!-- Navbar -->
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-neutral-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Logo & Nav Links -->
                <div class="flex items-center gap-8">
                    <Link href="/" class="flex items-center gap-2">
                        <img src="/images/logo-koze-new.png" alt="Koze Kos" class="h-20 w-auto object-contain -my-4" />
                    </Link>
                    
                    <nav class="hidden md:flex items-center gap-6">
                        <Link href="/" class="text-sm font-medium text-primary-600 border-b-2 border-primary-600 py-5">
                            Sewa Kos
                        </Link>
                        <Link href="/discover/map" class="text-sm font-medium text-neutral-500 hover:text-primary-600 transition-colors">
                            Lihat Peta
                        </Link>
                    </nav>
                </div> 

                <!-- Auth Buttons -->
                <div class="flex items-center gap-3">
                    <template v-if="isAuthenticated">
                        <UDropdownMenu :items="profileMenuItems" :content="{ align: 'end' }">
                            <UButton variant="ghost" class="flex items-center gap-2 rounded-full">
                                <UAvatar :src="authUser?.avatar_url" :alt="authUser?.name" size="sm" class="ring-2 ring-primary-100" />
                                <span class="hidden sm:inline font-medium text-neutral-700">{{ authUser?.name }}</span>
                            </UButton>
                        </UDropdownMenu>
                    </template>
                    <template v-else>
                        <UButton
                            to="/login"
                            variant="link"
                            class="font-semibold text-neutral-900 hover:text-primary-600 transition-colors"
                        >
                            Masuk
                        </UButton>
                        <UButton
                            to="/register"
                            color="primary"
                            class="font-medium rounded-full px-5"
                        >
                            Daftar
                        </UButton>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Brand -->
                    <div class="col-span-1 md:col-span-2">
                        <Link href="/" class="flex items-center gap-2 mb-4">
                            <img src="/images/logo-koze-new.png" alt="Koze Kos" class="h-20 w-auto object-contain -my-4 mb-2" />
                        </Link>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6 max-w-sm">
                            Platform pencarian hunian kos premium yang memberikan kenyamanan layaknya rumah sendiri. Dirancang untuk kenyamanan, dikurasi untuk gaya hidup Anda.
                        </p>
                        <!-- Socials -->
                        <div class="flex items-center gap-3">
                            <UButton icon="i-simple-icons-instagram" color="neutral" variant="ghost" size="sm" square />
                            <UButton icon="i-simple-icons-twitter" color="neutral" variant="ghost" size="sm" square />
                            <UButton icon="i-simple-icons-facebook" color="neutral" variant="ghost" size="sm" square />
                        </div>
                    </div>

                    <!-- Links -->
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-white mb-4">Layanan</h3>
                        <ul class="space-y-3">
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Cari Kos</Link></li>
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Pasang Iklan</Link></li>
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Program Afiliasi</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-white mb-4">Perusahaan</h3>
                        <ul class="space-y-3">
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Tentang Koze Kos</Link></li>
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Syarat & Ketentuan</Link></li>
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Kebijakan Privasi</Link></li>
                            <li><Link href="/" class="text-sm text-neutral-500 hover:text-primary-600 dark:text-neutral-400">Karir</Link></li>
                        </ul>
                    </div>
                </div>
                <!-- Copyright -->
                <div class="mt-12 pt-8 border-t border-neutral-200 dark:border-neutral-800 flex flex-col md:flex-row items-center justify-between">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        &copy; 2026 Koze. Hak cipta dilindungi.
                    </p>
                    <div class="flex items-center gap-4 mt-4 md:mt-0">
                        <span class="text-sm text-neutral-500">Indonesia (ID)</span>
                    </div>
                </div>
            </div>
        </footer>

        <FloatingChatWidget />

    </div>
</template>
