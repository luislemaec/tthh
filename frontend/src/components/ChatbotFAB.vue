<script setup>
import { ref, nextTick } from 'vue'
import { knowledge, FALLBACK } from '@/data/chatbot-knowledge.js'

const open         = ref(false)
const input        = ref('')
const loading      = ref(false)
const messages     = ref([])
const messagesArea = ref(null)
const menuVisible  = ref(false)

const QUICK_REPLIES = [
  '¿Cómo marco mi asistencia?',
  '¿Cómo solicito un permiso?',
  '¿Cuántos días de vacaciones tengo?',
  '¿Cómo solicito movilización?',
]

const WELCOME = '¡Hola! 👋 Soy el asistente del sistema. Puedo ayudarte con preguntas sobre el uso del aplicativo. ¿En qué puedo ayudarte?'

function normalize(text) {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
}

function findAnswer(userInput) {
  const q = normalize(userInput)
  let best = null
  let bestScore = 0
  for (const qa of knowledge) {
    const score = qa.keywords.filter(k => q.includes(k)).length
    if (score > bestScore) {
      bestScore = score
      best = qa
    }
  }
  return bestScore === 0 ? FALLBACK : best.answer
}

async function scrollToBottom() {
  await nextTick()
  if (messagesArea.value) {
    messagesArea.value.scrollTop = messagesArea.value.scrollHeight
  }
}

async function sendMessage(text) {
  const msg = (text ?? input.value).trim()
  if (!msg || loading.value) return
  input.value = ''
  menuVisible.value = false

  messages.value.push({ from: 'user', text: msg })
  await scrollToBottom()

  loading.value = true
  const esMenu = normalize(msg).includes('menu')
  setTimeout(async () => {
    messages.value.push({ from: 'bot', text: findAnswer(msg) })
    if (esMenu) menuVisible.value = true
    loading.value = false
    await scrollToBottom()
  }, 320)
}

function toggle() {
  open.value = !open.value
  if (open.value && messages.value.length === 0) {
    messages.value.push({ from: 'bot', text: WELCOME })
  }
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}
</script>

<template>
  <div class="fixed bottom-6 right-6 z-[9980] flex flex-col items-end gap-3">

    <!-- ── Ventana de chat ─────────────────────────────────────────────── -->
    <Transition name="chat">
      <div
        v-if="open"
        class="w-80 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        style="height: 480px; background: #fff;"
      >
        <!-- Cabecera -->
        <div class="flex items-center gap-3 px-4 py-3 flex-shrink-0" style="background: #0b5547;">
          <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
               style="background: rgba(255,255,255,0.18);">
            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.526 3.657 1.438 5.168L2.293 21.293a1 1 0 001.32 1.414L7.5 21.29A9.964 9.964 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 2a8 8 0 110 16A8 8 0 0112 4zm-1 5a1 1 0 100 2h2a1 1 0 100-2h-2zm0 4a1 1 0 000 2h4a1 1 0 100-2h-4z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white font-semibold text-sm leading-tight">Asistente del Sistema</p>
            <p class="text-white/60 text-xs">Consultas sobre el aplicativo</p>
          </div>
          <button
            @click="open = false"
            class="text-white/70 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Área de mensajes -->
        <div ref="messagesArea" class="flex-1 overflow-y-auto p-4 space-y-3" style="background: #f0f2f5;">

          <!-- Mensajes -->
          <div
            v-for="(m, i) in messages"
            :key="i"
            :class="['flex', m.from === 'user' ? 'justify-end' : 'justify-start']"
          >
            <div
              :class="[
                'max-w-[85%] px-3 py-2 rounded-2xl text-sm leading-snug whitespace-pre-line shadow-sm',
                m.from === 'user'
                  ? 'text-white rounded-br-sm'
                  : 'text-gray-800 rounded-bl-sm',
              ]"
              :style="m.from === 'user' ? 'background:#0b5547;' : 'background:#fff;'"
            >
              {{ m.text }}
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="flex justify-start">
            <div class="px-4 py-3 rounded-2xl rounded-bl-sm shadow-sm" style="background:#fff;">
              <span class="typing-dot" /><span class="typing-dot" /><span class="typing-dot" />
            </div>
          </div>

          <!-- Quick replies (al inicio o al escribir "menu") -->
          <div v-if="(messages.length === 1 || menuVisible) && !loading" class="flex flex-col gap-2 pt-1">
            <button
              v-for="q in QUICK_REPLIES"
              :key="q"
              @click="sendMessage(q)"
              class="text-left text-xs px-3 py-2 rounded-xl border transition-colors"
              style="border-color:#0b5547; color:#0b5547; background:#fff;"
              @mouseenter="e => { e.currentTarget.style.background='#0b5547'; e.currentTarget.style.color='#fff' }"
              @mouseleave="e => { e.currentTarget.style.background='#fff'; e.currentTarget.style.color='#0b5547' }"
            >
              {{ q }}
            </button>
          </div>
        </div>

        <!-- Input -->
        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-100 flex-shrink-0 bg-white">
          <input
            v-model="input"
            @keydown="onKeydown"
            :disabled="loading"
            type="text"
            placeholder="Escribe tu consulta..."
            class="flex-1 text-sm bg-gray-100 rounded-full px-4 py-2 outline-none disabled:opacity-50"
            style="min-width:0;"
          />
          <button
            @click="sendMessage()"
            :disabled="!input.trim() || loading"
            class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition-opacity disabled:opacity-40"
            style="background:#0b5547;"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>
        </div>
      </div>
    </Transition>

    <!-- ── Botón FAB ───────────────────────────────────────────────────── -->
    <button
      @click="toggle"
      class="w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
      style="background: #0b5547;"
      :title="open ? 'Cerrar asistente' : 'Abrir asistente'"
    >
      <Transition name="fab-icon" mode="out-in">
        <!-- Ícono chat -->
        <svg v-if="!open" key="chat" class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
          <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
        </svg>
        <!-- Ícono X -->
        <svg v-else key="close" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </Transition>
    </button>
  </div>
</template>

<style scoped>
@reference "tailwindcss";

/* Animación entrada/salida de la ventana */
.chat-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.chat-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.chat-enter-from  { opacity: 0; transform: translateY(12px) scale(0.97); }
.chat-leave-to    { opacity: 0; transform: translateY(8px)  scale(0.97); }

/* Animación del ícono del FAB */
.fab-icon-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fab-icon-leave-active { transition: opacity 0.1s ease,  transform 0.1s ease; }
.fab-icon-enter-from   { opacity: 0; transform: rotate(-30deg) scale(0.7); }
.fab-icon-leave-to     { opacity: 0; transform: rotate(30deg)  scale(0.7); }

/* Puntos animados del typing indicator */
.typing-dot {
  display: inline-block;
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #9ca3af;
  margin: 0 2px;
  animation: bounce 1.2s infinite ease-in-out;
}
.typing-dot:nth-child(1) { animation-delay: 0s; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%            { transform: translateY(-5px); }
}
</style>
