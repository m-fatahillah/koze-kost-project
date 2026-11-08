<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import 'leaflet/dist/leaflet.css';
import FloatingChatWidget from '@/Components/FloatingChatWidget.vue';

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

const mapRef = ref(null);
const mapLoaded = ref(false);
const mapLoading = ref(true);
const locationStatus = ref('Meminta akses lokasi...');
const markerCount = ref(0);
const selectedProperty = ref(null);
const currentCenter = ref({ lat: -6.2, lng: 106.816666 });
const userLocation = ref(null);
const visibleProperties = ref([]);
const radiusFilter = ref(null);
const radiusInput = ref('');
const radiusDropdownOpen = ref(false);
const RADIUS_PRESETS = [1, 2, 5, 10, 20];

// Filter state
const filterOpen = ref(false);
const filterGender = ref([]); // ['putra','putri','campur']
const filterPriceType = ref([]); // ['monthly','yearly','daily']
const filterPriceMin = ref('');
const filterPriceMax = ref('');
const filterAvailableOnly = ref(false);
const filterFacilities = ref([]); // array of facility ids

// Facilities list dari server
const facilitiesList = ref([]);

const activeFilterCount = computed(() => {
    let n = 0;
    if (filterGender.value.length) n++;
    if (filterPriceType.value.length) n++;
    if (filterPriceMin.value || filterPriceMax.value) n++;
    if (filterAvailableOnly.value) n++;
    if (filterFacilities.value.length) n++;
    return n;
});

const loadFacilities = async () => {
    try {
        const res = await fetch('/facilities/list', { headers: { Accept: 'application/json' } });
        facilitiesList.value = await res.json();
    } catch {}
};

const resetFilters = () => {
    filterGender.value = [];
    filterPriceType.value = [];
    filterPriceMin.value = '';
    filterPriceMax.value = '';
    filterAvailableOnly.value = false;
    filterFacilities.value = [];
    fetchMarkers();
};

let map = null;
let markerLayer = null;
let userMarker = null;
let radiusCircle = null;
let fetchTimer = null;
let radiusDebounceTimer = null;
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

const openGoogleMapsRoute = (property) => {
    const dest = `${property.latitude},${property.longitude}`;
    const origin = userLocation.value ? `${userLocation.value.lat},${userLocation.value.lng}` : '';
    const url = origin
        ? `https://www.google.com/maps/dir/${origin}/${dest}`
        : `https://www.google.com/maps/search/?api=1&query=${dest}`;
    window.open(url, '_blank');
};

const filteredProperties = () => {
    if (!radiusFilter.value || !userLocation.value) return visibleProperties.value;
    return visibleProperties.value.filter((p) => {
        const d = distanceKm(userLocation.value, { lat: p.latitude, lng: p.longitude });
        return d !== null && d <= radiusFilter.value;
    });
};

const updateRadiusCircle = async () => {
    const L = await getLeaflet();
    if (radiusCircle) {
        radiusCircle.remove();
        radiusCircle = null;
    }
    if (radiusFilter.value && userLocation.value && map) {
        radiusCircle = L.circle(
            [userLocation.value.lat, userLocation.value.lng],
            {
                radius: radiusFilter.value * 1000,
                color: '#0284c7',
                fillColor: '#38bdf8',
                fillOpacity: 0.08,
                weight: 2,
                dashArray: '6 4',
            }
        ).addTo(map);
    }
};

const applyRadius = (closeDropdown = true) => {
    const parsed = parseFloat(radiusInput.value);
    radiusFilter.value = (!radiusInput.value || isNaN(parsed) || parsed <= 0) ? null : parsed;
    if (closeDropdown) radiusDropdownOpen.value = false;
    updateRadiusCircle();
    fetchMarkers();
};

const applyRadiusDebounced = () => {
    clearTimeout(radiusDebounceTimer);
    radiusDebounceTimer = setTimeout(() => applyRadius(false), 600);
};

const selectPreset = (km) => {
    radiusInput.value = String(km);
    radiusFilter.value = km;
    radiusDropdownOpen.value = false;
    updateRadiusCircle();
    fetchMarkers();
};

const clearRadius = () => {
    radiusInput.value = '';
    radiusFilter.value = null;
    radiusDropdownOpen.value = false;
    updateRadiusCircle();
    fetchMarkers();
};

watch(userLocation, () => {
    updateRadiusCircle();
});

// Auto-apply saat toggle gender/price_type/available berubah
watch([filterGender, filterPriceType, filterAvailableOnly, filterFacilities], () => {
    scheduleFetchMarkers();
}, { deep: true });

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

    if (filterGender.value.length) params.set('gender', filterGender.value.join(','));
    if (filterPriceType.value.length) params.set('price_type', filterPriceType.value.join(','));
    if (filterPriceMin.value) params.set('price_min', filterPriceMin.value);
    if (filterPriceMax.value) params.set('price_max', filterPriceMax.value);
    if (filterAvailableOnly.value) params.set('available_only', '1');
    if (filterFacilities.value.length) params.set('facilities', filterFacilities.value.join(','));

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
        const toRender = filteredProperties();
        renderMarkers(L, toRender);

        if (!selectedProperty.value && toRender.length > 0) {
            selectedProperty.value = enrichProperty(toRender[0]);
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

onMounted(async () => {
    await nextTick();
    await Promise.all([initMap(), loadFacilities()]);
});

onBeforeUnmount(() => {
    clearTimeout(fetchTimer);
    clearTimeout(radiusDebounceTimer);

    if (requestAbortController) {
        requestAbortController.abort();
    }

    if (radiusCircle) {
        radiusCircle.remove();
        radiusCircle = null;
    }

    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <Head title="Peta Properti - Koze" />

    <!-- Fixed navbar — above Leaflet's z-index ceiling (1000) -->
    <header class="fixed top-4 inset-x-4 z-[1001] pointer-events-none">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Left pill -->
            <div class="pointer-events-auto bg-white/95 backdrop-blur-md px-4 py-2 rounded-2xl shadow-lg border border-slate-100 flex items-center gap-4">
                <Link href="/" class="flex items-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center text-primary-600 group-hover:bg-primary-600 group-hover:text-white transition-colors">
                        <UIcon name="i-heroicons-arrow-left" class="w-5 h-5" />
                    </div>
                    <span class="font-bold text-slate-900 pr-2">Kembali</span>
                </Link>
                <div class="w-px h-6 bg-slate-200"></div>
                <div class="flex flex-col pr-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Eksplorasi</span>
                    <span class="font-bold text-slate-900 leading-tight">Peta Properti</span>
                </div>
            </div>

            <!-- Right pill -->
            <div class="pointer-events-auto bg-white/95 backdrop-blur-md px-4 py-3 rounded-2xl shadow-lg border border-slate-100 flex items-center gap-3">
                <UBadge color="primary" variant="subtle" class="rounded-full px-3 py-1 font-bold">
                    {{ markerCount }} Properti
                </UBadge>

                <!-- Radius filter -->
                <div v-if="userLocation" class="relative">
                    <button
                        class="flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="radiusFilter
                            ? 'bg-primary-50 border-primary-300 text-primary-700'
                            : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                        @click="radiusDropdownOpen = !radiusDropdownOpen"
                    >
                        <UIcon name="i-heroicons-viewfinder-circle" class="w-4 h-4" />
                        <span>{{ radiusFilter ? `${radiusFilter} km` : 'Radius' }}</span>
                        <UIcon
                            name="i-heroicons-chevron-down"
                            class="w-3.5 h-3.5 transition-transform"
                            :class="radiusDropdownOpen ? 'rotate-180' : ''"
                        />
                    </button>

                    <div
                        v-if="radiusDropdownOpen"
                        class="absolute top-full right-0 mt-2 w-56 rounded-2xl bg-white shadow-2xl border border-slate-100 p-3 z-10 flex flex-col gap-2"
                    >
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="km in RADIUS_PRESETS"
                                :key="km"
                                class="px-3 py-1 rounded-full text-xs font-bold border transition-colors"
                                :class="radiusFilter === km
                                    ? 'bg-primary-500 text-white border-primary-500'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-primary-50 hover:border-primary-300 hover:text-primary-600'"
                                @click="selectPreset(km)"
                            >{{ km }} km</button>
                        </div>
                        <div class="h-px bg-slate-100"></div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Jarak custom</p>
                            <div class="flex items-center gap-1 rounded-lg bg-slate-50 border border-slate-200 px-2 py-1.5 focus-within:border-primary-400 focus-within:ring-2 focus-within:ring-primary-100 transition">
                                <input
                                    v-model="radiusInput"
                                    type="number"
                                    min="0.1"
                                    step="0.5"
                                    placeholder="Contoh: 7.5"
                                    class="flex-1 bg-transparent text-xs font-semibold text-slate-800 outline-none placeholder:text-slate-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    @input="applyRadiusDebounced"
                                    @keydown.enter="applyRadius()"
                                />
                                <span class="text-[10px] font-bold text-slate-400">km</span>
                            </div>
                        </div>
                        <button
                            v-if="radiusFilter"
                            class="flex items-center justify-center gap-1 text-xs text-slate-400 hover:text-red-500 font-medium transition-colors pt-0.5"
                            @click="clearRadius"
                        >
                            <UIcon name="i-heroicons-x-circle" class="w-3.5 h-3.5" />
                            Hapus filter radius
                        </button>
                    </div>

                    <div
                        v-if="radiusDropdownOpen"
                        class="fixed inset-0 z-[9]"
                        @click="radiusDropdownOpen = false"
                    ></div>
                </div>

                <!-- Filter button -->
                <button
                    class="flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="activeFilterCount
                        ? 'bg-primary-50 border-primary-300 text-primary-700'
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                    @click="filterOpen = !filterOpen"
                >
                    <UIcon name="i-heroicons-adjustments-horizontal" class="w-4 h-4" />
                    Filter
                    <span v-if="activeFilterCount" class="w-4 h-4 rounded-full bg-primary-500 text-white text-[10px] font-bold flex items-center justify-center">{{ activeFilterCount }}</span>
                </button>

                <UButton color="neutral" variant="soft" size="sm" class="rounded-xl font-semibold" :loading="mapLoading" @click="fetchMarkers">
                    Muat Ulang Area
                </UButton>
            </div>
        </div>

        <!-- Filter panel -->
        <div v-if="filterOpen" class="pointer-events-auto absolute top-[72px] right-4 z-[1002] w-80 rounded-2xl bg-white shadow-2xl border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <span class="font-bold text-slate-800 text-sm">Filter Properti</span>
                <div class="flex items-center gap-2">
                    <button v-if="activeFilterCount" class="text-xs text-red-400 hover:text-red-600 font-semibold" @click="resetFilters">Reset</button>
                    <button class="text-slate-400 hover:text-slate-600" @click="filterOpen = false">
                        <UIcon name="i-heroicons-x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </div>
            <div class="p-4 flex flex-col gap-4 max-h-[calc(100vh-120px)] overflow-y-auto">
                <!-- Gender -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Tipe Penghuni</p>
                    <div class="flex gap-2">
                        <button v-for="g in [['putra','Putra','🧑'],['putri','Putri','👩'],['campur','Campur','👥']]" :key="g[0]"
                            class="flex-1 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="filterGender.includes(g[0]) ? 'bg-primary-500 text-white border-primary-500' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-primary-300'"
                            @click="filterGender.includes(g[0]) ? filterGender.splice(filterGender.indexOf(g[0]),1) : filterGender.push(g[0])"
                        >{{ g[2] }} {{ g[1] }}</button>
                    </div>
                </div>
                <!-- Price type -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Tipe Harga</p>
                    <div class="flex gap-2">
                        <button v-for="pt in [['monthly','/ Bulan'],['yearly','/ Tahun'],['daily','/ Hari']]" :key="pt[0]"
                            class="flex-1 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="filterPriceType.includes(pt[0]) ? 'bg-primary-500 text-white border-primary-500' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-primary-300'"
                            @click="filterPriceType.includes(pt[0]) ? filterPriceType.splice(filterPriceType.indexOf(pt[0]),1) : filterPriceType.push(pt[0])"
                        >{{ pt[1] }}</button>
                    </div>
                </div>
                <!-- Price range -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Range Harga (Rp)</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center gap-1 rounded-lg bg-slate-50 border border-slate-200 px-2 py-1.5 focus-within:border-primary-400 focus-within:ring-2 focus-within:ring-primary-100 transition">
                            <input v-model="filterPriceMin" type="number" min="0" placeholder="Min"
                                class="w-full bg-transparent text-xs font-semibold text-slate-800 outline-none placeholder:text-slate-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                        </div>
                        <span class="text-slate-300 text-xs">—</span>
                        <div class="flex-1 flex items-center gap-1 rounded-lg bg-slate-50 border border-slate-200 px-2 py-1.5 focus-within:border-primary-400 focus-within:ring-2 focus-within:ring-primary-100 transition">
                            <input v-model="filterPriceMax" type="number" min="0" placeholder="Max"
                                class="w-full bg-transparent text-xs font-semibold text-slate-800 outline-none placeholder:text-slate-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                        </div>
                    </div>
                </div>
                <!-- Available only -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Hanya yang Tersedia</p>
                        <p class="text-[11px] text-slate-400">Tampilkan kos dengan kamar kosong</p>
                    </div>
                    <button
                        class="w-11 h-6 rounded-full transition-colors relative shrink-0"
                        :class="filterAvailableOnly ? 'bg-primary-500' : 'bg-slate-200'"
                        @click="filterAvailableOnly = !filterAvailableOnly"
                    >
                        <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all"
                            :class="filterAvailableOnly ? 'left-[calc(100%-1.375rem)]' : 'left-0.5'" />
                    </button>
                </div>
                <!-- Facilities -->
                <div v-if="facilitiesList.length">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Fasilitas</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="f in facilitiesList" :key="f.id"
                            class="px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors"
                            :class="filterFacilities.includes(f.id) ? 'bg-primary-500 text-white border-primary-500' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-primary-300 hover:text-primary-600'"
                            @click="filterFacilities.includes(f.id) ? filterFacilities.splice(filterFacilities.indexOf(f.id),1) : filterFacilities.push(f.id)"
                        >{{ f.name }}</button>
                    </div>
                </div>
                <!-- Apply -->
                <button
                    class="w-full py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-bold transition-colors"
                    @click="fetchMarkers(); filterOpen = false"
                >Terapkan Filter</button>
            </div>
        </div>

        <!-- Filter backdrop -->
        <div v-if="filterOpen" class="fixed inset-0 z-[1000]" @click="filterOpen = false"></div>
    </header>

    <!-- Full-screen map -->
    <div class="h-screen w-full bg-slate-50">
        <div ref="mapRef" class="h-full w-full map-canvas"></div>

        <!-- Property Card Overlay -->
        <div v-if="selectedProperty" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[1001] w-[min(90%,24rem)] transition-all duration-300">
            <div class="rounded-3xl border border-white/80 bg-white/95 p-4 shadow-2xl backdrop-blur-xl relative overflow-hidden group">
                <UButton icon="i-heroicons-x-mark" color="neutral" variant="ghost" class="absolute top-2 right-2 z-10 w-8 h-8 rounded-full" @click="selectedProperty = null" />
                <div class="flex gap-4">
                    <img
                        :src="selectedProperty.thumbnail_url || 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?q=80&w=900&auto=format&fit=crop'"
                        class="h-28 w-24 rounded-2xl object-cover shadow-md group-hover:scale-105 transition-transform duration-500"
                        :alt="selectedProperty.name"
                    />
                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                        <div class="flex items-center gap-2 mb-1">
                            <UBadge :color="selectedProperty.is_featured ? 'warning' : 'primary'" variant="subtle" class="rounded-full px-2 py-0.5 text-[10px]">
                                {{ selectedProperty.is_featured ? 'Unggulan' : titleCase(selectedProperty.type) }}
                            </UBadge>
                            <UBadge color="green" variant="subtle" class="rounded-full px-2 py-0.5 text-[10px]">
                                {{ labelGender(selectedProperty.gender_restriction) }}
                            </UBadge>
                        </div>
                        <h3 class="truncate text-base font-bold text-slate-900 mb-0.5">{{ selectedProperty.name }}</h3>
                        <p class="truncate text-xs text-slate-500 flex items-center gap-1 mb-1">
                            <UIcon name="i-heroicons-map-pin" class="w-3 h-3" />
                            {{ selectedProperty.address }}
                        </p>
                        <p v-if="selectedProperty.distance !== null" class="text-xs text-primary-500 font-semibold mb-2 flex items-center gap-1">
                            <UIcon name="i-heroicons-arrows-pointing-out" class="w-3 h-3" />
                            {{ selectedProperty.distance }} km dari lokasimu
                        </p>
                        <div class="flex items-end justify-between mt-auto gap-2">
                            <div class="text-sm font-extrabold text-primary-600">
                                {{ formatRupiah(selectedProperty.price) }}
                                <span class="text-[10px] font-medium text-slate-400 block -mt-1">{{ labelPriceType(selectedProperty.price_type) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-600 shadow-md flex items-center gap-1"
                                    @click="openGoogleMapsRoute(selectedProperty)"
                                    title="Buka rute di Google Maps"
                                >
                                    <UIcon name="i-heroicons-map" class="w-3.5 h-3.5" />
                                    Rute
                                </button>
                                <Link :href="`/property/${selectedProperty.id}`" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-700 shadow-md">
                                    Detail
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="mapLoading" class="fixed inset-0 flex items-center justify-center bg-white/20 backdrop-blur-[1px] z-[300]">
            <div class="rounded-2xl bg-white/90 px-6 py-3 text-sm font-bold text-primary-600 shadow-2xl flex items-center gap-3">
                <UIcon name="i-heroicons-arrow-path" class="w-5 h-5 animate-spin" />
                Memuat marker area...
            </div>
        </div>

        <!-- Focus User Button -->
        <div class="fixed bottom-6 right-6 z-[1001] flex flex-col items-end gap-2">
            <button
                class="flex items-center gap-2 px-4 py-3 rounded-2xl bg-white text-slate-800 shadow-xl hover:scale-105 active:scale-95 transition-transform border border-slate-200 font-semibold text-sm"
                @click="focusOnUser"
            >
                <span
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="userLocation ? 'bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.25)]' : 'bg-slate-300'"
                ></span>
                <UIcon name="i-heroicons-map-pin-solid" class="w-4 h-4 text-primary-500" />
                Lokasi Saya
            </button>
        </div>
    </div>

    <FloatingChatWidget />
</template>

<style>
.map-canvas {
    background: linear-gradient(180deg, #dbeafe 0%, #eff6ff 100%);
}

/* User location pin */
.vk-user-pin-wrapper {
    background: transparent;
    border: none;
}

.vk-user-pin {
    width: 40px;
    height: 40px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vk-user-pin__pulse {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    background: rgba(14, 165, 233, 0.25);
    animation: vk-pulse 2s ease-out infinite;
}

.vk-user-pin__dot {
    width: 16px;
    height: 16px;
    border-radius: 9999px;
    background: #0ea5e9;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.5);
    position: relative;
    z-index: 1;
}

@keyframes vk-pulse {
    0%   { transform: scale(0.6); opacity: 0.8; }
    70%  { transform: scale(1.8); opacity: 0; }
    100% { transform: scale(0.6); opacity: 0; }
}

/* Property pin */
.vk-pin-wrapper {
    background: transparent;
    border: none;
}

.vk-pin {
    width: 28px;
    height: 38px;
    position: relative;
    filter: drop-shadow(0 4px 10px rgba(2, 132, 199, 0.4));
    transition: transform 0.15s ease, filter 0.15s ease;
    cursor: pointer;
}

.vk-pin:hover {
    transform: scale(1.15) translateY(-2px);
    filter: drop-shadow(0 8px 16px rgba(2, 132, 199, 0.5));
}

/* SVG-like shape via clip-path: teardrop */
.vk-pin::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(160deg, #38bdf8 0%, #0284c7 100%);
    border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
    clip-path: polygon(50% 100%, 0% 30%, 7% 10%, 20% 2%, 50% 0%, 80% 2%, 93% 10%, 100% 30%);
}

/* white dot center */
.vk-pin__inner {
    position: absolute;
    width: 9px;
    height: 9px;
    background: white;
    border-radius: 9999px;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0.9;
}

.vk-pin--featured::before {
    background: linear-gradient(160deg, #fbbf24 0%, #d97706 100%);
}

.vk-pin--featured {
    filter: drop-shadow(0 4px 10px rgba(217, 119, 6, 0.45));
}

.vk-pin--featured:hover {
    filter: drop-shadow(0 8px 16px rgba(217, 119, 6, 0.6));
}

.vk-popup-container .leaflet-popup-content-wrapper {
    padding: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: none;
}

.vk-popup-container .leaflet-popup-content {
    margin: 0;
    width: 260px !important;
}

.vk-popup-container .leaflet-popup-tip-container {
    margin-top: -1px;
}

.vk-popup {
    width: 260px;
    overflow: hidden;
    border-radius: 20px;
    background: white;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
}

.vk-popup__image {
    width: 100%;
    height: 140px;
    object-fit: cover;
    display: block;
}

.vk-popup__body {
    padding: 16px;
}

.vk-popup__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
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
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    line-height: 1.2;
}

.vk-popup__address {
    font-size: 12px;
    color: #64748b;
    margin: 0 0 12px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vk-popup__price {
    font-size: 16px;
    font-weight: 800;
    color: #0284c7;
    margin-bottom: 12px;
    line-height: 1;
}

.vk-popup__price span {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    display: block;
    margin-top: 2px;
}

.vk-popup__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 10px;
    background: white;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s;
    box-sizing: border-box;
}

.vk-popup__link:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    margin-bottom: 24px !important;
    margin-right: 24px !important;
}

.leaflet-control-zoom a {
    color: #0f172a !important;
    background: white !important;
    border: none !important;
    width: 40px !important;
    height: 40px !important;
    line-height: 40px !important;
    font-weight: bold !important;
    font-size: 18px !important;
}

.leaflet-control-zoom a:first-child {
    border-radius: 12px 12px 0 0 !important;
    border-bottom: 1px solid #f1f5f9 !important;
}

.leaflet-control-zoom a:last-child {
    border-radius: 0 0 12px 12px !important;
}
</style>
