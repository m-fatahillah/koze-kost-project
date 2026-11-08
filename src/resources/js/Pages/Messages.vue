<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useGlobalChat } from '@/composables/useGlobalChat'
import { useSavedProperties } from '@/composables/useSavedProperties'

const page = usePage()

const {
  quickReplyOptions,
  maxChatAttachmentCount,
  chatMobilePanel,
  chatDraftMessage,
  chatDraftAttachments,
  chatConversations,
  activeChatId,
  activeConversation,
  canSendChatMessage,
  selectConversation,
  showConversationListOnMobile,
  removeAttachment,
  addAttachments,
  applyQuickReply,
  sendMessage,
  getLastMessage,
  getConversationPreview,
  getConversationStatusLabel,
  isLastOutgoingMessage,
  formatFileSize,
} = useGlobalChat()

const flash = ref({
  visible: false,
  message: '',
  tone: 'info',
})
const chatAttachmentInput = ref(null)
const chatComposerRef = ref(null)
const chatMessagesContainer = ref(null)
const uploadFeedback = ref('')
let flashTimer

const scrollToBottom = async () => {
  await nextTick()
  const container = chatMessagesContainer.value
  if (container) {
    container.scrollTop = container.scrollHeight
  }
}

watch(
  () => activeConversation.value?.messages?.length,
  () => {
    scrollToBottom()
  },
  { immediate: true },
)

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const userInitial = computed(() => {
  const userName = page.props.auth?.user?.name || 'Guest'
  return userName.charAt(0).toUpperCase()
})

const { savedCount } = useSavedProperties()

const isOwner = computed(() => {
  const userRoles = page.props.auth?.user?.roles || []
  return userRoles.includes('Owner')
})

const navItems = computed(() => {
  if (isOwner.value) {
    return [
      { key: 'Dashboard', label: 'Dasbor', path: '/dashboard' },
      { key: 'Properties', label: 'Properti', path: '/properties' },
      { key: 'FeaturedListing', label: 'Iklan Eksklusif', path: '/featured-listing' },
      { key: 'Messages', label: 'Pesan', path: '/messages' },
    ]
  }

  return [
    { key: 'Discover', label: 'Jelajahi', path: '/discover' },
    { key: 'Saved', label: `Tersimpan (${savedCount.value})`, path: '/saved' },
    { key: 'Messages', label: 'Pesan', path: '/messages' },
  ]
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

watch(
  () => activeChatId.value,
  async () => {
    await nextTick()
    chatComposerRef.value?.focus()
  },
  { immediate: true },
)

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

const setActiveNav = (item) => {
  if (item.key === 'Messages') {
    return
  }

  router.visit(item.path)
}

const triggerAttachmentPicker = () => {
  chatAttachmentInput.value?.click()
}

const handleAttachmentSelection = (event) => {
  const selectedFiles = Array.from(event.target.files || [])

  if (!selectedFiles.length) {
    return
  }

  const result = addAttachments(selectedFiles)

  if (result.blocked) {
    uploadFeedback.value = `Maksimal ${maxChatAttachmentCount} lampiran per pesan.`
  } else if (result.truncated) {
    uploadFeedback.value = `Sebagian file tidak ditambahkan. Batas lampiran ${maxChatAttachmentCount} file.`
  } else {
    uploadFeedback.value = ''
  }

  event.target.value = ''
}

const handleQuickReply = async (replyText) => {
  applyQuickReply(replyText)
  await nextTick()
  chatComposerRef.value?.focus()
}

const handleSendMessage = async () => {
  const isSent = sendMessage()

  if (!isSent) {
    return
  }

  uploadFeedback.value = ''
  await nextTick()
  chatComposerRef.value?.focus()
}

const handleComposerKeydown = (event) => {
  if (event.key !== 'Enter') {
    return
  }

  if (!event.ctrlKey && !event.metaKey) {
    return
  }

  event.preventDefault()
  handleSendMessage()
}
</script>

<template>
  <Head title="Pesan" />

  <div class="messages-shell min-h-screen bg-[#eff3f7]">
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
                :class="item.key === 'Messages' ? 'border-[#2f7ccc] text-[#1b4b9f]' : 'border-transparent text-[#63738c] hover:text-[#2b4f76]'"
                @click="setActiveNav(item)"
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
              Kembali ke Jelajahi
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
        <section class="overflow-hidden rounded-3xl border border-[#d6e2ee] bg-[#eef4fb] shadow-[0_15px_35px_rgba(23,61,98,0.08)]">
          <header class="border-b border-[#d7e6f3] bg-[#f7fbff] px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#4e7aa0]">Pesan</p>
            <h1 class="mt-1 text-xl font-black text-[#1f3f5d]">Kotak Masuk</h1>
          </header>

          <div class="min-h-[72vh] md:grid md:grid-cols-[340px_1fr]">
            <section
              class="min-h-0 flex-col border-b border-[#d7e6f3] bg-[#f7fbff] md:border-b-0 md:border-r"
              :class="chatMobilePanel === 'chat' ? 'hidden md:flex' : 'flex'"
            >
              <div class="border-b border-[#e0ebf5] px-4 py-3">
                <p class="text-sm font-black text-[#1f3f5d]">Daftar Percakapan</p>
                <p class="mt-1 text-xs text-[#6b87a2]">Pilih kontak untuk mulai chat.</p>
              </div>

              <div class="chat-scroll flex-1 space-y-2 overflow-y-auto px-3 py-3">
                <button
                  v-for="conversation in chatConversations"
                  :key="conversation.id"
                  type="button"
                  class="w-full cursor-pointer rounded-2xl border px-3 py-3 text-left transition"
                  :class="conversation.id === activeChatId
                    ? 'border-[#2f7ccc] bg-[#e8f3ff] shadow-[0_8px_20px_rgba(47,124,204,0.12)]'
                    : 'border-[#d5e4f2] bg-white hover:border-[#bed6eb]'"
                  @click="selectConversation(conversation.id)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="truncate text-sm font-bold text-[#244767]">{{ conversation.name }}</p>
                      <p class="truncate text-xs text-[#67839d]">{{ conversation.role }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                      <p class="text-[11px] font-semibold text-[#7b93ab]">{{ getLastMessage(conversation)?.timestamp || '-' }}</p>
                      <span
                        v-if="conversation.unread_count > 0"
                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#2f7ccc] px-1.5 text-[10px] font-bold text-white"
                      >
                        {{ conversation.unread_count }}
                      </span>
                    </div>
                  </div>
                  <p class="mt-2 truncate text-xs text-[#516f8c]">{{ getConversationPreview(conversation) }}</p>
                  <p v-if="getConversationStatusLabel(conversation)" class="mt-1 text-[11px] font-semibold text-[#2f7ccc]">
                    {{ getConversationStatusLabel(conversation) }}
                  </p>
                </button>
              </div>
            </section>

            <section class="min-h-0 flex-1 flex-col" :class="chatMobilePanel === 'chat' ? 'flex' : 'hidden md:flex'">
              <template v-if="activeConversation">
                <header class="flex items-center justify-between gap-3 border-b border-[#d7e6f3] bg-white px-4 py-3 md:px-5">
                  <div class="flex min-w-0 items-center gap-2">
                    <button
                      type="button"
                      class="cursor-pointer rounded-full border border-[#cfe0ef] bg-[#f4f9fe] px-2 py-1 text-xs font-semibold text-[#2e587f] md:hidden"
                      @click="showConversationListOnMobile"
                    >
                      Kembali
                    </button>
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-bold text-[#1f3f5d]">{{ activeConversation.name }}</p>
                        <span
                          v-if="activeConversation.responseTime === 'Online'"
                          class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.5)]"
                        />
                      </div>
                      <p class="truncate text-xs text-[#6b87a2]">{{ activeConversation.role }} - {{ activeConversation.responseTime }}</p>
                    </div>
                  </div>
                </header>

                <div ref="chatMessagesContainer" class="chat-scroll flex-1 space-y-3 overflow-y-auto p-4 md:p-5">
                  <div v-if="!activeConversation.messages.length" class="rounded-2xl border border-dashed border-[#cddff0] bg-white p-4 text-center text-sm text-[#64819d]">
                    Belum ada pesan. Mulai percakapan pertamamu sekarang.
                  </div>

                  <div
                    v-for="(message, index) in activeConversation.messages"
                    :key="message.id"
                    class="flex flex-col gap-1"
                    :class="message.sender === 'user' ? 'items-end' : 'items-start'"
                  >
                    <div
                      class="max-w-[86%] rounded-2xl px-3 py-2 text-sm shadow-[0_6px_14px_rgba(35,74,111,0.09)]"
                      :class="message.sender === 'user'
                        ? 'rounded-br-md bg-[#2f7ccc] text-white'
                        : 'rounded-bl-md border border-[#d8e6f3] bg-white text-[#264564]'"
                    >
                      <p v-if="message.text" class="whitespace-pre-line leading-relaxed">{{ message.text }}</p>

                      <div v-if="message.attachments?.length" class="mt-2 space-y-1.5">
                        <div
                          v-for="attachment in message.attachments"
                          :key="attachment.id"
                          class="flex items-center justify-between gap-2 rounded-xl px-2 py-1 text-[11px]"
                          :class="message.sender === 'user' ? 'bg-white/15 text-white' : 'bg-[#edf5fc] text-[#365777]'"
                        >
                          <span class="truncate">{{ attachment.name }}</span>
                          <span class="shrink-0 font-semibold">{{ formatFileSize(attachment.size) }}</span>
                        </div>
                      </div>
                    </div>

                    <p class="px-1 text-[11px] text-[#6f87a1]">{{ message.timestamp }}</p>
                    <p
                      v-if="message.sender === 'user' && isLastOutgoingMessage(activeConversation.messages, index)"
                      class="px-1 text-[11px] font-semibold text-[#2f7ccc]"
                    >
                      {{ message.status === 'seen' ? 'Dilihat' : 'Terkirim' }}
                    </p>
                  </div>
                </div>

                <div class="border-t border-[#d7e6f3] bg-white p-3 md:p-4">
                  <div class="chat-scroll mb-3 flex gap-2 overflow-x-auto pb-1">
                    <button
                      v-for="reply in quickReplyOptions"
                      :key="reply"
                      type="button"
                      class="shrink-0 cursor-pointer rounded-full border border-[#c7dcf0] bg-[#f4faff] px-3 py-1.5 text-xs font-semibold text-[#2d638f] transition hover:border-[#9ec2e2]"
                      @click="handleQuickReply(reply)"
                    >
                      {{ reply }}
                    </button>
                  </div>

                  <div v-if="chatDraftAttachments.length" class="mb-3 flex flex-wrap gap-2">
                    <div
                      v-for="attachment in chatDraftAttachments"
                      :key="attachment.id"
                      class="inline-flex items-center gap-2 rounded-full border border-[#cae0f1] bg-[#f2f8fe] px-3 py-1 text-[11px] text-[#2f5e86]"
                    >
                      <span class="max-w-[170px] truncate">{{ attachment.name }}</span>
                      <span class="font-semibold">{{ formatFileSize(attachment.size) }}</span>
                      <button
                        type="button"
                        class="cursor-pointer rounded-full bg-[#d7e8f6] px-1.5 text-[10px] font-bold text-[#2f5e86]"
                        @click="removeAttachment(attachment.id)"
                      >
                        x
                      </button>
                    </div>
                  </div>

                  <textarea
                    ref="chatComposerRef"
                    v-model="chatDraftMessage"
                    rows="3"
                    class="w-full rounded-2xl border border-[#c9dced] bg-[#f9fcff] px-3 py-2 text-sm text-[#2c4966] outline-none transition focus:border-[#2f7ccc]"
                    placeholder="Tulis pesan ke owner..."
                    @keydown="handleComposerKeydown"
                  />

                  <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                      <input
                        ref="chatAttachmentInput"
                        type="file"
                        class="hidden"
                        multiple
                        accept="image/*,.pdf,.doc,.docx"
                        @change="handleAttachmentSelection"
                      >
                      <button
                        type="button"
                        class="cursor-pointer rounded-full border border-[#c7d9ea] bg-[#f6fbff] px-3 py-1.5 text-xs font-semibold text-[#35597e]"
                        @click="triggerAttachmentPicker"
                      >
                        Unggah File
                      </button>
                      <p class="text-[11px] text-[#7d94aa]">Maks. {{ maxChatAttachmentCount }} file</p>
                    </div>

                    <button
                      type="button"
                      class="cursor-pointer rounded-full px-4 py-2 text-sm font-semibold text-white transition"
                      :class="canSendChatMessage ? 'bg-[#2f7ccc] hover:bg-[#216caf]' : 'bg-[#9bb8d4] cursor-not-allowed'"
                      :disabled="!canSendChatMessage"
                      @click="handleSendMessage"
                    >
                      Kirim
                    </button>
                  </div>

                  <p class="mt-2 text-[11px] text-[#7f95ab]">Gunakan Ctrl + Enter untuk kirim cepat.</p>
                  <p v-if="uploadFeedback" class="mt-1 text-[11px] font-semibold text-[#8a5b12]">{{ uploadFeedback }}</p>
                </div>
              </template>

              <div v-else class="flex flex-1 items-center justify-center p-6 text-center text-sm text-[#6d86a0]">
                Pilih percakapan untuk mulai chat dengan owner.
              </div>
            </section>
          </div>
        </section>
      </main>
    </div>

    <Transition name="flash-fade">
      <div v-if="flash.visible" class="fixed right-5 top-5 z-40 max-w-xs rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg" :class="flashClasses">
        {{ flash.message }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.messages-shell {
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

.chat-scroll {
  scrollbar-width: thin;
  scrollbar-color: #b7cce0 transparent;
}

.chat-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.chat-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.chat-scroll::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #b7cce0;
}
</style>
