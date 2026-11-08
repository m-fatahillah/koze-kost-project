<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import GlobalChatFloatingButton from '@/Components/chat/GlobalChatFloatingButton.vue'
import { useSavedProperties } from '@/composables/useSavedProperties'

const props = defineProps({
  properties: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()

const { savedCount, isSaved, toggleSaved } = useSavedProperties()

const activeNav = ref('Saved')
const selectedSort = ref('Terbaru disimpan')
const isSortOpen = ref(false)
const currentPage = ref(1)
const itemsPerPage = 9

const sortOptions = ['Terbaru disimpan', 'Harga terendah', 'Rating tertinggi']

const flash = ref({
  visible: false,
  message: '',
  tone: 'info',
})

let flashTimer

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

const userInitial = computed(() => {
  const userName = page.props.auth?.user?.name || 'Guest'
  return userName.charAt(0).toUpperCase()
})

const navItems = computed(() => [
  { key: 'Discover', label: 'Jelajahi' },
  { key: 'Saved', label: `Tersimpan (${savedCount.value})` },
  { key: 'Messages', label: 'Pesan' },
])

const savedProperties = computed(() => {
  if (!Array.isArray(props.properties)) {
    return []
  }

  return props.properties
    .map((property, index) => ({
      id: Number(property.id ?? index + 1),
      title: property.title ?? 'Properti Tanpa Nama',
      area: property.area ?? 'Lokasi belum diisi',
      location: property.location ?? property.area ?? 'Lokasi belum diisi',
      audience: ['Putri', 'Putra', 'Campur'].includes(property.audience) ? property.audience : 'Campur',
      priceValue: Number(property.priceValue ?? 0),
      rating: Number(property.rating ?? 0),
      createdAt: property.createdAt ?? '1970-01-01',
      facilities: Array.isArray(property.facilities) ? property.facilities : [],
      cover: property.cover ?? 'linear-gradient(140deg, #b1b8c8 0%, #ddd1bb 45%, #f4efe7 100%)',
      availability: property.availability ?? 'Tersedia',
      savedAt: property.savedAt ?? null,
    }))
    .filter((property) => isSaved(property.id))
})

const sortedSavedProperties = computed(() => {
  const savedList = [...savedProperties.value]
  const parseSavedAt = (property) => {
    if (!property.savedAt) {
      return 0
    }

    const parsed = Date.parse(property.savedAt)
    return Number.isNaN(parsed) ? 0 : parsed
  }

  if (selectedSort.value === 'Harga terendah') {
    return savedList.sort((propertyA, propertyB) => propertyA.priceValue - propertyB.priceValue)
  }

  if (selectedSort.value === 'Rating tertinggi') {
    return savedList.sort((propertyA, propertyB) => propertyB.rating - propertyA.rating)
  }

  return savedList.sort((propertyA, propertyB) => parseSavedAt(propertyB) - parseSavedAt(propertyA))
})

const totalPages = computed(() => Math.max(1, Math.ceil(sortedSavedProperties.value.length / itemsPerPage)))

const paginatedSavedProperties = computed(() => {
  const startIndex = (currentPage.value - 1) * itemsPerPage
  const endIndex = startIndex + itemsPerPage

  return sortedSavedProperties.value.slice(startIndex, endIndex)
})

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
  [sortedSavedProperties, selectedSort],
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  },
  { deep: true },
)

const setActiveNav = (navItem) => {
  if (navItem === 'Saved') {
    return
  }

  if (navItem === 'Discover') {
    router.visit('/discover')
    return
  }

  if (!isAuthenticated.value) {
    showFlash('Silakan login dulu untuk membuka messages.', 'warning')
    router.visit('/login')
    return
  }

  router.visit('/messages')
}

const toggleSortMenu = () => {
  isSortOpen.value = !isSortOpen.value
}

const chooseSort = (option) => {
  selectedSort.value = option
  isSortOpen.value = false
  currentPage.value = 1
}

const openProperty = (propertyId) => {
  router.visit(`/property/${propertyId}`)
}

const removeFromSaved = async (propertyId) => {
  if (!isSaved(propertyId)) {
    return
  }

  const result = await toggleSaved(propertyId)

  if (result?.requiresLogin) {
    showFlash('Silakan login dulu untuk mengubah simpanan.', 'warning')
    router.visit('/login')
    return
  }

  if (result?.failed) {
    showFlash('Gagal menghapus simpanan. Coba lagi.', 'warning')
    return
  }

  showFlash('Properti dihapus dari simpanan.', 'warning')
}

const goToPage = (pageNumber) => {
  if (pageNumber < 1 || pageNumber > totalPages.value || pageNumber === currentPage.value) {
    return
  }

  currentPage.value = pageNumber
}
</script>

<template>
  <Head title="Saved" />

  <div class="saved-shell min-h-screen bg-[#eff3f7]">
    <div class="w-full min-h-screen overflow-hidden bg-[#eff3f7]">
      <header class="border-b border-[#dce6f0] bg-[#f5f8fb]">
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 md:px-8">
          <div class="flex items-center gap-8">
            <Link href="/" class="inline-flex items-center">
              <img src="/images/logo-koze-new.png" alt="Koze Kos" class="h-20 w-auto md:h-20 object-contain -my-4" />
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
            <button
              type="button"
              class="rounded-full border border-[#c8d8e7] bg-white px-4 py-2 text-sm font-semibold text-[#2c4e73]"
              @click="router.visit('/discover')"
            >
              Jelajah Lagi
            </button>
            <button
              v-if="!isAuthenticated"
              type="button"
              class="rounded-full border border-[#c8d8e7] bg-white px-4 py-2 text-sm font-semibold text-[#2c4e73]"
              @click="router.visit('/login')"
            >
              Masuk
            </button>
            <div class="flex size-9 items-center justify-center rounded-full bg-[#0f366f] text-sm font-bold text-white">
              {{ userInitial }}
            </div>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-[1280px] px-5 py-6 md:px-8 md:py-8">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#4e7aa0]">Properti Tersimpan</p>
            <h1 class="mt-1 text-3xl font-black leading-none text-[#1a2737]">Daftar Simpanan Kamu</h1>
            <p class="mt-2 text-sm text-[#647892]">{{ sortedSavedProperties.length }} properti siap dibandingkan</p>
          </div>
          <div class="relative">
            <button
              type="button"
              class="cursor-pointer rounded-full border border-[#cfdceb] bg-white px-4 py-2 text-sm font-semibold text-[#445a75] transition hover:border-[#b5c9de]"
              @click="toggleSortMenu"
            >
              {{ selectedSort }}
            </button>
            <div v-if="isSortOpen" class="absolute right-0 z-20 mt-2 w-48 overflow-hidden rounded-xl border border-[#cfdceb] bg-white shadow-lg">
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

        <div v-if="sortedSavedProperties.length === 0" class="py-14 text-center md:py-20">
          <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#e8f1fb] text-[#2f7ccc]">
            <UIcon name="i-lucide-heart-off" class="size-6" />
          </div>
          <h2 class="mt-4 text-xl font-black text-[#22344a]">Belum ada properti tersimpan</h2>
          <p class="mt-2 text-sm text-[#6f859f]">Simpan properti favorit dari Discover supaya lebih mudah dibandingkan nanti.</p>
          <button
            type="button"
            class="mt-5 rounded-full bg-[#2f7ccc] px-5 py-2 text-sm font-bold text-white"
            @click="router.visit('/discover')"
          >
            Kembali ke Jelajahi
          </button>
        </div>

        <div v-else>
          <div class="space-y-4">
            <article
              v-for="property in paginatedSavedProperties"
              :key="property.id"
              class="group grid cursor-pointer grid-cols-1 gap-4 rounded-2xl border border-[#d9e4ef] bg-white p-4 shadow-[0_10px_30px_rgba(33,61,93,0.08)] transition hover:-translate-y-0.5 md:grid-cols-[220px_1fr_auto] md:items-center"
              @click="openProperty(property.id)"
            >
              <div class="relative h-36 overflow-hidden rounded-xl" :style="{ background: property.cover }">
                <button
                  type="button"
                  class="absolute left-3 top-3 flex size-8 cursor-pointer items-center justify-center rounded-full bg-[#f45a78] text-white shadow transition hover:scale-105"
                  @click.stop="removeFromSaved(property.id)"
                >
                  <UIcon name="i-lucide-heart" class="size-4" />
                </button>
                <span class="absolute right-3 top-3 rounded-full bg-[#4ec980] px-2.5 py-1 text-[11px] font-bold text-white">
                  {{ property.audience }}
                </span>
                <span class="absolute bottom-3 right-3 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-[#35597e]">
                  {{ property.availability }}
                </span>
              </div>

              <div>
                <div class="mb-1.5 flex items-start justify-between gap-3">
                  <h3 class="text-xl font-bold leading-tight text-[#1f2f44] md:text-2xl">{{ property.title }}</h3>
                  <span class="text-sm font-semibold text-[#2b7bc8] md:hidden">★ {{ formatRating(property.rating) }}</span>
                </div>
                <p class="text-base text-[#7587a0]">{{ property.location }} - {{ property.area }}</p>
                <p class="mt-3 text-lg font-black text-[#1170bf]">{{ formatPrice(property.priceValue) }} <span class="text-sm font-medium text-[#6f849e]">/ bln</span></p>
              </div>

              <div class="flex items-center justify-between gap-4 md:flex-col md:items-end md:justify-center">
                <span class="hidden text-sm font-semibold text-[#2b7bc8] md:inline">★ {{ formatRating(property.rating) }}</span>
                <button
                  type="button"
                  class="rounded-full border border-[#c9dced] px-4 py-2 text-sm font-semibold text-[#31597c] transition hover:bg-[#f1f7fd]"
                  @click.stop="openProperty(property.id)"
                >
                  Lihat detail
                </button>
              </div>
            </article>
          </div>

          <div v-if="sortedSavedProperties.length > itemsPerPage" class="mt-7 flex items-center justify-center gap-2 text-sm text-[#57728f]">
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
        </div>
      </main>
    </div>

    <Transition name="flash-fade">
      <div v-if="flash.visible" class="fixed right-5 top-5 z-40 max-w-xs rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg" :class="flashClasses">
        {{ flash.message }}
      </div>
    </Transition>

    <GlobalChatFloatingButton />
  </div>
</template>

<style scoped>
.saved-shell {
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
