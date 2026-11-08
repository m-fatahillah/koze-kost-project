<script setup>
import { nextTick, ref, watch } from 'vue'
import { useGlobalChat } from '@/composables/useGlobalChat'

const {
  quickReplyOptions,
  maxChatAttachmentCount,
  isChatDrawerOpen,
  chatMobilePanel,
  chatDraftMessage,
  chatDraftAttachments,
  chatConversations,
  drawerContextTitle,
  activeChatId,
  activeConversation,
  canSendChatMessage,
  closeDrawer,
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

const chatAttachmentInput = ref(null)
const chatComposerRef = ref(null)
const uploadFeedback = ref('')

watch(isChatDrawerOpen, async (isOpen) => {
  if (!isOpen) {
    uploadFeedback.value = ''
    return
  }

  await nextTick()
  chatComposerRef.value?.focus()
})

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
  <Transition name="chat-overlay-fade">
    <div v-if="isChatDrawerOpen" class="fixed inset-0 z-40 bg-[#0f2237]/45 backdrop-blur-[1px]" @click="closeDrawer" />
  </Transition>

  <Transition name="chat-drawer-slide">
    <aside
      v-if="isChatDrawerOpen"
      class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[980px] flex-col border-l border-[#cfe0ef] bg-[#eef4fb] shadow-[-18px_0_40px_rgba(14,49,82,0.28)]"
    >
      <header class="flex items-center justify-between gap-3 border-b border-[#d7e6f3] bg-[#f7fbff] px-4 py-3 md:px-5">
        <div class="min-w-0">
          <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#4e7aa0]">Chat Owner</p>
          <p class="truncate text-sm font-bold text-[#1f3f5d]">{{ drawerContextTitle }}</p>
        </div>
        <button
          type="button"
          class="cursor-pointer rounded-full border border-[#c9dced] bg-white px-3 py-1.5 text-xs font-semibold text-[#365d81]"
          @click="closeDrawer"
        >
          Tutup
        </button>
      </header>

      <div class="min-h-0 flex-1 md:grid md:grid-cols-[320px_1fr]">
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
                <p class="shrink-0 text-[11px] font-semibold text-[#7b93ab]">{{ getLastMessage(conversation)?.timestamp || '-' }}</p>
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
                  <p class="truncate text-sm font-bold text-[#1f3f5d]">{{ activeConversation.name }}</p>
                  <p class="truncate text-xs text-[#6b87a2]">{{ activeConversation.role }} - {{ activeConversation.responseTime }}</p>
                </div>
              </div>
            </header>

            <div class="chat-scroll flex-1 space-y-3 overflow-y-auto p-4 md:p-5">
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
    </aside>
  </Transition>
</template>

<style scoped>
.chat-overlay-fade-enter-active,
.chat-overlay-fade-leave-active {
  transition: opacity 0.2s ease;
}

.chat-overlay-fade-enter-from,
.chat-overlay-fade-leave-to {
  opacity: 0;
}

.chat-drawer-slide-enter-active,
.chat-drawer-slide-leave-active {
  transition: transform 0.25s ease;
}

.chat-drawer-slide-enter-from,
.chat-drawer-slide-leave-to {
  transform: translateX(100%);
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
