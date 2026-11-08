<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()
const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

const handleOpenGlobalChat = () => {
  if (!isAuthenticated.value) {
    router.visit('/login')
    return
  }

  router.visit('/messages')
}
</script>

<template>
  <div class="fixed bottom-6 right-6 z-30">
    <button
      type="button"
      class="flex size-14 cursor-pointer items-center justify-center rounded-full bg-[#2f7ccc] text-white shadow-[0_14px_30px_rgba(47,124,204,0.36)] transition hover:scale-105 hover:bg-[#1f69ad]"
      @click="handleOpenGlobalChat"
    >
      <UIcon name="i-heroicons-chat-bubble-oval-left-ellipsis-solid" class="size-7" />
    </button>
  </div>
</template>
