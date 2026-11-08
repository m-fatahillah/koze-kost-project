<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const userId = computed(() => page.props.auth?.user?.id || 'guest');

const storageKey = computed(() => `koze_ai_chat_history_${userId.value}`);

const DEFAULT_WELCOME_MESSAGE = {
    role: 'assistant',
    content: 'Halo! Saya **Koze AI** ✨\n\nSaya bisa bantu carikan kos sesuai preferensimu. Mau mulai dari mana — lokasi, budget, atau tipe penghuni?',
    id: 0,
};

const isOpen = ref(false);
const inputText = ref('');
const isLoading = ref(false);
const messagesEl = ref(null);

const messages = ref([DEFAULT_WELCOME_MESSAGE]);

// Load chat history from the database
const loadHistory = async () => {
    if (!isAuthenticated.value) return;
    try {
        const res = await fetch('/chat/history');
        if (!res.ok) throw new Error('Failed to load chat history');
        const data = await res.json();
        if (Array.isArray(data.history) && data.history.length > 0) {
            messages.value = [DEFAULT_WELCOME_MESSAGE, ...data.history];
            return;
        }
    } catch (e) {
        console.error('Failed to load chat history:', e);
    }
    messages.value = [DEFAULT_WELCOME_MESSAGE];
};

// Clear chat history in the database
const clearHistory = async () => {
    if (confirm('Apakah Anda yakin ingin menghapus semua riwayat percakapan dengan Koze AI?')) {
        try {
            const res = await fetch('/chat/clear', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('Gagal menghapus riwayat');
            messages.value = [
                {
                    ...DEFAULT_WELCOME_MESSAGE,
                    id: Date.now()
                }
            ];
            nextTick(scrollToBottom);
        } catch (e) {
            alert(e.message || 'Terjadi kesalahan saat menghapus riwayat.');
        }
    }
};

// Watch for authentication state to load/reset history
watch(isAuthenticated, (newVal) => {
    if (newVal) {
        loadHistory();
    } else {
        messages.value = [DEFAULT_WELCOME_MESSAGE];
    }
}, { immediate: true });

// Auto-scroll to bottom on opening chat
watch(isOpen, async (newVal) => {
    if (newVal) {
        await nextTick();
        scrollToBottom();
    }
});

const scrollToBottom = () => {
    if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
};

const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const renderContent = (text) => {
    let html = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="text-blue-600 underline font-medium hover:text-blue-800">$1</a>');
    html = html.replace(/\n/g, '<br>');
    return html;
};

const sendMessage = async () => {
    const text = inputText.value.trim();
    if (!text || isLoading.value) return;

    messages.value.push({ role: 'user', content: text, id: Date.now() });
    inputText.value = '';
    isLoading.value = true;
    await nextTick();
    scrollToBottom();

    try {
        const res = await fetch('/chat/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: text,
            }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Gagal menghubungi AI');
        
        // Update user message ID and append assistant message
        if (data.userMsgId && messages.value.length > 0) {
            messages.value[messages.value.length - 1].id = data.userMsgId;
        }
        messages.value.push({ role: 'assistant', content: data.reply, id: data.aiMsgId || Date.now() });
    } catch (err) {
        messages.value.push({
            role: 'assistant',
            content: err.message || 'Maaf, terjadi kesalahan. Coba lagi ya!',
            id: Date.now() + 1,
        });
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const onKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

const formatTime = (id) => {
    if (!id) return '';
    const d = new Date(id);
    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
};

const autoResize = (e) => {
    e.target.style.height = 'auto';
    e.target.style.height = e.target.scrollHeight + 'px';
};
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col items-end">
        <!-- Chat Window -->
        <transition
            enter-active-class="transition ease-out duration-300 transform origin-bottom-right"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-200 transform origin-bottom-right"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
        >
            <div
                v-if="isOpen"
                class="mb-3 w-[calc(100vw-2rem)] sm:w-[380px] h-[580px] max-h-[calc(100vh-5rem)] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-100"
            >
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-4 py-3 flex justify-between items-center text-white shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a3.75 3.75 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a3.75 3.75 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0116.5 15z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm leading-tight">Koze AI</h3>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span>
                                <span class="text-[11px] text-blue-100">Asisten pencari kos</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <!-- Reset / Clear History Button -->
                        <button
                            v-if="isAuthenticated && messages.length > 1"
                            class="text-blue-100 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10"
                            title="Hapus Riwayat Chat"
                            @click="clearHistory"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                        <!-- Close Button -->
                        <button
                            class="text-blue-100 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10"
                            @click="isOpen = false"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Auth gate -->
                <div v-if="!isAuthenticated" class="flex-1 flex flex-col items-center justify-center gap-5 p-8 bg-gray-50">
                    <div class="w-16 h-16 rounded-2xl bg-blue-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-blue-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <p class="font-bold text-gray-800 text-base mb-1">Login untuk mulai chat</p>
                        <p class="text-sm text-gray-500 leading-relaxed">Koze AI hanya tersedia untuk pengguna yang sudah login.</p>
                    </div>
                    <a href="/login" class="w-full text-center py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm transition-colors">
                        Masuk Sekarang
                    </a>
                    <a href="/register" class="text-xs text-blue-500 hover:underline">Belum punya akun? Daftar gratis</a>
                </div>

                <!-- Chat area -->
                <template v-else>
                    <div ref="messagesEl" class="flex-1 overflow-y-auto bg-gray-50 px-4 py-4 flex flex-col gap-3">
                        <template v-for="msg in messages" :key="msg.id">
                            <!-- AI bubble -->
                            <div v-if="msg.role === 'assistant'" class="flex flex-col gap-1 self-start max-w-[88%]">
                                <div class="flex items-start gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-blue-600">
                                            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div
                                        class="bg-white border border-gray-100 text-gray-800 rounded-2xl rounded-tl-sm px-3.5 py-2.5 shadow-sm text-sm leading-relaxed"
                                        v-html="renderContent(msg.content)"
                                    ></div>
                                </div>
                                <span v-if="msg.id" class="text-[10px] text-gray-400 ml-8">{{ formatTime(msg.id) }}</span>
                            </div>

                            <!-- User bubble -->
                            <div v-else class="flex flex-col gap-1 self-end max-w-[88%]">
                                <div class="bg-blue-600 text-white rounded-2xl rounded-tr-sm px-3.5 py-2.5 text-sm leading-relaxed">
                                    {{ msg.content }}
                                </div>
                                <span class="text-[10px] text-gray-400 mr-1 self-end">{{ formatTime(msg.id) }}</span>
                            </div>
                        </template>

                        <!-- Typing indicator -->
                        <div v-if="isLoading" class="flex items-start gap-2 self-start">
                            <div class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-blue-600">
                                    <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:0ms"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:150ms"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:300ms"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Input -->
                    <div class="p-3 bg-white border-t border-gray-100 shrink-0">
                        <div class="flex items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100 transition">
                            <textarea
                                v-model="inputText"
                                rows="1"
                                placeholder="Ketik pesan kamu..."
                                class="flex-1 bg-transparent border-none focus:ring-0 focus:outline-none text-sm text-gray-700 placeholder-gray-400 resize-none max-h-28 leading-relaxed"
                                @keydown="onKeydown"
                                @input="autoResize"
                            ></textarea>
                            <button
                                class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center transition-colors mb-0.5"
                                :class="inputText.trim() && !isLoading ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                :disabled="!inputText.trim() || isLoading"
                                @click="sendMessage"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                                    <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 text-center mt-1.5">Enter kirim · Shift+Enter baris baru</p>
                    </div>
                </template>
            </div>
        </transition>

        <!-- Trigger button -->
        <button
            @click="isOpen = !isOpen"
            :class="[
                'w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg transition-all duration-300',
                isOpen
                    ? 'bg-gray-800 text-white hover:bg-gray-700 scale-90'
                    : 'bg-blue-600 text-white hover:bg-blue-700 hover:scale-105 active:scale-95 ring-4 ring-blue-100'
            ]"
            :aria-label="isOpen ? 'Tutup chat' : 'Chat dengan Koze AI'"
        >
            <svg v-if="!isOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a3.75 3.75 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a3.75 3.75 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0116.5 15z" clip-rule="evenodd" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #e2e8f0 transparent;
}
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: transparent; }
.overflow-y-auto::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 20px; }
</style>
