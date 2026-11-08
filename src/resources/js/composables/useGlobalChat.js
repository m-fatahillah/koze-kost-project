import { computed, ref, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const quickReplyOptions = ['Masih tersedia?', 'Bisa survey dulu?', 'Unitnya masih kosong?']
const maxChatAttachmentCount = 3

const isChatDrawerOpen = ref(false)
const chatMobilePanel = ref('list')
const chatDraftMessage = ref('')
const chatDraftAttachments = ref([])
const drawerContextTitle = ref('Messages')

let pollInterval = null
let heartbeatInterval = null

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

const removeAttachment = (attachmentId) => {
  chatDraftAttachments.value = chatDraftAttachments.value.filter((attachment) => attachment.id !== attachmentId)
}

const buildAttachmentEntry = (file) => {
  return {
    id: `file-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    name: file.name,
    size: file.size,
    type: file.type,
  }
}

const addAttachments = (files) => {
  const remainingSlots = maxChatAttachmentCount - chatDraftAttachments.value.length

  if (remainingSlots <= 0) {
    return {
      blocked: true,
      truncated: false,
      addedCount: 0,
    }
  }

  const acceptedFiles = files.slice(0, remainingSlots).map((file) => buildAttachmentEntry(file))
  chatDraftAttachments.value = [...chatDraftAttachments.value, ...acceptedFiles]

  return {
    blocked: false,
    truncated: files.length > remainingSlots,
    addedCount: acceptedFiles.length,
  }
}

const applyQuickReply = (replyText) => {
  if (chatDraftMessage.value.trim()) {
    chatDraftMessage.value = `${chatDraftMessage.value.trim()}\n${replyText}`
    return
  }

  chatDraftMessage.value = replyText
}

const closeDrawer = () => {
  isChatDrawerOpen.value = false
  chatMobilePanel.value = 'list'
  stopPolling()
}

const openDrawer = (options = {}) => {
  drawerContextTitle.value = options.title || 'Messages'
  isChatDrawerOpen.value = true
  chatMobilePanel.value = 'list'
}

const showConversationListOnMobile = () => {
  chatMobilePanel.value = 'list'
}

const stopPolling = () => {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }

  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
    heartbeatInterval = null
  }
}

export function useGlobalChat() {
  const page = usePage()
  const user = computed(() => page.props.auth?.user)

  const chatConversations = computed(() => {
    return (page.props.conversations || []).map((conv) => ({
      id: conv.id,
      name: conv.other_user.name,
      role: conv.my_role === 'seeker'
        ? (conv.property ? `Pemilik ${conv.property.title}` : 'Pemilik Kos')
        : (conv.property ? `Calon Penyewa • ${conv.property.title}` : 'Calon Penyewa'),
      responseTime: conv.other_user.is_online ? 'Online' : 'Offline',
      avatar_url: conv.other_user.avatar_url,
      unread_count: conv.unread_count,
      last_message: conv.last_message,
      messages: conv.last_message
        ? [
            {
              id: conv.last_message.id,
              sender: conv.last_message.sender_id == user.value?.id ? 'user' : 'owner',
              text: conv.last_message.content,
              timestamp: formatChatTime(new Date(conv.last_message.created_at)),
              status: conv.last_message.is_read ? 'seen' : 'sent',
              attachments: [],
            },
          ]
        : [],
    }))
  })

  const activeChatId = computed(() => page.props.activeConversation?.id || null)

  const activeConversation = computed(() => {
    if (!page.props.activeConversation) {
      return null
    }

    const conv = page.props.activeConversation
    const msgs = page.props.messages || []

    return {
      id: conv.id,
      name: conv.other_user.name,
      role: conv.my_role === 'seeker'
        ? (conv.property ? `Pemilik ${conv.property.title}` : 'Pemilik Kos')
        : (conv.property ? `Calon Penyewa • ${conv.property.title}` : 'Calon Penyewa'),
      responseTime: conv.other_user.is_online ? 'Online' : 'Offline',
      avatar_url: conv.other_user.avatar_url,
      messages: msgs.map((msg) => ({
        id: msg.id,
        sender: msg.sender_id == user.value?.id ? 'user' : 'owner',
        text: msg.content,
        timestamp: formatChatTime(new Date(msg.created_at)),
        status: msg.is_read ? 'seen' : 'sent',
        attachments: [],
      })),
    }
  })

  const canSendChatMessage = computed(() => {
    return Boolean(chatDraftMessage.value.trim()) || chatDraftAttachments.value.length > 0
  })

  const selectConversation = (conversationId) => {
    chatMobilePanel.value = 'chat'
    router.visit(`/messages/${conversationId}`)
  }

  const sendMessage = () => {
    if (!canSendChatMessage.value || !activeChatId.value) {
      return false
    }

    router.post(
      '/messages',
      {
        conversation_id: activeChatId.value,
        content: chatDraftMessage.value.trim(),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          chatDraftMessage.value = ''
          chatDraftAttachments.value = []
        },
      },
    )

    return true
  }

  const openOwnerConversation = (ownerContext) => {
    router.post(
      '/conversations',
      {
        owner_id: ownerContext.ownerId,
        property_id: ownerContext.propertyId,
      },
      {
        preserveScroll: true,
      },
    )
  }

  const startPolling = () => {
    if (pollInterval) {
      return
    }

    pollInterval = setInterval(() => {
      const only = ['conversations']

      if (activeChatId.value) {
        only.push('messages')
      }

      router.reload({ only, preserveScroll: true })
    }, 3000)

    heartbeatInterval = setInterval(() => {
      const token = document.querySelector('meta[name="csrf-token"]')?.content
      if (!token) return

      fetch('/heartbeat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
      }).catch(() => {})
    }, 30000)
  }

  onMounted(() => {
    startPolling()
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
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
    closeDrawer,
    openDrawer,
    openOwnerConversation,
    startPolling,
    stopPolling,
  }
}
