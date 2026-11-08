<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import LandingLayout from '@/Layouts/LandingLayout.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    appName: String,
    faqs: {
        type: Array,
        default: () => [],
    },
    featuredProperties: {
        type: Array,
        default: () => [],
    },
    sidebarAd: {
        type: Object,
        default: null,
    },
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
};

const labelGender = (value) => {
    if (value === 'putra') return 'Putra';
    if (value === 'putri') return 'Putri';
    return 'Campur';
};

const labelPriceType = (value) => {
    if (value === 'daily') return '/ hari';
    if (value === 'yearly') return '/ tahun';
    return '/ bulan';
};

const titleCase = (value) => {
    return String(value || '')
        .split(' ')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const heroCards = computed(() => {
    return [...props.featuredProperties].slice(0, 8);
});

const types = ['Putra', 'Putri', 'Campur'];
const prices = ['Semua Harga', '< 1 Juta', '1 - 2 Juta', '> 2 Juta'];

const searchForm = ref({
    tipe: 'Putri, Putra & Campur',
    harga: prices[0],
});

const mapRef = ref(null);
const mapLoaded = ref(false);
const mapLoading = ref(true);
const locationStatus = ref('Meminta akses lokasi...');
const markerCount = ref(0);
const selectedProperty = ref(null);
const currentCenter = ref({ lat: -6.2, lng: 106.816666 });
const userLocation = ref(null);
const visibleProperties = ref([]);

let map = null;
let markerLayer = null;
let userMarker = null;
let fetchTimer = null;
let requestAbortController = null;
let leaflet = null;

const getLeaflet = async () => {
    if (leaflet) {
        return leaflet;
    }

    const module = await import('leaflet');
    leaflet = module.default;
    return leaflet;
};

const distanceKm = (from, to) => {
    if (!from || !to) return null;

    const toRad = (value) => (value * Math.PI) / 180;
    const earthRadiusKm = 6371;
    const deltaLat = toRad(to.lat - from.lat);
    const deltaLng = toRad(to.lng - from.lng);
    const lat1 = toRad(from.lat);
    const lat2 = toRad(to.lat);

    const a = Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2)
        + Math.sin(deltaLng / 2) * Math.sin(deltaLng / 2) * Math.cos(lat1) * Math.cos(lat2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return Math.round(earthRadiusKm * c * 10) / 10;
};

const enrichProperty = (property) => ({
    ...property,
    distance: userLocation.value
        ? distanceKm(userLocation.value, { lat: property.latitude, lng: property.longitude })
        : null,
});

const sortedHeroCards = computed(() => {
    const items = heroCards.value.map(enrichProperty);

    if (!userLocation.value) {
        return items;
    }

    return items.sort((left, right) => {
        const leftDistance = left.distance ?? Number.MAX_SAFE_INTEGER;
        const rightDistance = right.distance ?? Number.MAX_SAFE_INTEGER;
        return leftDistance - rightDistance;
    });
});

const nearestProperties = computed(() => {
    const items = visibleProperties.value.length > 0 ? visibleProperties.value : heroCards.value;
    return [...items].map(enrichProperty).sort((left, right) => {
        const leftDistance = left.distance ?? Number.MAX_SAFE_INTEGER;
        const rightDistance = right.distance ?? Number.MAX_SAFE_INTEGER;
        return leftDistance - rightDistance;
    }).slice(0, 6);
});

const propertyPinIcon = (L, property) => {
    const featured = property.is_featured;
    return L.divIcon({
        className: 'vk-pin-wrapper',
        html: `<div class="vk-pin ${featured ? 'vk-pin--featured' : ''}"><div class="vk-pin__inner"></div></div>`,
        iconSize: [28, 38],
        iconAnchor: [14, 38],
        popupAnchor: [0, -40],
    });
};

const popupTemplate = (property) => {
    const price = formatRupiah(property.price);
    const period = labelPriceType(property.price_type);
    const thumbnail = property.thumbnail_url || 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?q=80&w=900&auto=format&fit=crop';

    return `
        <div class="vk-popup">
            <img src="${thumbnail}" alt="${property.name}" class="vk-popup__image" />
            <div class="vk-popup__body">
                <div class="vk-popup__badges">
                    <span class="vk-popup__badge ${property.is_featured ? 'vk-popup__badge--featured' : ''}">${property.is_featured ? 'Unggulan' : titleCase(property.type)}</span>
                    <span class="vk-popup__badge vk-popup__badge--soft">${labelGender(property.gender_restriction)}</span>
                </div>
                <h3 class="vk-popup__title">${property.name}</h3>
                <p class="vk-popup__address">${property.address}</p>
                <div class="vk-popup__price">${price} <span>${period}</span></div>
                <a href="/property/${property.id}" class="vk-popup__link">Lihat detail</a>
            </div>
        </div>
    `;
};

const renderMarkers = (L, properties) => {
    if (!markerLayer || !map) return;

    markerLayer.clearLayers();
    properties.forEach((property) => {
        const marker = L.marker([property.latitude, property.longitude], {
            icon: propertyPinIcon(L, property),
        });

        marker.bindPopup(popupTemplate(property), {
            closeButton: false,
            maxWidth: 280,
            className: 'vk-popup-container',
        });

        marker.on('click', () => {
            selectedProperty.value = enrichProperty(property);
        });

        marker.addTo(markerLayer);
    });

    markerCount.value = properties.length;
};

const fetchMarkers = async () => {
    if (!map) return;

    const L = await getLeaflet();

    if (requestAbortController) {
        requestAbortController.abort();
    }

    requestAbortController = new AbortController();
    mapLoading.value = true;

    const bounds = map.getBounds();
    const zoom = map.getZoom();
    const limit = zoom < 11 ? 40 : zoom < 13 ? 80 : 140;

    const params = new URLSearchParams({
        south: String(bounds.getSouth()),
        north: String(bounds.getNorth()),
        west: String(bounds.getWest()),
        east: String(bounds.getEast()),
        limit: String(limit),
    });

    try {
        const response = await fetch(`/properties/markers?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: requestAbortController.signal,
        });

        if (!response.ok) {
            throw new Error('Gagal memuat marker');
        }

        const payload = await response.json();
        visibleProperties.value = payload.data || [];
    renderMarkers(L, visibleProperties.value);

        if (!selectedProperty.value && visibleProperties.value.length > 0) {
            selectedProperty.value = enrichProperty(visibleProperties.value[0]);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            locationStatus.value = 'Peta gagal dimuat, coba muat ulang.';
        }
    } finally {
        mapLoading.value = false;
    }
};

const scheduleFetchMarkers = () => {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(() => {
        fetchMarkers();
    }, 220);
};

const setUserMarker = async (lat, lng) => {
    if (!map) return;

    const L = await getLeaflet();

    currentCenter.value = { lat, lng };
    userLocation.value = { lat, lng };

    if (!userMarker) {
        userMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'vk-user-pin-wrapper',
                html: '<div class="vk-user-pin"><div class="vk-user-pin__pulse"></div><div class="vk-user-pin__dot"></div></div>',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            }),
        }).addTo(map);
    } else {
        userMarker.setLatLng([lat, lng]);
    }

    map.setView([lat, lng], 14, { animate: true });
    locationStatus.value = 'Fokus ke lokasi kamu';
    mapLoaded.value = true;
    scheduleFetchMarkers();
};

const fallbackLocation = async () => {
    const fallback = { lat: -6.2, lng: 106.816666 };
    currentCenter.value = fallback;
    locationStatus.value = 'Lokasi belum diizinkan, fokus ke area default';
    map.setView([fallback.lat, fallback.lng], 12, { animate: true });
    mapLoaded.value = true;
    scheduleFetchMarkers();
};

const requestBrowserLocation = () => {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            locationStatus.value = 'Browser tidak mendukung lokasi';
            fallbackLocation();
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                locationStatus.value = 'Lokasi berhasil diaktifkan';
                setUserMarker(lat, lng);
                resolve({ lat, lng });
            },
            () => {
                fallbackLocation();
                resolve(null);
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 300000,
            }
        );
    });
};

const initMap = async () => {
    if (!mapRef.value || map) return;

    const L = await getLeaflet();

    map = L.map(mapRef.value, {
        zoomControl: false,
        preferCanvas: true,
        minZoom: 4,
    });

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    markerLayer = L.layerGroup().addTo(map);
    map.setView([currentCenter.value.lat, currentCenter.value.lng], 12);

    map.on('moveend', scheduleFetchMarkers);
    map.on('zoomend', scheduleFetchMarkers);

    try {
        await requestBrowserLocation();
        await fetchMarkers();
    } catch (error) {
        locationStatus.value = 'Peta gagal diinisialisasi.';
        mapLoading.value = false;
    }
};

const focusOnUser = () => {
    if (userLocation.value) {
        setUserMarker(userLocation.value.lat, userLocation.value.lng);
        return;
    }

    requestBrowserLocation();
};

const openDiscover = () => {
    router.visit('/discover');
};

const openFilteredDiscover = () => {
    const params = new URLSearchParams();

    if (searchForm.value.tipe && searchForm.value.tipe !== 'Putri, Putra & Campur') params.set('type', searchForm.value.tipe);
    if (searchForm.value.harga && searchForm.value.harga !== 'Semua Harga') params.set('price', searchForm.value.harga);

    const query = params.toString();
    router.visit(query ? `/discover?${query}` : '/discover');
};

onMounted(async () => {
    await nextTick();
    await initMap();
});

onBeforeUnmount(() => {
    clearTimeout(fetchTimer);

    if (requestAbortController) {
        requestAbortController.abort();
    }

    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <Head :title="appName || 'Welcome'" />

    <LandingLayout>
        <section class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20 lg:pt-24 min-h-[85vh] flex flex-col justify-center">
            <!-- Decorative Background Orbs -->
            <div class="absolute top-10 -left-20 w-72 h-72 bg-primary-300/20 rounded-full blur-[80px] pointer-events-none animate-float"></div>
            <div class="absolute bottom-10 right-1/4 w-96 h-96 bg-blue-300/20 rounded-full blur-[100px] pointer-events-none animate-float-delayed"></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center relative z-10">
                <div class="space-y-10">
                    <div class="space-y-6 opacity-0 animate-fade-in-up">
                        <h1 class="text-4xl sm:text-5xl lg:text-5xl xl:text-[4rem] font-extrabold text-neutral-900 leading-[1.1] tracking-tight">
                            Temukan Kos <br />
                            Nyaman di Lokasi <br />
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-blue-500">Impianmu</span>
                        </h1>
                        <p class="text-lg text-neutral-600 max-w-lg leading-relaxed">
                            Hunian nyaman premium terbaik dengan fasilitas lengkap dan akses strategis di seluruh penjuru kota bersama <span class="font-semibold text-primary-600">Koze Kos</span>.
                        </p>
                    </div>

                    <div class="relative w-full lg:max-w-2xl group z-10 opacity-0 animate-fade-in-up delay-200">
                        <!-- Animated decorative blur -->
                        <div class="absolute -inset-1 bg-gradient-to-r from-primary-600 via-blue-500 to-cyan-400 rounded-[2rem] blur-xl opacity-20 group-hover:opacity-30 transition duration-1000 group-hover:duration-300"></div>
                        
                        <!-- Search Card -->
                        <div class="relative bg-white/95 backdrop-blur-xl border border-white shadow-2xl shadow-slate-200/50 p-4 sm:p-5 rounded-[2rem] flex flex-col gap-5">
                            <div class="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-slate-100/80 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <!-- Tipe Kos -->
                                <div class="group flex-1 px-4 py-3 hover:bg-white transition-all duration-300 rounded-t-2xl sm:rounded-none sm:rounded-l-2xl cursor-pointer hover:shadow-[0_0_15px_rgba(0,0,0,0.03)] hover:z-10 relative">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 flex items-center gap-1.5 group-hover:text-primary-500 transition-colors">
                                        <UIcon name="i-heroicons-home-modern-solid" class="w-3.5 h-3.5 text-primary-400 group-hover:scale-125 group-hover:-translate-y-0.5 transition-transform duration-300" /> Tipe Kos
                                    </label>
                                    <USelect v-model="searchForm.tipe" :items="['Putri, Putra & Campur', ...types]" variant="none" class="px-0 -mx-3 !shadow-none font-bold text-slate-800 text-sm [&_span>svg]:transition-all [&_span>svg]:duration-200 group-active:[&_span>svg]:scale-75 group-active:[&_span>svg]:translate-y-1" />
                                </div>
                                <!-- Harga -->
                                <div class="group flex-1 px-4 py-3 hover:bg-white transition-all duration-300 rounded-b-2xl sm:rounded-none sm:rounded-r-2xl cursor-pointer hover:shadow-[0_0_15px_rgba(0,0,0,0.03)] hover:z-10 relative">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 flex items-center gap-1.5 group-hover:text-primary-500 transition-colors">
                                        <UIcon name="i-heroicons-banknotes-solid" class="w-3.5 h-3.5 text-primary-400 group-hover:scale-125 group-hover:-translate-y-0.5 transition-transform duration-300" /> Harga
                                    </label>
                                    <USelect v-model="searchForm.harga" :items="prices" variant="none" class="px-0 -mx-3 !shadow-none font-bold text-slate-800 text-sm [&_span>svg]:transition-all [&_span>svg]:duration-200 group-active:[&_span>svg]:scale-75 group-active:[&_span>svg]:translate-y-1" />
                                </div>
                            </div>

                            <UButton 
                                color="primary" 
                                block
                                size="lg"
                                class="rounded-[1.25rem] py-3.5 font-bold text-base shadow-xl shadow-primary-500/30 hover:scale-[1.02] active:scale-95 transition-all duration-300 flex justify-center items-center gap-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700"
                                @click="openFilteredDiscover"
                            >
                                <UIcon name="i-heroicons-magnifying-glass" class="w-5 h-5" />
                                Cari Hunian Sekarang
                            </UButton>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 h-[400px] lg:h-[500px] xl:h-[580px] rounded-[2rem] overflow-hidden shadow-2xl shadow-slate-200/60 ring-1 ring-slate-100 opacity-0 animate-fade-in-up delay-300">
                    <!-- Top bar — z-[1001] beats Leaflet's internal z-index ceiling of 1000 -->
                    <div class="absolute top-0 inset-x-0 z-[1001] flex items-center justify-between gap-3 px-4 py-3 bg-white/90 backdrop-blur-md border-b border-slate-100/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary-50 flex items-center justify-center">
                                <UIcon name="i-heroicons-map" class="w-4 h-4 text-primary-600" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-800 leading-tight">Peta Properti</p>
                                <p class="text-[10px] text-slate-400 leading-tight">{{ markerCount }} properti di area ini</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-lg transition-colors"
                                :class="{ 'opacity-50 pointer-events-none': mapLoading }"
                                @click="fetchMarkers"
                            >
                                <UIcon name="i-heroicons-arrow-path" class="w-3.5 h-3.5" :class="mapLoading ? 'animate-spin' : ''" />
                                Muat ulang
                            </button>
                            <Link href="/discover/map" class="flex items-center gap-1.5 text-[11px] font-bold text-white bg-primary-500 hover:bg-primary-600 px-3 py-1.5 rounded-lg transition-colors shadow-sm shadow-primary-500/30">
                                <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                Buka Peta Penuh
                            </Link>
                        </div>
                    </div>

                    <!-- Map -->
                    <div ref="mapRef" class="absolute inset-0 map-canvas"></div>

                    <!-- User location button -->
                    <div class="absolute bottom-4 right-4 z-[1001]">
                        <button
                            class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white text-slate-700 shadow-lg border border-slate-200 text-xs font-semibold hover:scale-105 active:scale-95 transition-transform"
                            @click="focusOnUser"
                        >
                            <span class="w-2 h-2 rounded-full shrink-0" :class="userLocation ? 'bg-emerald-400' : 'bg-slate-300'"></span>
                            <UIcon name="i-heroicons-map-pin-solid" class="w-3.5 h-3.5 text-primary-500" />
                            Lokasi Saya
                        </button>
                    </div>

                    <!-- Selected property card -->
                    <div v-if="selectedProperty" class="absolute bottom-4 left-4 z-[1001] w-[min(85%,20rem)]">
                        <div class="rounded-2xl border border-white/80 bg-white/95 p-3 shadow-2xl backdrop-blur-xl">
                            <div class="flex gap-3">
                                <img
                                    :src="selectedProperty.thumbnail_url || 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?q=80&w=900&auto=format&fit=crop'"
                                    class="h-20 w-16 rounded-xl object-cover shrink-0"
                                    :alt="selectedProperty.name"
                                />
                                <div class="min-w-0 flex-1 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                :class="selectedProperty.is_featured ? 'bg-amber-100 text-amber-700' : 'bg-primary-50 text-primary-700'">
                                                {{ selectedProperty.is_featured ? 'Unggulan' : titleCase(selectedProperty.type) }}
                                            </span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                                {{ labelGender(selectedProperty.gender_restriction) }}
                                            </span>
                                        </div>
                                        <h3 class="truncate text-sm font-bold text-slate-900 leading-tight">{{ selectedProperty.name }}</h3>
                                        <p class="truncate text-[11px] text-slate-400 mt-0.5">{{ selectedProperty.address }}</p>
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-sm font-extrabold text-primary-600">{{ formatRupiah(selectedProperty.price) }}<span class="text-[10px] font-normal text-slate-400"> {{ labelPriceType(selectedProperty.price_type) }}</span></span>
                                        <Link :href="`/property/${selectedProperty.id}`" class="text-[11px] font-bold text-white bg-slate-900 hover:bg-slate-700 px-3 py-1.5 rounded-lg transition-colors">
                                            Detail
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="mapLoading" class="absolute inset-0 flex items-center justify-center bg-white/20 backdrop-blur-[1px] z-[300]">
                        <div class="rounded-xl bg-white/90 px-4 py-2.5 text-xs font-bold text-primary-600 shadow-xl flex items-center gap-2">
                            <UIcon name="i-heroicons-arrow-path" class="w-4 h-4 animate-spin" />
                            Memuat properti...
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <!-- Section Header -->
            <div class="flex items-end justify-between mb-10">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="w-1 h-6 bg-primary-500 rounded-full"></div>
                        <span class="text-xs font-bold uppercase tracking-widest text-primary-600">Pilihan Terbaik</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-neutral-900 tracking-tight">Rekomendasi Teratas</h2>
                    <p class="text-neutral-500">Hunian pilihan dengan rating tertinggi minggu ini di <span class="font-semibold text-primary-600">Koze Kos</span></p>
                </div>
                <UButton variant="outline" color="primary" class="font-semibold hidden sm:flex items-center gap-2 rounded-xl px-5 py-2.5 border-primary-200 hover:bg-primary-50 transition-all group" @click="openDiscover">
                    Lihat Semua
                    <UIcon name="i-heroicons-arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                </UButton>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
                <!-- Property Cards Grid -->
                <div class="xl:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="property in sortedHeroCards"
                        :key="property.id"
                        class="group relative bg-white rounded-2xl overflow-hidden border border-neutral-100 hover:border-primary-100 hover:shadow-2xl hover:shadow-primary-500/10 transition-all duration-300 cursor-pointer flex flex-col"
                        @click="$inertia?.visit?.('/property/' + property.id)"
                    >
                        <!-- Image Section -->
                        <div class="relative h-52 overflow-hidden">
                            <img
                                :src="property.thumbnail_url || 'https://images.unsplash.com/photo-1560185008-b033106afef8?q=80&w=900&auto=format&fit=crop'"
                                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out"
                                :alt="property.name"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                            <!-- Badges -->
                            <div class="absolute top-3 left-3 flex flex-col gap-2">
                                <span v-if="property.is_featured" class="inline-flex items-center gap-1 bg-amber-400/95 backdrop-blur-sm text-amber-900 text-xs font-bold px-2.5 py-1 rounded-lg shadow-sm">
                                    <UIcon name="i-heroicons-star-solid" class="w-3 h-3" /> Unggulan
                                </span>
                                <span v-else class="inline-flex items-center bg-neutral-900/70 backdrop-blur-sm text-white text-xs font-medium px-2.5 py-1 rounded-lg">
                                    {{ titleCase(property.type) }}
                                </span>
                            </div>
                            <span class="absolute top-3 right-3 text-xs font-bold px-3 py-1.5 rounded-xl shadow-lg"
                                :class="{
                                    'bg-pink-500 text-white': property.gender_restriction === 'putri',
                                    'bg-blue-500 text-white': property.gender_restriction === 'putra',
                                    'bg-green-500 text-white': !property.gender_restriction || property.gender_restriction === 'campur'
                                }"
                            >{{ labelGender(property.gender_restriction) }}</span>
                        </div>

                        <!-- Content Section -->
                        <div class="flex flex-col flex-1 p-4">
                            <h3 class="font-bold text-base text-neutral-900 truncate group-hover:text-primary-600 transition-colors mb-1">{{ property.name }}</h3>
                            <p class="text-xs text-neutral-400 flex items-center gap-1 truncate mb-3">
                                <UIcon name="i-heroicons-map-pin-solid" class="w-3 h-3 flex-shrink-0 text-neutral-300" />
                                {{ property.address }}
                            </p>

                            <!-- Description -->
                            <p v-if="property.description" class="text-xs text-neutral-500 leading-relaxed line-clamp-2 mb-3">
                                {{ property.description }}
                            </p>

                            <!-- Facilities -->
                            <div v-if="property.facilities?.length" class="flex flex-wrap gap-1.5 mb-4">
                                <span
                                    v-for="facility in property.facilities.slice(0, 4)"
                                    :key="facility.id"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-neutral-600 bg-neutral-50 border border-neutral-100 px-2 py-0.5 rounded-md"
                                >
                                    <UIcon v-if="facility.icon" :name="facility.icon" class="w-3 h-3 text-primary-400" />
                                    {{ facility.name }}
                                </span>
                                <span v-if="property.facilities.length > 4" class="inline-flex items-center text-[11px] font-medium text-primary-500 px-1">
                                    +{{ property.facilities.length - 4 }} lainnya
                                </span>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-between pt-3 border-t border-neutral-100 mt-auto">
                                <div class="flex items-center gap-1 bg-amber-50 px-2 py-1 rounded-lg">
                                    <UIcon name="i-heroicons-star-solid" class="w-3.5 h-3.5 text-amber-400" />
                                    <span class="text-xs font-bold text-amber-700">{{ property.distance ?? property.rating ?? '4.8' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-sm text-primary-600">{{ formatRupiah(property.price) }}</span>
                                    <span class="text-xs text-neutral-400 ml-0.5">{{ labelPriceType(property.price_type) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="flex flex-col gap-5">
                    <!-- Slot Iklan (dinamis dari admin) -->
                    <a
                        v-if="sidebarAd && sidebarAd.image_url"
                        :href="sidebarAd.target_url"
                        target="_blank"
                        rel="noopener nofollow"
                        class="relative block rounded-2xl overflow-hidden ring-1 ring-neutral-200 hover:ring-primary-300 hover:shadow-lg transition group aspect-square w-full"
                    >
                        <img :src="sidebarAd.image_url" :alt="sidebarAd.title" class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500" />
                        <span class="absolute top-2 left-2 z-10 text-[10px] font-bold uppercase tracking-wider bg-white/90 text-neutral-700 px-2 py-0.5 rounded-md backdrop-blur-sm">
                            Iklan
                        </span>
                        <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/70 to-transparent p-3 text-white text-xs">
                            <div class="font-bold leading-tight line-clamp-2">{{ sidebarAd.title }}</div>
                            <div class="text-[10px] opacity-80">oleh {{ sidebarAd.advertiser_name }}</div>
                        </div>
                    </a>
                    <div v-else class="relative bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl overflow-hidden p-6 text-white">
                        <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-primary-400/20 rounded-full blur-2xl"></div>
                        <div class="relative z-10">
                            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mb-5 ring-1 ring-white/30">
                                <UIcon name="i-heroicons-home-modern" class="w-6 h-6 text-white" />
                            </div>
                            <h3 class="font-bold text-lg mb-2 leading-snug">Pasang Iklan Anda</h3>
                            <p class="text-sm text-primary-100 mb-5 leading-relaxed">
                                Jangkau lebih banyak calon penyewa dengan iklan yang tampil menonjol.
                            </p>
                            <button class="w-full bg-white text-primary-600 font-bold text-sm py-2.5 rounded-xl hover:bg-primary-50 transition-colors shadow-lg shadow-primary-900/20">
                                Pasang Iklan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <UButton variant="outline" color="primary" class="font-semibold sm:hidden w-full justify-center mt-6 flex items-center gap-2 rounded-xl" @click="openDiscover">
                Lihat Semua Rekomendasi
                <UIcon name="i-heroicons-arrow-right" class="w-4 h-4" />
            </UButton>
        </section>

        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 pb-32 relative overflow-hidden">
            <div class="absolute top-1/4 -left-20 w-80 h-80 bg-primary-100/40 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-blue-50/50 rounded-full blur-[100px] pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row gap-16 items-start relative z-10">
                <div class="lg:w-1/3 space-y-8 lg:sticky lg:top-24">
                    <div class="space-y-4">
                        <UBadge color="primary" variant="subtle" class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-widest ring-1 ring-primary-200">
                            Bantuan & Dukungan
                        </UBadge>
                        <h2 class="text-4xl sm:text-5xl font-extrabold text-neutral-900 leading-[1.1] tracking-tight">
                            Punya <br />
                            <span class="text-primary-600 relative inline-block">
                                Pertanyaan?
                                <svg class="absolute -bottom-2 left-0 w-full h-2 text-primary-200" viewBox="0 0 100 10" preserveAspectRatio="none">
                                    <path d="M0 5 Q 25 0, 50 5 T 100 5" fill="none" stroke="currentColor" stroke-width="4" />
                                </svg>
                            </span>
                        </h2>
                        <p class="text-neutral-600 text-lg leading-relaxed max-w-md">
                            Temukan jawaban cepat untuk pertanyaan yang paling sering diajukan. Kami siap membantu kenyamanan hunian Anda.
                        </p>
                    </div>

                    <div class="p-6 bg-primary-50/50 rounded-3xl border border-primary-100 backdrop-blur-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <UIcon name="i-heroicons-envelope" class="w-5 h-5 text-white" />
                            </div>
                            <span class="font-bold text-neutral-900 leading-none">Butuh Bantuan Lebih?</span>
                        </div>
                        <p class="text-sm text-neutral-600 font-medium">
                            Tim layanan pelanggan kami siap membantu Anda 24/7 untuk segala kendala.
                        </p>
                        <UButton color="primary" size="lg" block class="rounded-xl font-bold py-3 shadow-lg shadow-primary-500/20 group">
                            Hubungi Layanan Pelanggan Kami
                            <template #trailing>
                                <UIcon name="i-heroicons-arrow-small-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </template>
                        </UButton>
                    </div>
                </div>

                <div class="lg:w-2/3 w-full">
                    <UAccordion :items="faqs" multiple class="border-y border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-800" :ui="{
                        item: {
                            base: 'transition-all duration-500 bg-transparent',
                            active: 'bg-transparent',
                            inactive: 'bg-transparent'
                        }
                    }">
                        <template #default="{ item, open }">
                            <UButton color="neutral" variant="ghost" class="w-full text-left justify-between py-5 px-2 sm:px-4 group hover:bg-transparent dark:hover:bg-transparent focus:bg-transparent dark:focus:bg-transparent active:bg-transparent dark:active:bg-transparent focus-visible:bg-transparent dark:focus-visible:bg-transparent transition-all rounded-none">
                                <div class="flex items-center gap-5">
                                    <div :class="['w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-500 shrink-0 z-10 relative', open ? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/40 rotate-12 scale-110' : 'bg-primary-50 text-primary-600 group-hover:bg-primary-100 group-hover:scale-110 group-hover:-rotate-3']">
                                        <UIcon name="i-heroicons-question-mark-circle" class="w-6 h-6" />
                                    </div>
                                    <span :class="['text-base sm:text-lg font-bold transition-all duration-500 leading-tight group-hover:translate-x-1', open ? 'text-primary-600' : 'text-neutral-800 group-hover:text-primary-600']">
                                        {{ item.label }}
                                    </span>
                                </div>

                                <template #trailing>
                                    <div :class="['w-8 h-8 rounded-full flex items-center justify-center transition-all duration-500 shrink-0', open ? 'bg-primary-50 text-primary-600 rotate-180' : 'bg-transparent text-neutral-300 group-hover:text-primary-500 group-hover:bg-primary-50']">
                                        <UIcon name="i-heroicons-chevron-down" class="w-5 h-5" />
                                    </div>
                                </template>
                             </UButton>
                        </template>

                        <template #body="{ item }">
                            <div class="px-2 sm:px-4 pb-6 pt-0 ml-[1.25rem]">
                                <div class="pl-6 border-l-2 border-primary-100 dark:border-primary-900/50">
                                    <p class="font-medium text-sm leading-relaxed" style="color: #404040 !important;">
                                        {{ item.content }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </UAccordion>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>

<style>
/* Custom Animations */
@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fade-in-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(2deg); }
}
.animate-float {
    animation: float 6s ease-in-out infinite;
}

@keyframes float-delayed {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(20px) rotate(-2deg); }
}
.animate-float-delayed {
    animation: float-delayed 8s ease-in-out infinite;
}

.delay-200 { animation-delay: 200ms; }
.delay-300 { animation-delay: 300ms; }

.animate-bounce-slow {
    animation: bounce 3s infinite;
}

.map-canvas {
    background: linear-gradient(180deg, #dbeafe 0%, #eff6ff 100%);
}

/* User pin */
.vk-user-pin-wrapper { background: transparent; border: none; }
.vk-user-pin { width: 40px; height: 40px; position: relative; display: flex; align-items: center; justify-content: center; }
.vk-user-pin__pulse { position: absolute; inset: 0; border-radius: 9999px; background: rgba(14,165,233,0.25); animation: vk-pulse 2s ease-out infinite; }
.vk-user-pin__dot { width: 16px; height: 16px; border-radius: 9999px; background: #0ea5e9; border: 3px solid white; box-shadow: 0 2px 8px rgba(14,165,233,0.5); position: relative; z-index: 1; }
@keyframes vk-pulse { 0% { transform: scale(0.6); opacity: 0.8; } 70% { transform: scale(1.8); opacity: 0; } 100% { transform: scale(0.6); opacity: 0; } }

/* Property pin */
.vk-pin-wrapper { background: transparent; border: none; }
.vk-pin { width: 28px; height: 38px; position: relative; filter: drop-shadow(0 4px 10px rgba(2,132,199,0.4)); transition: transform 0.15s ease, filter 0.15s ease; cursor: pointer; }
.vk-pin:hover { transform: scale(1.15) translateY(-2px); filter: drop-shadow(0 8px 16px rgba(2,132,199,0.5)); }
.vk-pin::before { content: ''; position: absolute; inset: 0; background: linear-gradient(160deg, #38bdf8 0%, #0284c7 100%); border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%; clip-path: polygon(50% 100%, 0% 30%, 7% 10%, 20% 2%, 50% 0%, 80% 2%, 93% 10%, 100% 30%); }
.vk-pin__inner { position: absolute; width: 9px; height: 9px; background: white; border-radius: 9999px; top: 8px; left: 50%; transform: translateX(-50%); opacity: 0.9; }
.vk-pin--featured::before { background: linear-gradient(160deg, #fbbf24 0%, #d97706 100%); }
.vk-pin--featured { filter: drop-shadow(0 4px 10px rgba(217,119,6,0.45)); }
.vk-pin--featured:hover { filter: drop-shadow(0 8px 16px rgba(217,119,6,0.6)); }

/* Popup */
.vk-popup-container .leaflet-popup-content-wrapper { padding: 0; border-radius: 20px; overflow: hidden; box-shadow: none; }
.vk-popup-container .leaflet-popup-content { margin: 0; width: 240px !important; }
.vk-popup-container .leaflet-popup-tip-container { margin-top: -1px; }

.vk-popup {
    width: 240px;
    overflow: hidden;
    border-radius: 20px;
    background: white;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
}

.vk-popup__image {
    width: 100%;
    height: 120px;
    object-fit: cover;
    display: block;
}

.vk-popup__body {
    padding: 14px;
}

.vk-popup__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}

.vk-popup__badge {
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
    background: #e2e8f0;
}

.vk-popup__badge--featured {
    background: #fef3c7;
    color: #92400e;
}

.vk-popup__badge--soft {
    background: #dbeafe;
    color: #075985;
}

.vk-popup__title {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
}

.vk-popup__address {
    font-size: 12px;
    color: #64748b;
    margin: 0 0 10px 0;
}

.vk-popup__price {
    font-size: 15px;
    font-weight: 800;
    color: #0284c7;
    margin-bottom: 10px;
}

.vk-popup__price span {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.vk-popup__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    border-radius: 9999px;
    background: #0284c7;
    color: white !important;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    padding: 9px 12px;
    transition: background 0.2s;
}

.vk-popup__link:hover {
    background: #0369a1;
}

.faq-accordion button,
.faq-accordion span,
.faq-accordion p,
.faq-accordion div,
.faq-accordion .i-heroicons-chevron-down {
    color: black !important;
}

.faq-accordion button {
    border-bottom: 1px solid #f5f5f5 !important;
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
    font-weight: 600 !important;
    justify-content: center !important;
}

.faq-accordion button span {
    width: 100% !important;
    text-align: center !important;
}

.faq-accordion > div > div > div {
    padding-top: 0.5rem !important;
    padding-bottom: 1rem !important;
    text-align: center !important;
}
</style>
