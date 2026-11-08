<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    role: { type: String, default: 'Owner' },
    stats: { type: Array, default: () => [] },
    charts: { type: Object, default: () => ({}) },
    recent: { type: Object, default: () => ({}) },
});

defineOptions({ layout: DashboardLayout });

const isAdmin = computed(() => props.role === 'Admin');

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const formatCurrency = (value) => {
    const number = Number(value || 0);
    if (number >= 1_000_000_000) return 'Rp ' + (number / 1_000_000_000).toFixed(1) + 'M';
    if (number >= 1_000_000) return 'Rp ' + (number / 1_000_000).toFixed(1) + 'jt';
    if (number >= 1_000) return 'Rp ' + (number / 1_000).toFixed(0) + 'rb';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number);
};

const formatDate = (iso) => {
    if (!iso) return '-';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatStatValue = (stat) => {
    if (stat.format === 'currency') return formatCurrency(stat.value);
    return formatNumber(stat.value);
};

const toneClasses = (tone) => ({
    sky:     { bg: 'bg-sky-50 dark:bg-sky-500/10',         text: 'text-sky-600 dark:text-sky-400',         ring: 'ring-sky-100 dark:ring-sky-500/20' },
    indigo:  { bg: 'bg-indigo-50 dark:bg-indigo-500/10',   text: 'text-indigo-600 dark:text-indigo-400',   ring: 'ring-indigo-100 dark:ring-indigo-500/20' },
    emerald: { bg: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400', ring: 'ring-emerald-100 dark:ring-emerald-500/20' },
    amber:   { bg: 'bg-amber-50 dark:bg-amber-500/10',     text: 'text-amber-600 dark:text-amber-400',     ring: 'ring-amber-100 dark:ring-amber-500/20' },
    fuchsia: { bg: 'bg-fuchsia-50 dark:bg-fuchsia-500/10', text: 'text-fuchsia-600 dark:text-fuchsia-400', ring: 'ring-fuchsia-100 dark:ring-fuchsia-500/20' },
    rose:    { bg: 'bg-rose-50 dark:bg-rose-500/10',       text: 'text-rose-600 dark:text-rose-400',       ring: 'ring-rose-100 dark:ring-rose-500/20' },
    cyan:    { bg: 'bg-cyan-50 dark:bg-cyan-500/10',       text: 'text-cyan-600 dark:text-cyan-400',       ring: 'ring-cyan-100 dark:ring-cyan-500/20' },
}[tone] || { bg: 'bg-gray-50 dark:bg-gray-700/40', text: 'text-gray-700 dark:text-gray-300', ring: 'ring-gray-100 dark:ring-gray-700' });

const statusBadge = (status) => {
    if (status === 'active') return { color: 'success', label: 'Aktif' };
    if (status === 'inactive') return { color: 'neutral', label: 'Nonaktif' };
    if (status === 'draft') return { color: 'warning', label: 'Draft' };
    if (status === 'pending') return { color: 'warning', label: 'Menunggu' };
    if (status === 'verified') return { color: 'success', label: 'Terverifikasi' };
    if (status === 'rejected') return { color: 'error', label: 'Ditolak' };
    return { color: 'neutral', label: status };
};

// === Chart geometry (responsive viewBox 500x200) ===
const buildChartPath = (data) => {
    if (!data || data.length === 0) return { line: '', area: '', points: [], max: 0 };
    const max = Math.max(...data, 1);
    const width = 500;
    const height = 200;
    const padY = 20;
    const usableH = height - padY * 2;
    const stepX = data.length > 1 ? width / (data.length - 1) : 0;

    const points = data.map((value, i) => {
        const x = i * stepX;
        const y = height - padY - (value / max) * usableH;
        return { x, y, value };
    });

    const line = points.map((p, i) => (i === 0 ? `M${p.x},${p.y}` : `L${p.x},${p.y}`)).join(' ');
    const area = `${line} L${points[points.length - 1].x},${height} L${points[0].x},${height} Z`;

    return { line, area, points, max };
};

const primaryChart = computed(() => {
    const c = props.charts?.primary;
    if (!c) return null;
    return { ...c, geometry: buildChartPath(c.series || []) };
});

const secondaryChart = computed(() => {
    const c = props.charts?.secondary;
    if (!c) return null;
    return { ...c, geometry: buildChartPath(c.series || []) };
});

const chartTotal = (chart) => {
    if (!chart) return 0;
    const total = (chart.series || []).reduce((sum, v) => sum + Number(v || 0), 0);
    return chart.format === 'currency' ? formatCurrency(total) : formatNumber(total);
};
</script>

<template>
    <Head title="Dasbor" />

    <div class="space-y-6">
        <!-- Title -->
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                Dasbor {{ isAdmin ? 'Admin' : 'Pemilik' }}
            </h1>
            <p class="text-sm md:text-base text-gray-500 dark:text-gray-400 mt-2">
                {{ isAdmin
                    ? 'Pantau performa platform Koze, dari pengguna hingga pendapatan iklan.'
                    : 'Ringkasan performa properti dan pengeluaran iklan eksklusif Anda.' }}
            </p>
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <UCard
                v-for="stat in stats"
                :key="stat.key"
                class="hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ stat.title }}</p>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 truncate">
                            {{ formatStatValue(stat) }}
                        </h3>
                    </div>
                    <div :class="['flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center ring-1', toneClasses(stat.tone).bg, toneClasses(stat.tone).ring]">
                        <UIcon :name="stat.icon" :class="['w-5 h-5', toneClasses(stat.tone).text]" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <span v-if="stat.delta !== null && stat.delta !== undefined" :class="['inline-flex items-center font-semibold px-1.5 py-0.5 rounded',
                        stat.delta > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400']">
                        <UIcon v-if="stat.delta > 0" name="i-lucide-trending-up" class="w-3 h-3 mr-0.5" />
                        {{ formatNumber(stat.delta) }}
                    </span>
                    <span class="text-gray-400 dark:text-gray-500">{{ stat.deltaLabel }}</span>
                </div>
            </UCard>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <UCard v-if="primaryChart">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">{{ primaryChart.title }}</h2>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">{{ primaryChart.subtitle }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Total 6 bulan</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ chartTotal(primaryChart) }}</p>
                    </div>
                </div>
                <div class="relative h-56">
                    <div class="absolute inset-0 flex flex-col justify-between pb-6 pointer-events-none">
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800"></div>
                    </div>
                    <svg class="absolute inset-0 w-full h-[calc(100%-1.25rem)] overflow-visible" viewBox="0 0 500 200" preserveAspectRatio="none">
                        <defs>
                            <linearGradient :id="`grad-primary`" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" :stop-color="primaryChart.color" stop-opacity="0.25" />
                                <stop offset="100%" :stop-color="primaryChart.color" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="primaryChart.geometry.area" :fill="`url(#grad-primary)`" />
                        <path :d="primaryChart.geometry.line" fill="none" :stroke="primaryChart.color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <g>
                            <circle
                                v-for="(p, idx) in primaryChart.geometry.points"
                                :key="idx"
                                :cx="p.x" :cy="p.y" r="4.5"
                                class="fill-white dark:fill-gray-900"
                                :stroke="primaryChart.color"
                                stroke-width="2.5"
                            />
                        </g>
                    </svg>
                    <div class="absolute bottom-0 w-full flex justify-between text-[11px] text-gray-400 dark:text-gray-500 font-medium">
                        <span v-for="label in primaryChart.labels" :key="label">{{ label }}</span>
                    </div>
                </div>
            </UCard>

            <UCard v-if="secondaryChart">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">{{ secondaryChart.title }}</h2>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">{{ secondaryChart.subtitle }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Total 6 bulan</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ chartTotal(secondaryChart) }}</p>
                    </div>
                </div>
                <div class="relative h-56">
                    <div class="absolute inset-0 flex flex-col justify-between pb-6 pointer-events-none">
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800 flex-1"></div>
                        <div class="border-t border-gray-100 dark:border-gray-800"></div>
                    </div>
                    <svg class="absolute inset-0 w-full h-[calc(100%-1.25rem)] overflow-visible" viewBox="0 0 500 200" preserveAspectRatio="none">
                        <defs>
                            <linearGradient :id="`grad-secondary`" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" :stop-color="secondaryChart.color" stop-opacity="0.25" />
                                <stop offset="100%" :stop-color="secondaryChart.color" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="secondaryChart.geometry.area" :fill="`url(#grad-secondary)`" />
                        <path :d="secondaryChart.geometry.line" fill="none" :stroke="secondaryChart.color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <g>
                            <circle
                                v-for="(p, idx) in secondaryChart.geometry.points"
                                :key="idx"
                                :cx="p.x" :cy="p.y" r="4.5"
                                class="fill-white dark:fill-gray-900"
                                :stroke="secondaryChart.color"
                                stroke-width="2.5"
                            />
                        </g>
                    </svg>
                    <div class="absolute bottom-0 w-full flex justify-between text-[11px] text-gray-400 dark:text-gray-500 font-medium">
                        <span v-for="label in secondaryChart.labels" :key="label">{{ label }}</span>
                    </div>
                </div>
            </UCard>
        </div>

        <!-- Tables: admin sees pending payments + latest properties, owner sees own props + payment history -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pending Payments (admin) / My Payments (owner) -->
            <UCard>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">
                            {{ isAdmin ? 'Pengajuan Iklan Menunggu' : 'Riwayat Pembayaran Iklan' }}
                        </h2>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ isAdmin ? 'Verifikasi pembayaran dari pemilik properti.' : 'Lima pengajuan iklan eksklusif terakhir Anda.' }}
                        </p>
                    </div>
                    <Link href="/featured-listing" class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        Lihat semua →
                    </Link>
                </div>

                <template v-if="isAdmin">
                    <div v-if="recent.pendingPayments?.length" class="space-y-2">
                        <div
                            v-for="item in recent.pendingPayments"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ item.property || '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ item.owner }} · {{ item.months }} bulan · {{ formatDate(item.created_at) }}
                                </p>
                            </div>
                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ formatCurrency(item.total) }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">
                        Tidak ada pengajuan menunggu verifikasi.
                    </div>
                </template>

                <template v-else>
                    <div v-if="recent.myPayments?.length" class="space-y-2">
                        <div
                            v-for="item in recent.myPayments"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ item.property || '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ item.months }} bulan · {{ formatDate(item.created_at) }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ formatCurrency(item.total) }}</span>
                                <UBadge :color="statusBadge(item.status).color" variant="subtle" size="xs">
                                    {{ statusBadge(item.status).label }}
                                </UBadge>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">
                        Belum ada pengajuan iklan eksklusif.
                    </div>
                </template>
            </UCard>

            <!-- Latest Properties (admin) / My Properties (owner) -->
            <UCard>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">
                            {{ isAdmin ? 'Properti Terbaru' : 'Properti Saya' }}
                        </h2>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ isAdmin ? 'Lima properti yang terakhir didaftarkan.' : 'Ringkasan status properti yang Anda kelola.' }}
                        </p>
                    </div>
                    <Link href="/properties" class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        Lihat semua →
                    </Link>
                </div>

                <template v-if="isAdmin">
                    <div v-if="recent.latestProperties?.length" class="space-y-2">
                        <div
                            v-for="item in recent.latestProperties"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ item.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    oleh {{ item.owner || '—' }} · {{ formatDate(item.created_at) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <UBadge v-if="item.is_featured" color="warning" variant="subtle" size="xs">
                                    <UIcon name="i-lucide-star" class="w-3 h-3" /> Premium
                                </UBadge>
                                <UBadge :color="statusBadge(item.status).color" variant="subtle" size="xs">
                                    {{ statusBadge(item.status).label }}
                                </UBadge>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">
                        Belum ada properti yang terdaftar.
                    </div>
                </template>

                <template v-else>
                    <div v-if="recent.myProperties?.length" class="space-y-2">
                        <div
                            v-for="item in recent.myProperties"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ item.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ item.available_rooms }}/{{ item.total_rooms }} kamar tersedia
                                    <template v-if="item.is_featured && item.featured_until"> · Premium s/d {{ formatDate(item.featured_until) }}</template>
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <UBadge v-if="item.is_featured" color="warning" variant="subtle" size="xs">
                                    <UIcon name="i-lucide-star" class="w-3 h-3" /> Premium
                                </UBadge>
                                <UBadge :color="statusBadge(item.status).color" variant="subtle" size="xs">
                                    {{ statusBadge(item.status).label }}
                                </UBadge>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">
                        Anda belum memiliki properti.
                        <div class="mt-3">
                            <Link href="/properties/create" class="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                Tambah properti pertama
                                <UIcon name="i-lucide-arrow-right" class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </template>
            </UCard>
        </div>
    </div>
</template>
