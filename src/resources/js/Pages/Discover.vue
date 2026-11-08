<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import GlobalChatFloatingButton from '@/Components/chat/GlobalChatFloatingButton.vue'
import { useSavedProperties } from '@/composables/useSavedProperties'

const props = defineProps({
  properties: {
    type: Array,
    default: () => [],
  },
  sidebarAd: {
    type: Object,
    default: null,
  },
})

const page = usePage()

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

const userInitial = computed(() => {
  const userName = page.props.auth?.user?.name || 'Guest'
  return userName.charAt(0).toUpperCase()
})

const { savedCount, isSaved, toggleSaved } = useSavedProperties()

const properties = computed(() => {
  if (!Array.isArray(props.properties)) {
    return []
  }

  return props.properties.map((property, index) => {
    const title = property.title ?? 'Properti Tanpa Nama'
    const area = property.area ?? 'Lokasi belum diisi'
    const location = property.location ?? property.area ?? 'Lokasi belum diisi'
    const audience = ['Putri', 'Putra', 'Campur'].includes(property.audience) ? property.audience : 'Campur'
    const priceValue = Number(property.priceValue ?? 0)
    const rating = Number(property.rating ?? 0)
    const createdAt = property.createdAt ?? '1970-01-01'
    const facilities = Array.isArray(property.facilities) ? property.facilities : []
    const facilitiesDetailed = Array.isArray(property.facilitiesDetailed) ? property.facilitiesDetailed : []
    const cover = property.cover ?? 'linear-gradient(140deg, #b1b8c8 0%, #ddd1bb 45%, #f4efe7 100%)'
    const thumbnailUrl = property.thumbnailUrl ?? null
    const genderRestriction = property.genderRestriction ?? 'campur'
    const propertyType = property.propertyType ?? 'Kos'
    const isFeatured = Boolean(property.isFeatured)
    const priceType = property.priceType ?? 'monthly'
    const description = property.description ?? ''

    return {
      id: Number(property.id ?? index + 1),
      title,
      name: title,
      area,
      location,
      address: (location && area) ? `${location}, ${area}` : (location ?? area ?? 'Lokasi belum diisi'),
      audience,
      priceValue,
      price: priceValue,
      price_type: priceType,
      rating,
      createdAt,
      facilities,
      facilitiesDetailed,
      cover,
      thumbnail_url: thumbnailUrl,
      gender_restriction: genderRestriction,
      type: propertyType,
      is_featured: isFeatured,
      description,
    }
  })
})

const formatRupiah = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

const labelGender = (value) => {
  const lower = String(value || '').toLowerCase()
  if (lower === 'putra') return 'Putra'
  if (lower === 'putri') return 'Putri'
  return 'Campur'
}

const labelPriceType = (value) => {
  const lower = String(value || '').toLowerCase()
  if (lower === 'daily') return '/ hari'
  if (lower === 'yearly') return '/ tahun'
  return '/ bulan'
}

const titleCase = (value) => {
  return String(value || '')
    .split(' ')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}


const facilityFilters = computed(() => {
  const discoveredFacilities = new Set()

  properties.value.forEach((property) => {
    property.facilities.forEach((facility) => {
      discoveredFacilities.add(facility)
    })
  })

  return Array.from(discoveredFacilities)
})
const sortOptions = ['Terbaru', 'Harga Terendah', 'Rating Tertinggi']

const minBudget = 500000
const maxBudget = 10000000
const itemsPerPage = 8

const activeNav = ref('Discover')
const searchKeyword = ref('')
const selectedKosType = ref('Semua')
const selectedFacilities = ref([])
const selectedSort = ref('Terbaru')
const isSortOpen = ref(false)
const priceLevel = ref(100)
const currentPage = ref(1)
const searchButtonBusy = ref(false)
const promoBusy = ref(false)
const chatBusy = ref(false)
const isAdvancedSearchOpen = ref(false)
const advancedSearchKeywordInput = ref(null)
const advancedSearchDraft = ref({
  keyword: '',
  kosType: 'Semua',
  facilities: [],
  priceLevel: 100,
})

const flash = ref({
  visible: false,
  message: '',
  tone: 'info',
})

let flashTimer

const selectedMaxPrice = computed(() => {
  if (priceLevel.value === 100) {
    return Number.POSITIVE_INFINITY
  }

  const amount = minBudget + ((maxBudget - minBudget) * priceLevel.value) / 100
  return Math.round(amount)
})

const maxPriceLabel = computed(() => {
  if (priceLevel.value === 100) {
    return 'Rp 10jt+'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(selectedMaxPrice.value)
})

const draftMaxPriceLabel = computed(() => {
  if (advancedSearchDraft.value.priceLevel === 100) {
    return 'Rp 10jt+'
  }

  const amount = minBudget + ((maxBudget - minBudget) * advancedSearchDraft.value.priceLevel) / 100
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Math.round(amount))
})

const navItems = computed(() => [
  { key: 'Discover', label: 'Jelajahi' },
  { key: 'Saved', label: `Tersimpan (${savedCount.value})` },
  { key: 'Messages', label: 'Pesan' },
])

const filteredAndSortedProperties = computed(() => {
  const normalizedKeyword = searchKeyword.value.trim().toLowerCase()

  const filtered = properties.value.filter((property) => {
    const keywordMatches =
      normalizedKeyword.length === 0
      || property.title.toLowerCase().includes(normalizedKeyword)
      || property.area.toLowerCase().includes(normalizedKeyword)
      || property.location.toLowerCase().includes(normalizedKeyword)
    const typeMatches = selectedKosType.value === 'Semua' ? true : property.audience === selectedKosType.value
    const priceMatches = property.priceValue <= selectedMaxPrice.value
    const facilitiesMatch = selectedFacilities.value.every((facility) => property.facilities.includes(facility))

    return keywordMatches && typeMatches && priceMatches && facilitiesMatch
  })

  return [...filtered].sort((propertyA, propertyB) => {
    if (selectedSort.value === 'Harga Terendah') {
      return propertyA.priceValue - propertyB.priceValue
    }

    if (selectedSort.value === 'Rating Tertinggi') {
      return propertyB.rating - propertyA.rating
    }

    return new Date(propertyB.createdAt).getTime() - new Date(propertyA.createdAt).getTime()
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredAndSortedProperties.value.length / itemsPerPage)))

const paginatedProperties = computed(() => {
  const startIndex = (currentPage.value - 1) * itemsPerPage
  const endIndex = startIndex + itemsPerPage

  return filteredAndSortedProperties.value.slice(startIndex, endIndex)
})

const topRowProperties = computed(() => paginatedProperties.value.slice(0, 3))
const bottomRowProperties = computed(() => paginatedProperties.value.slice(3, 8))

const visiblePageNumbers = computed(() => {
  if (totalPages.value <= 4) {
    return Array.from({ length: totalPages.value }, (_, index) => index + 1)
  }

  return [1, 2, 3]
})

const flashClasses = computed(() => {
  if (flash.value.tone === 'success') {
    return 'border-[#b7dfc6] bg-[#eaf9f0] text-[#1c6b3b]'
  }

  if (flash.value.tone === 'warning') {
    return 'border-[#f0d8a7] bg-[#fff7e6] text-[#8a5b12]'
  }

  return 'border-[#bcd7ef] bg-[#edf5fc] text-[#165a8d]'
})

const formatPrice = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount)
}

const formatRating = (rating) => {
  const normalized = Number(rating)
  return normalized > 0 ? normalized : 'Baru'
}

const isFavorite = (propertyId) => isSaved(propertyId)

const showFlash = (message, tone = 'info') => {
  flash.value = {
    visible: true,
    message,
    tone,
  }

  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => {
    flash.value.visible = false
  }, 2200)
}

watch(
  [filteredAndSortedProperties, searchKeyword, selectedKosType, selectedFacilities, selectedSort, priceLevel],
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = 1
    }
  },
  { deep: true }
)

const setActiveNav = (navItem) => {
  if (navItem === 'Discover') {
    activeNav.value = 'Discover'
    return
  }

  if (navItem === 'Saved') {
    if (!isAuthenticated.value) {
      showFlash('Silakan login dulu untuk membuka simpanan.', 'warning')
      router.visit('/login')
      return
    }

    router.visit('/saved')
    return
  }

  if (navItem === 'Messages') {
    if (!isAuthenticated.value) {
      showFlash('Silakan login dulu untuk membuka messages.', 'warning')
      router.visit('/login')
      return
    }

    router.visit('/messages')
    return
  }

  activeNav.value = navItem
}

const openAdvancedSearch = async () => {
  searchButtonBusy.value = true
  advancedSearchDraft.value = {
    keyword: searchKeyword.value,
    kosType: selectedKosType.value,
    facilities: [...selectedFacilities.value],
    priceLevel: priceLevel.value,
  }
  isAdvancedSearchOpen.value = true
  showFlash('Pencarian lanjut siap digunakan.', 'info')

  await nextTick()
  if (advancedSearchKeywordInput.value?.inputRef?.focus) {
    advancedSearchKeywordInput.value.inputRef.focus()
  } else {
    advancedSearchKeywordInput.value?.$el?.querySelector('input')?.focus()
  }

  setTimeout(() => {
    searchButtonBusy.value = false
  }, 900)
}

const applyAdvancedSearch = () => {
  searchKeyword.value = advancedSearchDraft.value.keyword.trim()
  selectedKosType.value = advancedSearchDraft.value.kosType
  selectedFacilities.value = [...advancedSearchDraft.value.facilities]
  priceLevel.value = advancedSearchDraft.value.priceLevel
  currentPage.value = 1
  isAdvancedSearchOpen.value = false
  showFlash('Pencarian diperbarui.', 'success')
}

const resetAdvancedSearch = () => {
  clearFilters()
  advancedSearchDraft.value = {
    keyword: '',
    kosType: 'Semua',
    facilities: [],
    priceLevel: 100,
  }
}

const triggerPromoAction = () => {
  promoBusy.value = true
  showFlash('Menyiapkan langkah pasang iklan...', 'info')

  setTimeout(() => {
    promoBusy.value = false
  }, 900)
}

const triggerChatAction = () => {
  chatBusy.value = true
  showFlash('Menghubungkan ke asisten pencarian...', 'info')

  setTimeout(() => {
    chatBusy.value = false
  }, 900)
}

const toggleSortMenu = () => {
  isSortOpen.value = !isSortOpen.value
}

const chooseSort = (option) => {
  selectedSort.value = option
  isSortOpen.value = false
  currentPage.value = 1
  showFlash(`Urutan diubah ke ${option.toLowerCase()}.`, 'success')
}

const clearFilters = () => {
  searchKeyword.value = ''
  selectedKosType.value = 'Semua'
  selectedFacilities.value = []
  priceLevel.value = 100
  advancedSearchDraft.value = {
    keyword: '',
    kosType: 'Semua',
    facilities: [],
    priceLevel: 100,
  }
  currentPage.value = 1
  showFlash('Filter berhasil direset.', 'success')
}

const toggleFavorite = async (propertyId) => {
  const result = await toggleSaved(propertyId)

  if (result?.requiresLogin) {
    showFlash('Silakan login dulu untuk menyimpan properti.', 'warning')
    router.visit('/login')
    return
  }

  if (result?.failed) {
    showFlash('Gagal memperbarui simpanan. Coba lagi.', 'warning')
    return
  }

  if (result?.saved) {
    showFlash('Properti ditambahkan ke simpanan.', 'success')
    return
  }

  showFlash('Properti dihapus dari simpanan.', 'warning')
}

const openProperty = (propertyId) => {
  router.visit(`/property/${propertyId}`)
}

const goToPage = (pageNumber) => {
  if (pageNumber < 1 || pageNumber > totalPages.value || pageNumber === currentPage.value) {
    return
  }

  currentPage.value = pageNumber
  showFlash(`Berpindah ke halaman ${pageNumber}.`, 'info')
}
</script>

<template>
  <Head title="Discover" />

  <div class="discover-shell min-h-screen bg-[#eff3f7]">
    <div class="w-full min-h-screen overflow-hidden bg-[#eff3f7]">
      <header class="border-b border-[#dce6f0] bg-[#f5f8fb]">
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 md:px-8">
          <div class="flex items-center gap-8">
            <Link href="/" class="inline-flex items-center">
              <img src="/images/logo-koze-new.png" alt="Koze" class="h-20 w-auto md:h-20 object-contain -my-4" />
            </Link>
            <nav class="hidden items-center gap-5 text-sm font-medium text-[#63738c] md:flex">
              <button
                v-for="item in navItems"
                :key="item.key"
                type="button"
                class="cursor-pointer border-b-2 pb-1 transition"
                :class="activeNav === item.key ? 'border-[#2f7ccc] text-[#1b4b9f]' : 'border-transparent text-[#63738c] hover:text-[#2b4f76]'"
                @click="setActiveNav(item.key)"
              >
                {{ item.label }}
              </button>
            </nav>
          </div>

          <div class="flex items-center gap-3">
            <UButton size="sm" color="primary" class="rounded-full px-5 font-semibold" :loading="searchButtonBusy" @click="openAdvancedSearch">
              {{ searchButtonBusy ? 'Menyiapkan...' : 'Cari' }}
            </UButton>
            <UButton v-if="!isAuthenticated" as="a" href="/login" size="sm" variant="soft" class="rounded-full px-4 font-semibold">
              Masuk
            </UButton>
            <div class="flex size-9 items-center justify-center rounded-full bg-[#0f366f] text-sm font-bold text-white">
              {{ userInitial }}
            </div>
          </div>
        </div>
      </header>

      <main class="grid grid-cols-1 md:grid-cols-[280px_1fr]">
        <aside class="border-r border-[#dce6f0] bg-[#e8f1f8] px-5 py-6">
          <div class="mb-8 flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold text-[#203046]">Filter</h2>
              <p class="text-xs text-[#7588a3]">Sempurnakan pencarian Anda</p>
            </div>
            <button type="button" class="cursor-pointer text-xs font-semibold text-[#2f7ccc] hover:underline" @click="clearFilters">Hapus Semua</button>
          </div>

          <section class="mb-6">
            <div class="mb-3 flex items-center justify-between text-sm font-semibold text-[#274465]">
              <span>Rentang Harga</span>
              <span class="text-xs font-bold text-[#2d6ca2]">{{ maxPriceLabel }}</span>
            </div>
            <input
              v-model.number="priceLevel"
              type="range"
              min="0"
              max="100"
              step="1"
              class="mb-2 w-full cursor-pointer accent-[#2f7ccc]"
            >
            <div class="flex justify-between text-xs text-[#67809f]">
              <span>Rp 500rb</span>
              <span>Rp 10jt+</span>
            </div>
          </section>

          <section class="mb-6">
            <p class="mb-3 text-sm font-semibold text-[#274465]">Tipe Kos</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="type in ['Semua', 'Putri', 'Putra', 'Campur']"
                :key="type"
                type="button"
                class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="selectedKosType === type ? 'bg-[#3ebd80] text-white' : 'bg-white text-[#5a708b] hover:bg-[#dbe6f2]'"
                @click="selectedKosType = type"
              >
                {{ type }}
              </button>
            </div>
          </section>

          <section>
            <p class="mb-3 text-sm font-semibold text-[#274465]">Fasilitas</p>
            <div class="space-y-2.5 text-sm text-[#4d6078]">
              <label v-for="facility in facilityFilters" :key="facility" class="flex items-center gap-2.5">
                <input v-model="selectedFacilities" :value="facility" type="checkbox" class="size-4 cursor-pointer rounded border border-[#b5c4d7]" />
                <span>{{ facility }}</span>
              </label>
            </div>
          </section>

          <div class="mt-10 rounded-xl bg-gradient-to-br from-[#0c4c9d] to-[#237fd1] p-4 text-white shadow-lg">
            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-blue-100">Promo Terbatas</p>
            <p class="mt-2 text-sm font-bold leading-tight">Diskon 20% untuk mahasiswa semester awal!</p>
          </div>
        </aside>

        <section class="bg-[#f6f9fc] px-5 py-6 md:px-7">
          <div v-if="searchKeyword" class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#c9dced] bg-white px-3 py-1 text-xs font-semibold text-[#34516f]">
            <span>Kata kunci: "{{ searchKeyword }}"</span>
            <button type="button" class="cursor-pointer text-[#2f7ccc] hover:underline" @click="clearFilters">Hapus</button>
          </div>

          <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h1 class="text-[34px] font-black leading-none text-[#1a2737]">Cari Hunian Impianmu</h1>
              <p class="mt-1 text-sm text-[#647892]">Ditemukan {{ filteredAndSortedProperties.length }} properti</p>
            </div>
            <div class="relative">
              <button
                type="button"
                class="cursor-pointer rounded-full border border-[#cfdceb] bg-white px-4 py-2 text-sm font-semibold text-[#445a75] transition hover:border-[#b5c9de]"
                @click="toggleSortMenu"
              >
                {{ selectedSort }}
              </button>
              <div v-if="isSortOpen" class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl border border-[#cfdceb] bg-white shadow-lg">
                <button
                  v-for="option in sortOptions"
                  :key="option"
                  type="button"
                  class="block w-full cursor-pointer px-3 py-2 text-left text-sm text-[#455a74] transition hover:bg-[#eef4fa]"
                  @click="chooseSort(option)"
                >
                  {{ option }}
                </button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <article
              v-for="property in topRowProperties"
              :key="property.id"
              class="group relative bg-white rounded-2xl overflow-hidden border border-neutral-100 hover:border-primary-100 hover:shadow-2xl hover:shadow-primary-500/10 transition-all duration-300 cursor-pointer flex flex-col"
              @click="openProperty(property.id)"
            >
              <!-- Image Section -->
              <div class="relative h-52 overflow-hidden bg-neutral-100">
                <img
                  :src="property.thumbnail_url || 'https://images.unsplash.com/photo-1560185008-b033106afef8?q=80&w=900&auto=format&fit=crop'"
                  class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out"
                  :alt="property.title"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                <!-- Favorite / Save Button -->
                <button
                  type="button"
                  class="absolute left-3 top-3 z-10 flex size-8 cursor-pointer items-center justify-center rounded-full shadow transition hover:scale-105"
                  :class="isFavorite(property.id) ? 'bg-[#f45a78] text-white' : 'bg-white/85 text-[#5f728d]'"
                  @click.stop="toggleFavorite(property.id)"
                >
                  <UIcon name="i-lucide-heart" class="size-4" />
                </button>

                <!-- Badges -->
                <div class="absolute top-13 left-3 flex flex-col gap-2 z-10">
                  <span v-if="property.is_featured" class="inline-flex items-center gap-1 bg-amber-400/95 backdrop-blur-sm text-amber-900 text-xs font-bold px-2.5 py-1 rounded-lg shadow-sm">
                    <UIcon name="i-heroicons-star-solid" class="w-3 h-3" /> Unggulan
                  </span>
                  <span v-else class="inline-flex items-center bg-neutral-900/70 backdrop-blur-sm text-white text-xs font-medium px-2.5 py-1 rounded-lg">
                    {{ titleCase(property.type) }}
                  </span>
                </div>
                <span class="absolute top-3 right-3 text-xs font-bold px-3 py-1.5 rounded-xl shadow-lg z-10"
                  :class="{
                    'bg-pink-500 text-white': property.gender_restriction === 'putri',
                    'bg-blue-500 text-white': property.gender_restriction === 'putra',
                    'bg-green-500 text-white': !property.gender_restriction || property.gender_restriction === 'campur'
                  }"
                >{{ labelGender(property.gender_restriction) }}</span>
              </div>

              <!-- Content Section -->
              <div class="flex flex-col flex-1 p-4">
                <h3 class="font-bold text-base text-neutral-900 truncate group-hover:text-primary-600 transition-colors mb-1">{{ property.title }}</h3>
                <p class="text-xs text-neutral-400 flex items-center gap-1 truncate mb-3">
                  <UIcon name="i-heroicons-map-pin-solid" class="w-3 h-3 flex-shrink-0 text-neutral-300" />
                  {{ property.address }}
                </p>

                <!-- Description -->
                <p v-if="property.description" class="text-xs text-neutral-500 leading-relaxed line-clamp-2 mb-3">
                  {{ property.description }}
                </p>

                <!-- Facilities -->
                <div v-if="property.facilitiesDetailed?.length" class="flex flex-wrap gap-1.5 mb-4">
                  <span
                    v-for="facility in property.facilitiesDetailed.slice(0, 4)"
                    :key="facility.id"
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-neutral-600 bg-neutral-50 border border-neutral-100 px-2 py-0.5 rounded-md"
                  >
                    <UIcon v-if="facility.icon" :name="facility.icon" class="w-3 h-3 text-primary-400" />
                    {{ facility.name }}
                  </span>
                  <span v-if="property.facilitiesDetailed.length > 4" class="inline-flex items-center text-[11px] font-medium text-primary-500 px-1">
                    +{{ property.facilitiesDetailed.length - 4 }} lainnya
                  </span>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-neutral-100 mt-auto">
                  <div class="flex items-center gap-1 bg-amber-50 px-2 py-1 rounded-lg">
                    <UIcon name="i-heroicons-star-solid" class="w-3.5 h-3.5 text-amber-400" />
                    <span class="text-xs font-bold text-amber-700">{{ formatRating(property.rating) }}</span>
                  </div>
                  <div class="text-right">
                    <span class="font-bold text-sm text-primary-600">{{ formatRupiah(property.price) }}</span>
                    <span class="text-xs text-neutral-400 ml-0.5">{{ labelPriceType(property.price_type) }}</span>
                  </div>
                </div>
              </div>
            </article>
          </div>

          <a
            v-if="sidebarAd && sidebarAd.image_url"
            :href="sidebarAd.target_url"
            target="_blank"
            rel="noopener nofollow"
            class="my-5 block overflow-hidden rounded-2xl ring-1 ring-[#d6e2ee] hover:ring-[#8fb9df] hover:shadow-lg transition group relative bg-white"
            :title="`${sidebarAd.title} — ${sidebarAd.advertiser_name}`"
          >
            <span class="absolute top-3 left-3 z-10 text-[10px] font-bold uppercase tracking-wider bg-white/90 text-neutral-700 px-2 py-0.5 rounded-md backdrop-blur-sm shadow-sm">
              Iklan
            </span>
            <img
              :src="sidebarAd.image_url"
              :alt="`${sidebarAd.title} — ${sidebarAd.advertiser_name}`"
              class="block w-full h-auto object-contain group-hover:scale-[1.01] transition-transform duration-500"
            />
          </a>
          <section v-else class="my-5 rounded-2xl bg-gradient-to-r from-[#cde6f7] via-[#d7ebf9] to-[#d0e6f7] p-5 md:p-6">
            <div class="grid grid-cols-1 items-center gap-5 md:grid-cols-[1fr_250px]">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#2a6ea5]">Daftarkan Properti Anda</p>
                <h2 class="mt-1 text-2xl font-black leading-tight text-[#1c2d44]">Punya kamar kosong? Ubah jadi penghasilan tambahan di Koze.</h2>
                <UButton size="sm" color="primary" class="mt-4 rounded-full px-5 font-semibold" :loading="promoBusy" @click="triggerPromoAction">
                  {{ promoBusy ? 'Menyiapkan...' : 'Mulai Pasang Iklan' }}
                </UButton>
              </div>
              <div class="h-32 rounded-2xl bg-gradient-to-br from-[#526278] via-[#8f9caf] to-[#d7e4ef]" />
            </div>
          </section>

          <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <article
              v-for="property in bottomRowProperties"
              :key="property.id"
              class="group relative bg-white rounded-2xl overflow-hidden border border-neutral-100 hover:border-primary-100 hover:shadow-2xl hover:shadow-primary-500/10 transition-all duration-300 cursor-pointer flex flex-col"
              @click="openProperty(property.id)"
            >
              <!-- Image Section -->
              <div class="relative h-52 overflow-hidden bg-neutral-100">
                <img
                  :src="property.thumbnail_url || 'https://images.unsplash.com/photo-1560185008-b033106afef8?q=80&w=900&auto=format&fit=crop'"
                  class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out"
                  :alt="property.title"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                <!-- Favorite / Save Button -->
                <button
                  type="button"
                  class="absolute left-3 top-3 z-10 flex size-8 cursor-pointer items-center justify-center rounded-full shadow transition hover:scale-105"
                  :class="isFavorite(property.id) ? 'bg-[#f45a78] text-white' : 'bg-white/85 text-[#5f728d]'"
                  @click.stop="toggleFavorite(property.id)"
                >
                  <UIcon name="i-lucide-heart" class="size-4" />
                </button>

                <!-- Badges -->
                <div class="absolute top-13 left-3 flex flex-col gap-2 z-10">
                  <span v-if="property.is_featured" class="inline-flex items-center gap-1 bg-amber-400/95 backdrop-blur-sm text-amber-900 text-xs font-bold px-2.5 py-1 rounded-lg shadow-sm">
                    <UIcon name="i-heroicons-star-solid" class="w-3 h-3" /> Unggulan
                  </span>
                  <span v-else class="inline-flex items-center bg-neutral-900/70 backdrop-blur-sm text-white text-xs font-medium px-2.5 py-1 rounded-lg">
                    {{ titleCase(property.type) }}
                  </span>
                </div>
                <span class="absolute top-3 right-3 text-xs font-bold px-3 py-1.5 rounded-xl shadow-lg z-10"
                  :class="{
                    'bg-pink-500 text-white': property.gender_restriction === 'putri',
                    'bg-blue-500 text-white': property.gender_restriction === 'putra',
                    'bg-green-500 text-white': !property.gender_restriction || property.gender_restriction === 'campur'
                  }"
                >{{ labelGender(property.gender_restriction) }}</span>
              </div>

              <!-- Content Section -->
              <div class="flex flex-col flex-1 p-4">
                <h3 class="font-bold text-base text-neutral-900 truncate group-hover:text-primary-600 transition-colors mb-1">{{ property.title }}</h3>
                <p class="text-xs text-neutral-400 flex items-center gap-1 truncate mb-3">
                  <UIcon name="i-heroicons-map-pin-solid" class="w-3 h-3 flex-shrink-0 text-neutral-300" />
                  {{ property.address }}
                </p>

                <!-- Description -->
                <p v-if="property.description" class="text-xs text-neutral-500 leading-relaxed line-clamp-2 mb-3">
                  {{ property.description }}
                </p>

                <!-- Facilities -->
                <div v-if="property.facilitiesDetailed?.length" class="flex flex-wrap gap-1.5 mb-4">
                  <span
                    v-for="facility in property.facilitiesDetailed.slice(0, 4)"
                    :key="facility.id"
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-neutral-600 bg-neutral-50 border border-neutral-100 px-2 py-0.5 rounded-md"
                  >
                    <UIcon v-if="facility.icon" :name="facility.icon" class="w-3 h-3 text-primary-400" />
                    {{ facility.name }}
                  </span>
                  <span v-if="property.facilitiesDetailed.length > 4" class="inline-flex items-center text-[11px] font-medium text-primary-500 px-1">
                    +{{ property.facilitiesDetailed.length - 4 }} lainnya
                  </span>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-neutral-100 mt-auto">
                  <div class="flex items-center gap-1 bg-amber-50 px-2 py-1 rounded-lg">
                    <UIcon name="i-heroicons-star-solid" class="w-3.5 h-3.5 text-amber-400" />
                    <span class="text-xs font-bold text-amber-700">{{ formatRating(property.rating) }}</span>
                  </div>
                  <div class="text-right">
                    <span class="font-bold text-sm text-primary-600">{{ formatRupiah(property.price) }}</span>
                    <span class="text-xs text-neutral-400 ml-0.5">{{ labelPriceType(property.price_type) }}</span>
                  </div>
                </div>
              </div>
            </article>


          </div>

          <div v-if="filteredAndSortedProperties.length === 0" class="mt-6 rounded-2xl border border-[#d6e2ee] bg-white p-8 text-center">
            <p class="text-base font-bold text-[#264766]">Belum ada hasil yang sesuai.</p>
            <p class="mt-1 text-sm text-[#7388a3]">Coba longgarkan filter atau ubah urutan pencarian.</p>
          </div>

          <div class="mt-8 flex items-center justify-center gap-2 text-sm text-[#57728f]">
            <button type="button" class="flex size-8 items-center justify-center rounded-full border border-[#c9d8e8] bg-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
            <button
              v-for="pageNumber in visiblePageNumbers"
              :key="pageNumber"
              type="button"
              class="flex size-8 items-center justify-center rounded-full border border-[#c9d8e8] bg-white transition"
              :class="currentPage === pageNumber ? 'border-[#8fb9df] bg-[#dcecff] font-semibold text-[#1f74b7]' : 'text-[#57728f] hover:border-[#a8c3dd]'"
              @click="goToPage(pageNumber)"
            >
              {{ pageNumber }}
            </button>
            <span v-if="totalPages > 4" class="px-1">...</span>
            <button
              v-if="totalPages > 4"
              type="button"
              class="flex size-8 items-center justify-center rounded-full border border-[#c9d8e8] bg-white transition"
              :class="currentPage === totalPages ? 'border-[#8fb9df] bg-[#dcecff] font-semibold text-[#1f74b7]' : 'text-[#57728f] hover:border-[#a8c3dd]'"
              @click="goToPage(totalPages)"
            >
              {{ totalPages }}
            </button>
            <button type="button" class="flex size-8 items-center justify-center rounded-full border border-[#c9d8e8] bg-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
          </div>
        </section>
      </main>
    </div>

    <Transition name="flash-fade">
      <div v-if="flash.visible" class="fixed right-5 top-5 z-40 max-w-xs rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg" :class="flashClasses">
        {{ flash.message }}
      </div>
    </Transition>

    <UModal v-model:open="isAdvancedSearchOpen" title="Pencarian Lanjut" :ui="{ footer: 'justify-end' }">
      <template #body>
        <div class="space-y-5">
          <UFormField label="Kata Kunci">
            <UInput
              ref="advancedSearchKeywordInput"
              v-model="advancedSearchDraft.keyword"
              placeholder="Contoh: Tebet, Kemang, kos putri"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Tipe Kos">
            <div class="flex flex-wrap gap-2">
              <button
                v-for="type in ['Semua', 'Putri', 'Putra', 'Campur']"
                :key="`modal-type-${type}`"
                type="button"
                class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="advancedSearchDraft.kosType === type ? 'bg-[#3ebd80] text-white' : 'bg-[#edf3f9] text-[#5a708b] hover:bg-[#dbe6f2]'"
                @click="advancedSearchDraft.kosType = type"
              >
                {{ type }}
              </button>
            </div>
          </UFormField>

          <UFormField label="Maksimal Harga">
            <input
              v-model.number="advancedSearchDraft.priceLevel"
              type="range"
              min="0"
              max="100"
              step="1"
              class="w-full cursor-pointer accent-[#2f7ccc]"
            >
            <div class="mt-2 flex justify-between text-xs text-[#67809f]">
              <span>Rp 500rb</span>
              <span>{{ draftMaxPriceLabel }}</span>
            </div>
          </UFormField>

          <UFormField label="Fasilitas">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <label v-for="facility in facilityFilters" :key="`modal-facility-${facility}`" class="flex items-center gap-2 text-sm text-[#4d6078]">
                <input
                  v-model="advancedSearchDraft.facilities"
                  :value="facility"
                  type="checkbox"
                  class="size-4 cursor-pointer rounded border border-[#b5c4d7]"
                >
                <span>{{ facility }}</span>
              </label>
            </div>
          </UFormField>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full items-center justify-between gap-2">
          <UButton color="neutral" variant="soft" @click="resetAdvancedSearch">Reset</UButton>
          <div class="flex items-center gap-2">
            <UButton color="neutral" variant="ghost" @click="isAdvancedSearchOpen = false">Batal</UButton>
            <UButton color="primary" @click="applyAdvancedSearch">Cari</UButton>
          </div>
        </div>
      </template>
    </UModal>

    <GlobalChatFloatingButton />
  </div>
</template>

<style scoped>
.discover-shell {
  font-family: 'Manrope', 'Plus Jakarta Sans', 'Avenir Next', sans-serif;
}

.flash-fade-enter-active,
.flash-fade-leave-active {
  transition: all 0.2s ease;
}

.flash-fade-enter-from,
.flash-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
