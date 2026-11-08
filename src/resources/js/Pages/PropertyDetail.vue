<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import 'leaflet/dist/leaflet.css'
import GlobalChatFloatingButton from '@/Components/chat/GlobalChatFloatingButton.vue'
import { useGlobalChat } from '@/composables/useGlobalChat'
import { useSavedProperties } from '@/composables/useSavedProperties'

const props = defineProps({
  property: {
    type: Object,
    default: null,
  },
  similarProperties: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()
const { openOwnerConversation } = useGlobalChat()
const { isSaved, toggleSaved } = useSavedProperties()

const reportReasons = ['Informasi menyesatkan', 'Bahasa tidak pantas', 'Spam atau promosi', 'Lainnya']

const quickReplyOptions = ['Masih tersedia?', 'Bisa survey dulu?', 'Unitnya masih kosong?']

const supplementaryConversationTemplates = [
  {
    id: 'supplementary-support',
    name: 'Tim Koze Support',
    role: 'Concierge',
    responseTime: 'Aktif 15 menit lalu',
    messages: [
      {
        id: 'supplementary-support-m1',
        sender: 'owner',
        text: 'Halo! Kalau butuh bantuan proses booking, aku siap bantu.',
        timestamp: '08.45',
      },
      {
        id: 'supplementary-support-m2',
        sender: 'user',
        text: 'Noted, nanti saya hubungi lagi ya.',
        timestamp: '08.51',
        status: 'seen',
      },
    ],
  },
  {
    id: 'supplementary-putra',
    name: 'Rama Pradana',
    role: 'Pemilik Kos Putra',
    responseTime: 'Aktif 1 jam lalu',
    messages: [
      {
        id: 'supplementary-putra-m1',
        sender: 'owner',
        text: 'Unit lantai 2 masih ada, kalau mau aku kirim detailnya.',
        timestamp: '10.12',
      },
    ],
  },
  {
    id: 'supplementary-campur',
    name: 'Anisa Hartati',
    role: 'Manajer Hunian Campur',
    responseTime: 'Aktif 40 menit lalu',
    messages: [],
  },
]

const fallbackGallery = [
  'linear-gradient(140deg, #dce8f4 0%, #f4f7fb 50%, #c8daee 100%)',
  'linear-gradient(140deg, #264866 0%, #4d7ca2 50%, #c7dff2 100%)',
  'linear-gradient(140deg, #1f2d3d 0%, #496889 50%, #e1edf8 100%)',
]

const flash = ref({
  visible: false,
  message: '',
  tone: 'info',
})

const avatarFallbackMap = ref({})
const isReviewModalOpen = ref(false)
const isReportModalOpen = ref(false)
const reviewFormError = ref('')
const reportTargetReview = ref(null)
const reviewForm = useForm({
  rating: 5,
  comment: '',
})
const reportForm = ref({
  reason: reportReasons[0],
  note: '',
})

const isChatDrawerOpen = ref(false)
const chatMobilePanel = ref('list')
const activeChatId = ref(null)
const chatDraftMessage = ref('')
const chatDraftAttachments = ref([])
const chatAttachmentInput = ref(null)
const chatComposerRef = ref(null)
const chatConversations = ref([])

// ===== Leaflet map =====
const mapRef = ref(null)
let leafletMap = null
let leafletMarker = null
let leafletLib = null

const getLeaflet = async () => {
  if (leafletLib) return leafletLib
  const mod = await import('leaflet')
  leafletLib = mod.default
  return leafletLib
}

const hasMapCoordinates = computed(() => {
  const lat = Number(property.value?.latitude)
  const lng = Number(property.value?.longitude)
  return Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0)
})

const initPropertyMap = async () => {
  if (!mapRef.value || leafletMap || !hasMapCoordinates.value) return

  const L = await getLeaflet()
  const lat = Number(property.value.latitude)
  const lng = Number(property.value.longitude)

  leafletMap = L.map(mapRef.value, {
    zoomControl: false,
    scrollWheelZoom: false,
    preferCanvas: true,
  }).setView([lat, lng], 15)

  L.control.zoom({ position: 'bottomright' }).addTo(leafletMap)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(leafletMap)

  const icon = L.divIcon({
    className: 'vk-pin-wrapper',
    html: `<div class="vk-pin vk-pin--featured"><div class="vk-pin__inner"></div></div>`,
    iconSize: [28, 38],
    iconAnchor: [14, 38],
  })

  leafletMarker = L.marker([lat, lng], { icon }).addTo(leafletMap)
}

const maxChatAttachmentCount = 3

let flashTimer

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const currentUser = computed(() => page.props.auth?.user ?? null)
const property = computed(() => props.property ?? null)
const similarProperties = computed(() => (Array.isArray(props.similarProperties) ? props.similarProperties : []))
const isFavorite = computed(() => (property.value ? isSaved(property.value.id) : false))

const reviewsForDisplay = computed(() => {
  if (!property.value?.reviews) {
    return []
  }

  return property.value.reviews.map((review) => ({
    ...review,
    initial: review.initial ?? getReviewerInitial(review.name),
  }))
})
const reviewsEnabled = computed(() => Boolean(property.value?.reviewsEnabled))
const canReview = computed(() => Boolean(property.value?.canReview))
const reviewBlockReason = computed(() => property.value?.reviewBlockReason ?? null)
const myReview = computed(() => property.value?.myReview ?? null)
const reviewSubmitMode = computed(() => (myReview.value ? 'edit' : 'create'))

const galleryItems = computed(() => {
  if (!property.value) {
    return []
  }

  const gallery = Array.isArray(property.value.gallery) ? [...property.value.gallery] : []

  if (!gallery.length && property.value.cover) {
    gallery.push(property.value.cover)
  }

  let index = 0
  while (gallery.length < 4) {
    gallery.push(fallbackGallery[index % fallbackGallery.length])
    index += 1
  }

  return gallery.slice(0, 4)
})

const priceLabel = computed(() => {
  if (!property.value) {
    return ''
  }

  const priceType = property.value.priceType ?? 'monthly'
  const unitLabel = priceType === 'daily' ? 'hari' : priceType === 'yearly' ? 'tahun' : 'bulan'
  return `${formatPrice(property.value.priceValue)} / ${unitLabel}`
})

const ratingBadge = computed(() => {
  const rating = Number(property.value?.rating ?? 0)
  return rating > 0 ? rating : 'Baru'
})

const ratingSummary = computed(() => {
  const rating = Number(property.value?.rating ?? 0)
  return rating > 0 ? `${rating} / 5` : 'Belum ada rating'
})

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

const formatPrice = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount)
}

const formatReviewDate = (dateValue) => {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(dateValue)
}

const formatChatTime = (dateValue) => {
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(dateValue)
}

const formatFileSize = (sizeInBytes) => {
  if (sizeInBytes < 1024) {
    return `${sizeInBytes} B`
  }

  const sizeInKb = sizeInBytes / 1024
  if (sizeInKb < 1024) {
    return `${sizeInKb.toFixed(1)} KB`
  }

  return `${(sizeInKb / 1024).toFixed(1)} MB`
}

const getLastMessage = (conversation) => {
  if (!conversation?.messages?.length) {
    return null
  }

  return conversation.messages[conversation.messages.length - 1]
}

const getConversationPreview = (conversation) => {
  const lastMessage = getLastMessage(conversation)

  if (!lastMessage) {
    return 'Belum ada pesan.'
  }

  if (lastMessage.text) {
    return lastMessage.text
  }

  const attachmentCount = lastMessage.attachments?.length || 0
  return attachmentCount ? `Lampiran (${attachmentCount})` : 'Pesan baru'
}

const getConversationStatusLabel = (conversation) => {
  const lastMessage = getLastMessage(conversation)

  if (!lastMessage || lastMessage.sender !== 'user') {
    return ''
  }

  return lastMessage.status === 'seen' ? 'Dilihat' : 'Terkirim'
}

const isLastOutgoingMessage = (messages, messageIndex) => {
  for (let index = messages.length - 1; index >= 0; index -= 1) {
    if (messages[index].sender === 'user') {
      return index === messageIndex
    }
  }

  return false
}

const getOwnerConversationId = (propertyData) => {
  return `owner-${propertyData.id}`
}

const buildOwnerConversation = (propertyData) => {
  const ownerConversationId = getOwnerConversationId(propertyData)

  return {
    id: ownerConversationId,
    name: propertyData.owner.name,
    role: propertyData.owner.role,
    responseTime: propertyData.owner.responseTime,
    messages: [
      {
        id: `${ownerConversationId}-m1`,
        sender: 'owner',
        text: `Halo, saya ${propertyData.owner.name}. Silakan tanya apa pun soal ${propertyData.title}.`,
        timestamp: '09.12',
      },
      {
        id: `${ownerConversationId}-m2`,
        sender: 'user',
        text: 'Baik kak, saya mau tanya ketersediaan kamar bulan ini.',
        timestamp: '09.15',
        status: 'seen',
      },
    ],
  }
}

const cloneConversation = (conversation) => {
  return {
    ...conversation,
    messages: (conversation.messages || []).map((message) => ({
      ...message,
      attachments: (message.attachments || []).map((attachment) => ({ ...attachment })),
    })),
  }
}

const seedConversationsForProperty = (propertyData) => {
  const ownerConversation = buildOwnerConversation(propertyData)
  const supplementaryConversations = supplementaryConversationTemplates
    .filter((conversation) => !(conversation.name === propertyData.owner.name && conversation.role === propertyData.owner.role))
    .map((item) => cloneConversation(item))

  return [ownerConversation, ...supplementaryConversations]
}

const syncConversationsForProperty = () => {
  if (!property.value) {
    chatConversations.value = []
    activeChatId.value = null
    return
  }

  const seededConversations = seedConversationsForProperty(property.value)
  const previousConversations = new Map(chatConversations.value.map((conversation) => [conversation.id, conversation]))

  chatConversations.value = seededConversations.map((seededConversation) => {
    const previousConversation = previousConversations.get(seededConversation.id)

    if (!previousConversation) {
      return seededConversation
    }

    return {
      ...seededConversation,
      messages: previousConversation.messages,
    }
  })

  const ownerConversationId = getOwnerConversationId(property.value)

  if (!activeChatId.value || !chatConversations.value.some((conversation) => conversation.id === activeChatId.value)) {
    activeChatId.value = chatConversations.value.some((conversation) => conversation.id === ownerConversationId)
      ? ownerConversationId
      : chatConversations.value[0]?.id || null
  }
}

const updateConversation = (conversationId, updater) => {
  chatConversations.value = chatConversations.value.map((conversation) => {
    if (conversation.id !== conversationId) {
      return conversation
    }

    return updater(conversation)
  })
}

const moveConversationToTop = (conversationId) => {
  const targetConversation = chatConversations.value.find((conversation) => conversation.id === conversationId)

  if (!targetConversation) {
    return
  }

  chatConversations.value = [
    targetConversation,
    ...chatConversations.value.filter((conversation) => conversation.id !== conversationId),
  ]
}

const markLastOutgoingAsSeen = (conversationId) => {
  updateConversation(conversationId, (conversation) => {
    let targetIndex = -1

    for (let index = conversation.messages.length - 1; index >= 0; index -= 1) {
      if (conversation.messages[index].sender === 'user') {
        targetIndex = index
        break
      }
    }

    if (targetIndex < 0) {
      return conversation
    }

    return {
      ...conversation,
      messages: conversation.messages.map((message, index) => {
        if (index !== targetIndex) {
          return message
        }

        return {
          ...message,
          status: 'seen',
        }
      }),
    }
  })
}

const buildAttachmentEntry = (file) => {
  return {
    id: `file-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    name: file.name,
    size: file.size,
    type: file.type,
  }
}

const getReviewerInitial = (name) => {
  return String(name || 'U').trim().charAt(0).toUpperCase() || 'U'
}

const normalizeAvatarUrl = (avatar) => {
  if (!avatar) {
    return null
  }

  if (avatar.startsWith('http://') || avatar.startsWith('https://')) {
    return avatar
  }

  if (avatar.startsWith('/storage/')) {
    return avatar
  }

  return `/storage/${avatar.replace(/^\/+/, '')}`
}

const shouldShowAvatarImage = (review) => {
  return Boolean(review.avatarUrl) && !avatarFallbackMap.value[review.id]
}

const markAvatarFallback = (reviewId) => {
  avatarFallbackMap.value = {
    ...avatarFallbackMap.value,
    [reviewId]: true,
  }
}

const resetReviewForm = () => {
  reviewForm.rating = myReview.value?.rating ?? 5
  reviewForm.comment = myReview.value?.comment ?? ''
  reviewFormError.value = ''
  reviewForm.clearErrors?.()
}

const resetReportForm = () => {
  reportForm.value = {
    reason: reportReasons[0],
    note: '',
  }
}


watch(
  () => property.value?.id,
  () => {
    syncConversationsForProperty()
  },
  { immediate: true },
)


const activeConversation = computed(() => {
  if (!activeChatId.value) {
    return null
  }

  return chatConversations.value.find((conversation) => conversation.id === activeChatId.value) || null
})

const canSendChatMessage = computed(() => {
  return Boolean(chatDraftMessage.value.trim()) || chatDraftAttachments.value.length > 0
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

const goBack = () => {
  router.visit('/discover')
}

const toggleFavorite = async () => {
  if (!property.value) {
    return
  }

  const result = await toggleSaved(property.value.id)

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

const openChatDrawer = async () => {
  isChatDrawerOpen.value = true
  await nextTick()
  chatComposerRef.value?.focus()
}

const closeChatDrawer = () => {
  isChatDrawerOpen.value = false
  chatMobilePanel.value = 'list'
}

const showConversationListOnMobile = () => {
  chatMobilePanel.value = 'list'
}

const selectConversation = async (conversationId) => {
  activeChatId.value = conversationId
  chatMobilePanel.value = 'chat'
  await nextTick()
  chatComposerRef.value?.focus()
}

const chatOwner = async () => {
  if (!isAuthenticated.value) {
    showFlash('Silakan login dulu untuk chat pemilik.', 'warning')
    router.visit('/login')
    return
  }

  if (!property.value) {
    return
  }

  openOwnerConversation({
    ownerId: property.value.owner.id,
    propertyId: property.value.id,
  })
}

const shareProperty = async () => {
  if (typeof window === 'undefined') {
    return
  }

  const url = window.location.href

  try {
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(url)
      showFlash('Link properti berhasil disalin.', 'success')
      return
    }
  } catch (error) {
    // Fallback handled below
  }

  showFlash('Browser tidak mendukung copy otomatis.', 'warning')
}

const openProperty = (propertyId) => {
  router.visit(`/property/${propertyId}`)
}

const triggerAttachmentPicker = () => {
  chatAttachmentInput.value?.click()
}

const removeAttachment = (attachmentId) => {
  chatDraftAttachments.value = chatDraftAttachments.value.filter((attachment) => attachment.id !== attachmentId)
}

const handleAttachmentSelection = (event) => {
  const selectedFiles = Array.from(event.target.files || [])

  if (!selectedFiles.length) {
    return
  }

  const remainingSlots = maxChatAttachmentCount - chatDraftAttachments.value.length

  if (remainingSlots <= 0) {
    showFlash(`Maksimal ${maxChatAttachmentCount} lampiran per pesan.`, 'warning')
    event.target.value = ''
    return
  }

  const acceptedFiles = selectedFiles.slice(0, remainingSlots).map((file) => buildAttachmentEntry(file))

  chatDraftAttachments.value = [...chatDraftAttachments.value, ...acceptedFiles]

  if (selectedFiles.length > remainingSlots) {
    showFlash(`Sebagian file tidak ditambahkan. Batas lampiran ${maxChatAttachmentCount} file.`, 'warning')
  }

  event.target.value = ''
}

const applyQuickReply = async (replyText) => {
  if (chatDraftMessage.value.trim()) {
    chatDraftMessage.value = `${chatDraftMessage.value.trim()}\n${replyText}`
  } else {
    chatDraftMessage.value = replyText
  }

  await nextTick()
  chatComposerRef.value?.focus()
}

const sendChatMessage = () => {
  if (!activeConversation.value || !canSendChatMessage.value) {
    return
  }

  const messageText = chatDraftMessage.value.trim()
  const messageAttachments = chatDraftAttachments.value.map((attachment) => ({ ...attachment }))
  const sentAt = formatChatTime(new Date())
  const outgoingMessage = {
    id: `msg-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    sender: 'user',
    text: messageText,
    timestamp: sentAt,
    status: 'sent',
    attachments: messageAttachments,
  }

  updateConversation(activeConversation.value.id, (conversation) => ({
    ...conversation,
    messages: [...conversation.messages, outgoingMessage],
  }))

  moveConversationToTop(activeConversation.value.id)
  chatDraftMessage.value = ''
  chatDraftAttachments.value = []

  setTimeout(() => {
    markLastOutgoingAsSeen(activeConversation.value.id)
  }, 1100)
}

const handleComposerKeydown = (event) => {
  if (event.key !== 'Enter') {
    return
  }

  if (!event.ctrlKey && !event.metaKey) {
    return
  }

  event.preventDefault()
  sendChatMessage()
}

const openReviewModal = () => {
  if (!reviewsEnabled.value) {
    showFlash('Fitur ulasan sedang disiapkan.', 'info')
    return
  }

  if (!isAuthenticated.value) {
    showFlash('Silakan login dulu untuk kasih ulasan.', 'warning')
    router.visit('/login')
    return
  }

  if (!canReview.value) {
    showFlash(reviewBlockReason.value || 'Anda tidak dapat memberi ulasan pada properti ini.', 'warning')
    return
  }

  resetReviewForm()
  isReviewModalOpen.value = true
}

const closeReviewModal = () => {
  isReviewModalOpen.value = false
  reviewFormError.value = ''
}

const submitReview = () => {
  if (!property.value) return

  if (!reviewForm.rating || reviewForm.rating < 1 || reviewForm.rating > 5) {
    reviewFormError.value = 'Rating harus antara 1 sampai 5.'
    return
  }

  reviewForm.post(`/properties/${property.value.id}/reviews`, {
    preserveScroll: true,
    onSuccess: () => {
      closeReviewModal()
      showFlash(reviewSubmitMode.value === 'edit' ? 'Ulasan berhasil diperbarui.' : 'Ulasan terkirim. Terima kasih!', 'success')
    },
    onError: (errors) => {
      reviewFormError.value = errors?.error || errors?.rating || errors?.comment || 'Gagal menyimpan ulasan.'
    },
  })
}

const deleteMyReview = () => {
  if (!myReview.value) return
  if (!confirm('Hapus ulasan Anda untuk properti ini?')) return

  router.delete(`/reviews/${myReview.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      showFlash('Ulasan dihapus.', 'success')
    },
  })
}

const openReportModal = (review) => {
  reportTargetReview.value = review
  resetReportForm()
  isReportModalOpen.value = true
}

const closeReportModal = () => {
  isReportModalOpen.value = false
  reportTargetReview.value = null
}

const submitReportReview = () => {
  if (!reportTargetReview.value) {
    return
  }

  const reviewerName = reportTargetReview.value.name
  closeReportModal()
  showFlash(`Laporan untuk ulasan ${reviewerName} sudah kami terima.`, 'success')
}

onMounted(async () => {
  await nextTick()
  initPropertyMap()
})

onBeforeUnmount(() => {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    leafletMarker = null
  }
})

watch(
  () => [property.value?.id, property.value?.latitude, property.value?.longitude],
  async () => {
    if (leafletMap) {
      leafletMap.remove()
      leafletMap = null
      leafletMarker = null
    }
    await nextTick()
    initPropertyMap()
  },
)
</script>

<template>
  <Head :title="property ? property.title : 'Property Detail'" />

  <div class="detail-shell min-h-screen bg-[#eff3f7]">
    <header class="border-b border-[#dce6f0] bg-[#f5f8fb]">
      <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between gap-3 px-5 py-4 md:px-8">
        <Link href="/" class="inline-flex items-center">
          <img src="/images/logo-koze-new.png" alt="Koze" class="h-20 w-auto md:h-20 object-contain -my-4" />
        </Link>
        <button type="button" class="cursor-pointer rounded-full border border-[#c8d8e7] bg-white px-4 py-2 text-sm font-semibold text-[#2c4e73]" @click="goBack">
          Kembali ke Discover
        </button>
      </div>
    </header>

    <main class="mx-auto w-full max-w-[1280px] px-5 py-6 md:px-8 md:py-8">
      <section v-if="!property" class="rounded-xl border border-[#d6e2ee] bg-white p-8 text-center shadow-[0_15px_35px_rgba(23,61,98,0.08)]">
        <h1 class="text-2xl font-black text-[#1d314b]">Properti tidak ditemukan</h1>
        <p class="mt-2 text-sm text-[#6f849d]">ID properti tidak tersedia saat ini.</p>
        <button type="button" class="mt-5 cursor-pointer rounded-full bg-[#2f7ccc] px-5 py-2 text-sm font-bold text-white" @click="goBack">
          Kembali ke daftar properti
        </button>
      </section>

      <div v-else class="grid grid-cols-1 gap-5 lg:grid-cols-[1.3fr_0.7fr]">
        <section class="space-y-5">
          <article class="overflow-hidden rounded-xl border border-[#d6e2ee] bg-white shadow-[0_15px_35px_rgba(23,61,98,0.08)]">
            <!-- Gallery mosaic -->
            <div class="grid grid-cols-1 gap-2 p-3 md:grid-cols-[2fr_1fr] md:p-3">
              <div class="relative h-72 overflow-hidden rounded-lg md:h-[360px]" :style="{ background: galleryItems[0] }">
                <div class="absolute left-3 top-3 flex flex-col gap-2">
                  <span v-if="property.isFeatured" class="inline-flex items-center gap-1 rounded-md bg-amber-400/95 px-2.5 py-1 text-[11px] font-bold uppercase text-amber-900 shadow-sm backdrop-blur-sm">
                    <UIcon name="i-heroicons-star-solid" class="h-3 w-3" /> Unggulan
                  </span>
                  <span v-else class="inline-flex items-center rounded-md bg-neutral-900/70 px-2.5 py-1 text-[11px] font-bold uppercase text-white backdrop-blur-sm">
                    {{ property.propertyType || 'Properti' }}
                  </span>
                </div>
                <span
                  class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-bold text-white shadow-lg"
                  :class="{
                    'bg-pink-500': property.genderRestriction === 'putri',
                    'bg-blue-500': property.genderRestriction === 'putra',
                    'bg-emerald-500': !property.genderRestriction || property.genderRestriction === 'campur',
                  }"
                >
                  <UIcon name="i-heroicons-user-group-solid" class="h-3 w-3" />
                  {{ property.audience }}
                </span>
                <div v-if="property.ratingCount > 0" class="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-md bg-white/95 px-2.5 py-1 text-[11px] font-bold text-amber-700 backdrop-blur-sm shadow-sm">
                  <UIcon name="i-heroicons-star-solid" class="h-3 w-3 text-amber-400" />
                  {{ property.rating }} <span class="text-neutral-400 font-medium">({{ property.ratingCount }})</span>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-2 md:grid-cols-1">
                <div
                  v-for="(galleryItem, index) in galleryItems.slice(1)"
                  :key="`${property.id}-gallery-${index}`"
                  class="relative h-24 overflow-hidden rounded-lg md:h-[114px]"
                  :style="{ background: galleryItem }"
                >
                  <div v-if="index === galleryItems.length - 2 && galleryItems.length >= 4" class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/35 text-xs font-bold uppercase tracking-wider text-white">
                    Lihat semua foto
                  </div>
                </div>
              </div>
            </div>

            <!-- Header info -->
            <div class="border-t border-[#e3edf6] px-5 py-5 md:px-6 md:py-6">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto] md:gap-6">
                <div class="min-w-0">
                  <h1 class="text-2xl font-black leading-tight text-[#1a2737] md:text-3xl">{{ property.title }}</h1>
                  <p class="mt-2 flex items-center gap-1.5 text-sm text-[#5f7894]">
                    <UIcon name="i-heroicons-map-pin-solid" class="h-4 w-4 flex-shrink-0 text-[#9ab3cf]" />
                    <span class="truncate">{{ property.locationDetail.address }}</span>
                  </p>
                </div>
                <div class="md:text-right">
                  <div class="inline-flex flex-col rounded-lg bg-gradient-to-br from-[#0c4c9d] to-[#237fd1] px-4 py-2.5 text-white shadow-[0_8px_20px_rgba(18,75,132,0.25)]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Mulai dari</span>
                    <span class="text-xl font-black leading-tight md:text-2xl">{{ formatPrice(property.priceValue) }}</span>
                    <span class="text-[11px] font-semibold text-blue-100">
                      / {{ property.priceType === 'daily' ? 'hari' : property.priceType === 'yearly' ? 'tahun' : 'bulan' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quick stats strip -->
              <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-[#e3edf6] bg-[#f8fbff] px-3 py-2.5">
                  <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#5b779b]">
                    <UIcon name="i-heroicons-home-modern" class="h-3.5 w-3.5" />
                    Tipe
                  </div>
                  <p class="mt-0.5 text-sm font-black text-[#1f3654]">{{ property.propertyType || '-' }}</p>
                </div>
                <div class="rounded-lg border border-[#e3edf6] bg-[#f8fbff] px-3 py-2.5">
                  <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#5b779b]">
                    <UIcon name="i-heroicons-user-group" class="h-3.5 w-3.5" />
                    Jenis Kelamin
                  </div>
                  <p class="mt-0.5 text-sm font-black text-[#1f3654]">{{ property.audience }}</p>
                </div>
                <div class="rounded-lg border border-[#e3edf6] bg-[#f8fbff] px-3 py-2.5">
                  <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#5b779b]">
                    <UIcon name="i-heroicons-key" class="h-3.5 w-3.5" />
                    Ketersediaan
                  </div>
                  <p class="mt-0.5 text-sm font-black text-[#1f3654]">{{ property.availability }}</p>
                </div>
                <div class="rounded-lg border border-[#e3edf6] bg-[#f8fbff] px-3 py-2.5">
                  <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#5b779b]">
                    <UIcon name="i-heroicons-star" class="h-3.5 w-3.5" />
                    Nilai
                  </div>
                  <p class="mt-0.5 text-sm font-black text-[#1f3654]">
                    <template v-if="property.ratingCount > 0">{{ property.rating }} <span class="text-xs font-medium text-neutral-400">({{ property.ratingCount }})</span></template>
                    <template v-else>Baru</template>
                  </p>
                </div>
              </div>

              <!-- Facilities -->
              <div v-if="property.facilitiesDetailed?.length || property.facilities?.length" class="mt-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#5b779b]">Fasilitas</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                  <template v-if="property.facilitiesDetailed?.length">
                    <span
                      v-for="facility in property.facilitiesDetailed"
                      :key="facility.id"
                      class="inline-flex items-center gap-1.5 rounded-md border border-[#cfe0f3] bg-[#eff6fc] px-2.5 py-1 text-xs font-semibold text-[#2c5685]"
                    >
                      <UIcon v-if="facility.icon" :name="facility.icon" class="h-3.5 w-3.5 text-[#3e8ad4]" />
                      {{ facility.name }}
                    </span>
                  </template>
                  <template v-else>
                    <span
                      v-for="facility in property.facilities"
                      :key="facility"
                      class="inline-flex items-center rounded-md border border-[#cfe0f3] bg-[#eff6fc] px-2.5 py-1 text-xs font-semibold text-[#2c5685]"
                    >
                      {{ facility }}
                    </span>
                  </template>
                </div>
              </div>

              <!-- Description -->
              <div class="mt-5 rounded-lg border border-[#dbe8f4] bg-[#f8fbff] p-4">
                <div class="flex items-center gap-2">
                  <UIcon name="i-heroicons-document-text" class="h-4 w-4 text-[#2f7ccc]" />
                  <h2 class="text-sm font-black uppercase tracking-wider text-[#1e3046]">Deskripsi</h2>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-[#536b86]">{{ property.description }}</p>
              </div>
            </div>
          </article>

          <article class="rounded-xl border border-[#d6e2ee] bg-white p-5 shadow-[0_15px_35px_rgba(23,61,98,0.08)] md:p-6">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-xl font-black text-[#1e3046]">Lokasi</h2>
              <span v-if="hasMapCoordinates" class="inline-flex items-center gap-1 rounded-full bg-[#eaf3fc] px-3 py-1 text-[11px] font-bold text-[#2a628f]">
                <UIcon name="i-heroicons-map-pin-solid" class="w-3 h-3" />
                {{ Number(property.latitude).toFixed(4) }}, {{ Number(property.longitude).toFixed(4) }}
              </span>
            </div>
            <p class="mt-2 text-sm font-semibold text-[#345475]">{{ property.locationDetail.address }}</p>
            <p class="mt-1 text-sm text-[#607996]">{{ property.locationDetail.landmark }}</p>
            <div v-if="hasMapCoordinates" class="mt-4 overflow-hidden rounded-lg border border-[#d0e2f2]">
              <div ref="mapRef" class="h-72 w-full vk-detail-map"></div>
            </div>
            <div v-else class="mt-4 overflow-hidden rounded-lg border border-dashed border-[#d0e2f2]">
              <div class="flex h-44 items-center justify-center bg-[radial-gradient(circle_at_15%_20%,#8bb3d6_0,#d8e7f3_42%,#f3f8fc_100%)] text-center">
                <div class="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-[#2a628f]">Koordinat properti belum diatur</div>
              </div>
            </div>
          </article>

          <article class="rounded-xl border border-[#d6e2ee] bg-white p-5 shadow-[0_15px_35px_rgba(23,61,98,0.08)] md:p-6">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-xl font-black text-[#1e3046]">Properti Serupa</h2>
              <button type="button" class="cursor-pointer text-sm font-semibold text-[#2f7ccc]" @click="goBack">Lihat semua</button>
            </div>
            <div v-if="similarProperties.length" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="item in similarProperties"
                :key="item.id"
                class="group relative flex cursor-pointer flex-col overflow-hidden rounded-lg border border-neutral-100 bg-white transition-all duration-300 hover:-translate-y-0.5 hover:border-primary-100 hover:shadow-2xl hover:shadow-primary-500/10"
                @click="openProperty(item.id)"
              >
                <div class="relative h-44 overflow-hidden">
                  <img
                    v-if="item.thumbnailUrl"
                    :src="item.thumbnailUrl"
                    :alt="item.title"
                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                  />
                  <div v-else class="absolute inset-0" :style="{ background: item.cover }"></div>
                  <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/5 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>

                  <div class="absolute left-3 top-3 flex flex-col gap-2">
                    <span v-if="item.isFeatured" class="inline-flex items-center gap-1 rounded-lg bg-amber-400/95 px-2.5 py-1 text-[11px] font-bold text-amber-900 shadow-sm backdrop-blur-sm">
                      <UIcon name="i-heroicons-star-solid" class="w-3 h-3" /> Unggulan
                    </span>
                    <span v-else class="inline-flex items-center rounded-lg bg-neutral-900/70 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur-sm">
                      {{ item.propertyType || 'Properti' }}
                    </span>
                  </div>

                  <span
                    class="absolute right-3 top-3 rounded-xl px-2.5 py-1 text-[11px] font-bold text-white shadow-lg"
                    :class="{
                      'bg-pink-500': item.genderRestriction === 'putri',
                      'bg-blue-500': item.genderRestriction === 'putra',
                      'bg-green-500': !item.genderRestriction || item.genderRestriction === 'campur',
                    }"
                  >
                    {{ item.audience }}
                  </span>
                </div>

                <div class="flex flex-1 flex-col p-4">
                  <h3 class="mb-1 truncate text-base font-bold text-neutral-900 transition-colors group-hover:text-primary-600">{{ item.title }}</h3>
                  <p class="mb-3 flex items-center gap-1 truncate text-xs text-neutral-400">
                    <UIcon name="i-heroicons-map-pin-solid" class="h-3 w-3 flex-shrink-0 text-neutral-300" />
                    {{ item.location }}
                  </p>

                  <p v-if="item.description" class="mb-3 line-clamp-2 text-xs leading-relaxed text-neutral-500">
                    {{ item.description }}
                  </p>

                  <div v-if="item.facilitiesDetailed?.length || item.facilities?.length" class="mb-4 flex flex-wrap gap-1.5">
                    <template v-if="item.facilitiesDetailed?.length">
                      <span
                        v-for="facility in item.facilitiesDetailed.slice(0, 3)"
                        :key="facility.id"
                        class="inline-flex items-center gap-1 rounded-md border border-neutral-100 bg-neutral-50 px-2 py-0.5 text-[11px] font-medium text-neutral-600"
                      >
                        <UIcon v-if="facility.icon" :name="facility.icon" class="h-3 w-3 text-primary-400" />
                        {{ facility.name }}
                      </span>
                      <span v-if="item.facilitiesDetailed.length > 3" class="inline-flex items-center px-1 text-[11px] font-medium text-primary-500">
                        +{{ item.facilitiesDetailed.length - 3 }} lainnya
                      </span>
                    </template>
                    <template v-else>
                      <span
                        v-for="facility in item.facilities.slice(0, 3)"
                        :key="facility"
                        class="inline-flex items-center rounded-md border border-neutral-100 bg-neutral-50 px-2 py-0.5 text-[11px] font-medium text-neutral-600"
                      >
                        {{ facility }}
                      </span>
                      <span v-if="item.facilities.length > 3" class="inline-flex items-center px-1 text-[11px] font-medium text-primary-500">
                        +{{ item.facilities.length - 3 }} lainnya
                      </span>
                    </template>
                  </div>

                  <div class="mt-auto flex items-center justify-between border-t border-neutral-100 pt-3">
                    <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">
                      <UIcon name="i-heroicons-home" class="w-3 h-3" />
                      {{ item.availability }}
                    </span>
                    <div class="text-right">
                      <span class="text-sm font-bold text-primary-600">{{ formatPrice(item.priceValue) }}</span>
                      <span class="ml-0.5 text-xs text-neutral-400">
                        {{ item.priceType === 'daily' ? '/ hari' : item.priceType === 'yearly' ? '/ tahun' : '/ bln' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-[#7088a4]">Belum ada properti serupa untuk kategori ini.</p>
          </article>
        </section>

        <aside class="space-y-5">
          <article class="rounded-xl border border-[#d6e2ee] bg-white p-5 shadow-[0_15px_35px_rgba(23,61,98,0.08)]">
            <h2 class="text-lg font-black text-[#1e3046]">Pemilik Properti</h2>
            <div class="mt-4 rounded-lg bg-[#f4f9fe] p-4">
              <p class="text-base font-bold text-[#26405b]">{{ property.owner.name }}</p>
              <p class="mt-1 text-sm text-[#617b97]">{{ property.owner.role }}</p>
              <p class="mt-2 text-sm font-semibold text-[#31597c]">{{ property.owner.phone }}</p>
              <p class="mt-2 text-xs text-[#6f859f]">{{ property.owner.responseTime }}</p>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-2">
              <button
                type="button"
                class="cursor-pointer rounded-full border border-[#f3bcc9] px-4 py-2 text-sm font-semibold"
                :class="isFavorite ? 'bg-[#f45a78] text-white' : 'bg-white text-[#d94365]'"
                @click="toggleFavorite"
              >
                {{ isFavorite ? 'Tersimpan' : 'Simpan' }}
              </button>
              <button type="button" class="cursor-pointer rounded-full bg-[#2f7ccc] px-4 py-2 text-sm font-semibold text-white" @click="chatOwner">
                Hubungi Pemilik
              </button>
              <button type="button" class="cursor-pointer rounded-full border border-[#c7d9ea] bg-white px-4 py-2 text-sm font-semibold text-[#35597e]" @click="shareProperty">
                Bagikan Properti
              </button>
            </div>
          </article>

          <article class="rounded-xl border border-[#d6e2ee] bg-gradient-to-br from-[#0c4c9d] to-[#237fd1] p-5 text-white shadow-[0_15px_35px_rgba(18,75,132,0.35)]">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Statistik</p>
            <div class="mt-4 space-y-2 text-sm">
              <p>Nilai: <span class="font-bold">{{ ratingSummary }}</span></p>
              <p>Total fasilitas: <span class="font-bold">{{ property.facilities.length }}</span></p>
              <p>Dipublikasi: <span class="font-bold">{{ property.createdAt }}</span></p>
            </div>
          </article>

          <article class="rounded-xl border border-[#d6e2ee] bg-white p-5 shadow-[0_15px_35px_rgba(23,61,98,0.08)]">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 class="text-xl font-black text-[#1e3046]">Ulasan Penghuni</h2>
                <p class="text-xs text-[#7088a4] mt-0.5">{{ ratingSummary }} · {{ property.ratingCount || reviewsForDisplay.length }} ulasan</p>
              </div>
              <template v-if="!isAuthenticated">
                <Link href="/login" class="rounded-full bg-[#2f7ccc] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#1f69ad]">
                  Login untuk Ulasan
                </Link>
              </template>
              <template v-else>
                <button
                  type="button"
                  class="rounded-full bg-[#2f7ccc] px-4 py-2 text-xs font-bold text-white transition"
                  :class="canReview ? 'hover:bg-[#1f69ad]' : 'cursor-not-allowed opacity-50'"
                  :disabled="!canReview"
                  :title="canReview ? '' : (reviewBlockReason || '')"
                  @click="openReviewModal"
                >
                  {{ myReview ? 'Edit Ulasan Saya' : 'Kasih Ulasan' }}
                </button>
              </template>
            </div>

            <p v-if="!isAuthenticated" class="mt-3 rounded-lg border border-dashed border-[#cad9ec] bg-[#f7fbff] p-3 text-xs text-[#577a9d]">
              Login terlebih dahulu untuk memberi ulasan pada properti ini.
            </p>
            <p v-else-if="reviewBlockReason" class="mt-3 rounded-lg border border-dashed border-[#cad9ec] bg-[#f7fbff] p-3 text-xs text-[#577a9d]">
              {{ reviewBlockReason }}
            </p>

            <div v-if="reviewsForDisplay.length" class="mt-4 space-y-3">
              <div
                v-for="review in reviewsForDisplay"
                :key="review.id"
                class="rounded-lg border p-4 transition"
                :class="review.isOwn ? 'border-[#2f7ccc]/40 bg-[#eef6ff]' : 'border-[#dfebf6] bg-[#f9fcff]'"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex min-w-0 items-center gap-3">
                    <img
                      v-if="shouldShowAvatarImage(review)"
                      :src="review.avatarUrl"
                      :alt="`Foto profil ${review.name}`"
                      class="h-10 w-10 rounded-full border border-[#d6e6f6] object-cover"
                      @error="markAvatarFallback(review.id)"
                    />
                    <span
                      v-else
                      class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#d6e6f6] bg-[#e8f1fa] text-sm font-black text-[#2c5f8e]"
                    >
                      {{ review.initial }}
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-sm font-bold text-[#26405b]">
                        {{ review.name }}
                        <span v-if="review.isOwn" class="ml-1 rounded-full bg-[#2f7ccc] px-2 py-0.5 text-[10px] font-bold uppercase text-white">Ulasan Anda</span>
                      </p>
                      <p class="mt-0.5 text-xs text-[#7d92aa]">{{ review.date }}</p>
                    </div>
                  </div>
                  <span class="text-xs font-bold text-[#2e7ac4]">★ {{ review.rating }}</span>
                </div>
                <p v-if="review.text" class="mt-2 text-sm text-[#5f7894]">{{ review.text }}</p>
                <p v-else class="mt-2 text-xs italic text-[#9aaec5]">Tidak ada komentar.</p>
                <div class="mt-3 flex justify-end gap-2">
                  <template v-if="review.isOwn">
                    <button
                      type="button"
                      class="cursor-pointer rounded-full border border-[#cfe0f3] bg-white px-3 py-1.5 text-xs font-semibold text-[#2f7ccc] transition hover:border-[#2f7ccc]"
                      @click="openReviewModal"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      class="cursor-pointer rounded-full border border-[#f0c8c8] bg-white px-3 py-1.5 text-xs font-semibold text-[#b04a4a] transition hover:border-[#b04a4a]"
                      @click="deleteMyReview"
                    >
                      Hapus
                    </button>
                  </template>
                  <button
                    v-else
                    type="button"
                    class="cursor-pointer rounded-full border border-[#d8e4ef] bg-white px-3 py-1.5 text-xs font-semibold text-[#476887] transition hover:border-[#c0d6ea] hover:text-[#2e567a]"
                    @click="openReportModal(review)"
                  >
                    Laporkan Ulasan
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-[#7088a4]">Belum ada ulasan untuk properti ini.</p>
          </article>
        </aside>
      </div>
    </main>

    <Transition name="flash-fade">
      <div v-if="flash.visible" class="fixed right-5 top-5 z-40 max-w-xs rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg" :class="flashClasses">
        {{ flash.message }}
      </div>
    </Transition>

    <UModal v-model:open="isReviewModalOpen" :title="myReview ? 'Edit Ulasan' : 'Kasih Ulasan'" :ui="{ footer: 'justify-end' }">
      <template #body>
        <div class="space-y-4">
          <p class="text-sm text-[#5f7894]">{{ myReview ? 'Perbarui ulasan Anda untuk properti ini.' : 'Bagikan pengalamanmu setelah tinggal di properti ini.' }}</p>

          <label class="block">
            <span class="text-sm font-semibold text-[#1f3d5a]">Rating</span>
            <div class="mt-2 flex flex-wrap gap-2">
              <button
                v-for="score in [1, 2, 3, 4, 5]"
                :key="`rating-${score}`"
                type="button"
                class="cursor-pointer rounded-full border px-3 py-1 text-sm font-bold transition"
                :class="reviewForm.rating === score ? 'border-[#2f7ccc] bg-[#2f7ccc] text-white' : 'border-[#c8d9ea] bg-white text-[#446685]'"
                @click="reviewForm.rating = score"
              >
                {{ score }} ★
              </button>
            </div>
          </label>

          <label class="block">
            <span class="text-sm font-semibold text-[#1f3d5a]">Komentar</span>
            <textarea
              v-model="reviewForm.comment"
              rows="4"
              class="mt-2 w-full rounded-xl border border-[#c9dced] bg-white px-3 py-2 text-sm text-[#2c4966] outline-none transition focus:border-[#2f7ccc]"
              placeholder="Tulis ulasan kamu di sini..."
            />
          </label>

          <p v-if="reviewFormError" class="text-xs font-semibold text-[#b33a52]">{{ reviewFormError }}</p>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <button
            type="button"
            class="cursor-pointer rounded-full border border-[#c8d9ea] bg-white px-4 py-2 text-sm font-semibold text-[#456786]"
            @click="closeReviewModal"
          >
            Batal
          </button>
          <button
            type="button"
            class="cursor-pointer rounded-full bg-[#2f7ccc] px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="reviewForm.processing"
            @click="submitReview"
          >
            {{ reviewForm.processing ? 'Mengirim...' : (myReview ? 'Simpan Perubahan' : 'Kirim Ulasan') }}
          </button>
        </div>
      </template>
    </UModal>

    <UModal v-model:open="isReportModalOpen" title="Laporkan Ulasan" :ui="{ footer: 'justify-end' }">
      <template #body>
        <div class="space-y-4">
          <p class="text-sm text-[#5f7894]">
            Kamu sedang melaporkan ulasan dari <span class="font-bold text-[#1f3d5a]">{{ reportTargetReview?.name }}</span>.
          </p>

          <label class="block">
            <span class="text-sm font-semibold text-[#1f3d5a]">Alasan laporan</span>
            <select
              v-model="reportForm.reason"
              class="mt-2 w-full rounded-xl border border-[#c9dced] bg-white px-3 py-2 text-sm text-[#2c4966] outline-none transition focus:border-[#2f7ccc]"
            >
              <option v-for="reason in reportReasons" :key="reason" :value="reason">{{ reason }}</option>
            </select>
          </label>

          <label class="block">
            <span class="text-sm font-semibold text-[#1f3d5a]">Catatan tambahan (opsional)</span>
            <textarea
              v-model="reportForm.note"
              rows="3"
              class="mt-2 w-full rounded-xl border border-[#c9dced] bg-white px-3 py-2 text-sm text-[#2c4966] outline-none transition focus:border-[#2f7ccc]"
              placeholder="Tambahkan konteks jika diperlukan"
            />
          </label>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <button
            type="button"
            class="cursor-pointer rounded-full border border-[#c8d9ea] bg-white px-4 py-2 text-sm font-semibold text-[#456786]"
            @click="closeReportModal"
          >
            Batal
          </button>
          <button
            type="button"
            class="cursor-pointer rounded-full bg-[#dd5f5f] px-4 py-2 text-sm font-semibold text-white"
            @click="submitReportReview"
          >
            Kirim Laporan
          </button>
        </div>
      </template>
    </UModal>

    <GlobalChatFloatingButton />
  </div>
</template>

<style scoped>
.detail-shell {
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

<style>
.vk-detail-map { background: linear-gradient(180deg, #dbeafe 0%, #eff6ff 100%); }
.vk-pin-wrapper { background: transparent; border: none; }
.vk-pin { width: 28px; height: 38px; position: relative; filter: drop-shadow(0 4px 10px rgba(2,132,199,0.4)); cursor: pointer; }
.vk-pin::before { content: ''; position: absolute; inset: 0; background: linear-gradient(160deg, #38bdf8 0%, #0284c7 100%); clip-path: polygon(50% 100%, 0% 30%, 7% 10%, 20% 2%, 50% 0%, 80% 2%, 93% 10%, 100% 30%); }
.vk-pin__inner { position: absolute; width: 9px; height: 9px; background: white; border-radius: 9999px; top: 8px; left: 50%; transform: translateX(-50%); opacity: 0.95; }
.vk-pin--featured::before { background: linear-gradient(160deg, #fbbf24 0%, #d97706 100%); }
.vk-pin--featured { filter: drop-shadow(0 4px 10px rgba(217,119,6,0.45)); }
</style>
