<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { z } from 'zod';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    property: {
        type: Object,
        default: null,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    availableFacilities: {
        type: Array,
        default: () => [],
    },
    availablePropertyTypes: {
        type: Array,
        default: () => [],
    },
});

const isEdit = computed(() => props.mode === 'edit');

const genderItems = [
    { label: 'Putra', value: 'putra' },
    { label: 'Putri', value: 'putri' },
    { label: 'Campur', value: 'campur' },
];

const typeItems = computed(() => {
    return props.availablePropertyTypes.map((type) => ({
        label: type.name,
        value: type.id,
    }));
});

const priceTypeItems = [
    { label: 'Harian', value: 'daily' },
    { label: 'Bulanan', value: 'monthly' },
    { label: 'Tahunan', value: 'yearly' },
];

const statusItems = [
    { label: 'Aktif', value: 'active' },
    { label: 'Nonaktif', value: 'inactive' },
];

const form = useForm({
    id: props.property?.id ?? null,
    name: props.property?.name ?? '',
    property_type_id: props.property?.property_type_id ?? (props.availablePropertyTypes.length > 0 ? props.availablePropertyTypes[0].id : null),
    gender_restriction: props.property?.gender_restriction ?? 'campur',
    description: props.property?.description ?? '',
    price: props.property?.price ?? '',
    price_type: props.property?.price_type ?? 'monthly',
    address: props.property?.address ?? '',
    latitude: props.property?.latitude ?? '',
    longitude: props.property?.longitude ?? '',
    total_rooms: props.property?.total_rooms ?? 1,
    available_rooms: props.property?.available_rooms ?? 1,
    status: props.property?.status ?? 'active',
    tagsString: props.property?.tags?.map((tag) => tag.name).join(', ') ?? '',
    facility_ids: props.property?.facilities?.map((facility) => facility.id) ?? [],
    thumbnail: null,
    detail_photos: [],
    new_thumbnail: null,
    new_detail_photos: [],
    existing_photos: props.property?.images ?? [],
});

const existingThumbnail = computed(() => {
    return form.existing_photos.find((photo) => photo.is_primary) || null;
});

const existingDetailPhotos = computed(() => {
    return form.existing_photos.filter((photo) => !photo.is_primary);
});

const thumbnailModel = computed({
    get() {
        return isEdit.value ? form.new_thumbnail : form.thumbnail;
    },
    set(value) {
        if (isEdit.value) {
            form.new_thumbnail = value;
            clearError('new_thumbnail');
            return;
        }

        form.thumbnail = value;
        clearError('thumbnail');
    },
});

const detailPhotosModel = computed({
    get() {
        return isEdit.value ? form.new_detail_photos : form.detail_photos;
    },
    set(value) {
        if (isEdit.value) {
            form.new_detail_photos = value || [];
            clearError('new_detail_photos');
            return;
        }

        form.detail_photos = value || [];
        clearError('detail_photos');
    },
});

const schema = z.object({
    name: z.string().min(1, 'Nama properti wajib diisi'),
    property_type_id: z.coerce.number().min(1, 'Pilih tipe properti'),
    gender_restriction: z.enum(['putra', 'putri', 'campur'], { required_error: 'Pilih gender penghuni' }),
    description: z.string().min(1, 'Deskripsi wajib diisi'),
    price: z.coerce.number().min(0, 'Harga tidak valid'),
    price_type: z.enum(['daily', 'monthly', 'yearly'], { required_error: 'Pilih tipe harga' }),
    address: z.string().min(1, 'Alamat wajib diisi'),
    total_rooms: z.coerce.number().int().min(1, 'Total kamar minimal 1'),
    available_rooms: z.coerce.number().int().min(0, 'Kamar tersedia tidak valid'),
    status: z.enum(['active', 'inactive'], { required_error: 'Pilih status properti' }),
});

const validate = () => {
    return Object.entries(form.errors).map(([name, message]) => ({ name, message }));
};

const clearError = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};

const tagHint = computed(() => {
    if (props.availableTags.length > 0) {
        return `Contoh tag: ${props.availableTags.slice(0, 5).join(', ')}`;
    }

    return 'Pisahkan dengan koma. Contoh: AC, WiFi, Dekat Kampus';
});

const facilityItems = computed(() => {
    return props.availableFacilities.map((facility) => ({
        label: facility.name,
        value: facility.id,
        icon: facility.icon || undefined,
    }));
});

const mapRef = ref(null);
let mapInstance = null;
let markerInstance = null;

const defaultCenter = [-7.2575, 112.7521];

const mapPinIcon = L.divIcon({
    className: 'vk-map-pin-wrapper',
    html: '<span class="vk-map-pin"></span>',
    iconSize: [26, 26],
    iconAnchor: [13, 26],
});

const upsertMarker = (lat, lng) => {
    if (!mapInstance) {
        return;
    }

    if (!markerInstance) {
        markerInstance = L.marker([lat, lng], { icon: mapPinIcon }).addTo(mapInstance);
    } else {
        markerInstance.setLatLng([lat, lng]);
    }
};

const setupMap = () => {
    if (!mapRef.value || mapInstance) {
        return;
    }

    mapInstance = L.map(mapRef.value, {
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(mapInstance);

    mapInstance.setView(defaultCenter, 13);

    const hasCoordinates = form.latitude !== '' && form.longitude !== '';

    if (hasCoordinates) {
        const point = [Number(form.latitude), Number(form.longitude)];
        upsertMarker(point[0], point[1]);
        mapInstance.setView(point, 16);
    } else if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            if (mapInstance) {
                mapInstance.setView([position.coords.latitude, position.coords.longitude], 15);
            }
        });
    }

    mapInstance.on('click', (event) => {
        const { lat, lng } = event.latlng;

        form.latitude = Number(lat.toFixed(8));
        form.longitude = Number(lng.toFixed(8));
        clearError('latitude');
        clearError('longitude');

        upsertMarker(lat, lng);
    });
};

const clearPin = () => {
    form.latitude = '';
    form.longitude = '';

    if (markerInstance && mapInstance) {
        mapInstance.removeLayer(markerInstance);
        markerInstance = null;
        mapInstance.setView(defaultCenter, 13);
    }
};

const destroyMap = () => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    markerInstance = null;
};

onMounted(async () => {
    await nextTick();
    setupMap();
});

onBeforeUnmount(() => {
    destroyMap();
});

const handleDeleteImage = (img) => {
    if (!form.id) {
        return;
    }

    if (confirm('Yakin ingin menghapus foto ini?')) {
        router.delete(`/properties/${form.id}/photos/${img.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.existing_photos = form.existing_photos.filter((photo) => photo.id !== img.id);
            },
        });
    }
};

const submit = () => {
    const tagsArray = form.tagsString
        ? form.tagsString.split(',').map((tag) => tag.trim()).filter((tag) => tag !== '')
        : [];

    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            tags: tagsArray,
            _method: 'PUT',
        })).post(`/properties/${form.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }

    form.transform((data) => ({
        ...data,
        tags: tagsArray,
    })).post('/properties', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<style>
.vk-map-pin-wrapper {
    background: transparent;
    border: none;
}

.vk-map-pin {
    display: block;
    width: 26px;
    height: 26px;
    border-radius: 9999px;
    border: 3px solid #0ea5e9;
    background: #ffffff;
    box-shadow: 0 6px 12px rgba(2, 132, 199, 0.35);
    position: relative;
}

.vk-map-pin::after {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: #0ea5e9;
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}
</style>

<template>
    <UForm
        :schema="schema"
        :validate="validate"
        :state="form"
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <UCard>
                <template #header>
                    <h2 class="text-lg font-semibold">Data Properti</h2>
                </template>

                <div class="space-y-4">
                    <UFormField label="Nama Properti" name="name" required>
                        <UInput
                            v-model="form.name"
                            placeholder="Contoh: Kos Amanah Jaya"
                            class="w-full"
                            @update:model-value="clearError('name')"
                        />
                    </UFormField>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <UFormField label="Tipe Properti" name="property_type_id" required>
                            <USelect
                                v-model="form.property_type_id"
                                :items="typeItems"
                                class="w-full"
                                @update:model-value="clearError('property_type_id')"
                            />
                        </UFormField>

                        <UFormField label="Gender Penghuni" name="gender_restriction" required>
                            <USelect
                                v-model="form.gender_restriction"
                                :items="genderItems"
                                class="w-full"
                                @update:model-value="clearError('gender_restriction')"
                            />
                        </UFormField>
                    </div>

                    <UFormField label="Deskripsi" name="description" required>
                        <UTextarea
                            v-model="form.description"
                            placeholder="Jelaskan keunggulan properti Anda"
                            :rows="4"
                            class="w-full"
                            @update:model-value="clearError('description')"
                        />
                    </UFormField>

                    <UFormField label="Alamat" name="address" required>
                        <UTextarea
                            v-model="form.address"
                            placeholder="Masukkan alamat lengkap"
                            :rows="3"
                            class="w-full"
                            @update:model-value="clearError('address')"
                        />
                    </UFormField>

                    <UFormField label="Fasilitas" name="facility_ids" description="Bisa pilih lebih dari satu fasilitas dari data master.">
                        <USelect
                            v-model="form.facility_ids"
                            :items="facilityItems"
                            value-key="value"
                            label-key="label"
                            multiple
                            placeholder="Pilih fasilitas"
                            class="w-full"
                            @update:model-value="clearError('facility_ids')"
                        />
                    </UFormField>

                    <UFormField label="Tag (Opsional)" name="tagsString" :description="tagHint">
                        <UInput
                            v-model="form.tagsString"
                            placeholder="AC, WiFi, Kamar Mandi Dalam"
                            class="w-full"
                        />
                    </UFormField>
                </div>
            </UCard>

            <div class="space-y-6">
                <UCard>
                    <template #header>
                        <h2 class="text-lg font-semibold">Harga dan Ketersediaan</h2>
                    </template>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <UFormField label="Harga" name="price" required>
                                <UInput
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    placeholder="Contoh: 1200000"
                                    class="w-full"
                                    @update:model-value="clearError('price')"
                                />
                            </UFormField>

                            <UFormField label="Tipe Harga" name="price_type" required>
                                <USelect
                                    v-model="form.price_type"
                                    :items="priceTypeItems"
                                    class="w-full"
                                    @update:model-value="clearError('price_type')"
                                />
                            </UFormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <UFormField label="Total Kamar" name="total_rooms" required>
                                <UInput
                                    v-model="form.total_rooms"
                                    type="number"
                                    min="1"
                                    class="w-full"
                                    @update:model-value="clearError('total_rooms')"
                                />
                            </UFormField>

                            <UFormField label="Kamar Tersedia" name="available_rooms" required>
                                <UInput
                                    v-model="form.available_rooms"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                    @update:model-value="clearError('available_rooms')"
                                />
                            </UFormField>
                        </div>

                        <UFormField label="Status" name="status" required>
                            <USelect
                                v-model="form.status"
                                :items="statusItems"
                                class="w-full"
                                @update:model-value="clearError('status')"
                            />
                        </UFormField>
                    </div>
                </UCard>

                <UCard>
                    <template #header>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-semibold">Pin Lokasi di Peta</h2>
                            <UButton
                                type="button"
                                color="neutral"
                                variant="soft"
                                size="sm"
                                @click="clearPin"
                            >
                                Reset Pin
                            </UButton>
                        </div>
                    </template>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        Klik peta untuk menentukan titik lokasi properti.
                    </p>

                    <div
                        ref="mapRef"
                        class="h-72 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                    />

                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="form.latitude !== '' && form.longitude !== ''">
                            Titik tersimpan: {{ form.latitude }}, {{ form.longitude }}
                        </span>
                        <span v-else>
                            Belum ada titik dipilih.
                        </span>
                    </div>
                </UCard>
            </div>
        </div>

        <UCard>
            <template #header>
                <h2 class="text-lg font-semibold">Foto Properti</h2>
            </template>

            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Thumbnail</label>

                    <div v-if="isEdit && existingThumbnail" class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs text-gray-500 mb-2">Thumbnail tersimpan saat ini</p>
                        <img :src="`/storage/${existingThumbnail.image_url}`" class="w-full max-w-xs h-40 object-cover rounded-lg border dark:border-gray-700" alt="Thumbnail properti" />
                    </div>

                    <UFileUpload
                        v-model="thumbnailModel"
                        accept="image/*"
                        :multiple="false"
                        :dropzone="true"
                        :interactive="true"
                        :preview="true"
                        layout="list"
                        label="Drop thumbnail di sini"
                        description="Atau klik untuk pilih satu file gambar"
                        class="w-full"
                        @change="clearError(isEdit ? 'new_thumbnail' : 'thumbnail')"
                    />
                </div>

                <div v-if="isEdit && existingDetailPhotos.length > 0" class="space-y-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Foto Detail Tersimpan</label>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="img in existingDetailPhotos" :key="img.id" class="relative group">
                            <img :src="`/storage/${img.image_url}`" class="w-20 h-20 object-cover rounded border dark:border-gray-700" alt="Foto properti" />
                            <UButton
                                type="button"
                                icon="i-heroicons-x-mark"
                                size="xs"
                                color="error"
                                variant="solid"
                                class="absolute -top-2 -right-2 !rounded-full opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity"
                                square
                                @click="handleDeleteImage(img)"
                            />
                        </div>
                    </div>
                </div>

                <UFormField
                    :label="isEdit ? 'Tambah Foto Detail Baru' : 'Upload Foto Detail (Opsional, bisa lebih dari satu)'"
                    :name="isEdit ? 'new_detail_photos' : 'detail_photos'"
                >
                    <UFileUpload
                        v-model="detailPhotosModel"
                        accept="image/*"
                        multiple
                        :dropzone="true"
                        :interactive="true"
                        :preview="true"
                        layout="grid"
                        label="Drop foto detail di sini"
                        description="Bisa pilih banyak foto sekaligus"
                        class="w-full"
                        @change="clearError(isEdit ? 'new_detail_photos' : 'detail_photos')"
                    />
                </UFormField>
            </div>
        </UCard>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <UButton
                type="button"
                color="neutral"
                variant="soft"
                @click="router.get('/properties')"
            >
                Batal
            </UButton>
            <UButton
                type="button"
                color="primary"
                :loading="form.processing"
                @click="submit"
            >
                {{ isEdit ? 'Simpan Perubahan' : 'Simpan Properti' }}
            </UButton>
        </div>
    </UForm>
</template>
