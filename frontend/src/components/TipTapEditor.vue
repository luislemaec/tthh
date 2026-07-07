<template>
  <div class="tiptap-wrapper">
    <!-- Toolbar -->
    <div class="flex items-center gap-1 border border-gray-300 border-b-0 rounded-t-lg bg-gray-50 px-2 py-1.5">
      <button
        type="button"
        @click="editor?.chain().focus().toggleBold().run()"
        :class="editor?.isActive('bold')
          ? 'bg-white border border-gray-300 shadow-sm text-gray-900'
          : 'text-gray-600 hover:bg-gray-200'"
        class="px-2.5 py-0.5 rounded text-sm font-bold transition-colors"
        title="Negrita (Ctrl+B)">
        N
      </button>
    </div>

    <!-- Área de edición -->
    <editor-content
      :editor="editor"
      class="tiptap-content border border-gray-300 rounded-b-lg px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-[#579186] focus-within:border-transparent" />
  </div>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'

const props = defineProps({
  modelValue: { type: String, default: '' },
  minHeight:  { type: String, default: '140px' },
})
const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    StarterKit.configure({
      heading:         false,
      blockquote:      false,
      code:            false,
      codeBlock:       false,
      horizontalRule:  false,
      strike:          false,
    }),
  ],
  editorProps: {
    attributes: {
      class: 'focus:outline-none',
      style: `min-height: ${props.minHeight}`,
    },
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

// Sincronizar cuando el contenido cambia externamente (ej: al abrir modal con datos existentes)
watch(() => props.modelValue, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val || '', false)
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<style scoped>
.tiptap-content :deep(.ProseMirror) {
  outline: none;
}
.tiptap-content :deep(.ProseMirror p) {
  margin: 0 0 4px 0;
}
.tiptap-content :deep(.ProseMirror p:last-child) {
  margin-bottom: 0;
}
</style>
