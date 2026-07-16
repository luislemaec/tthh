<template>
  <div class="flex gap-1">
    <select :value="horas" @change="onHoras"
      class="border rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] bg-white">
      <option value="">HH</option>
      <option v-for="h in 24" :key="h - 1" :value="pad(h - 1)">{{ pad(h - 1) }}</option>
    </select>
    <span class="self-center text-gray-500 font-semibold">:</span>
    <select :value="minutos" @change="onMinutos"
      class="border rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] bg-white">
      <option value="">MM</option>
      <option v-for="m in minutosOpciones" :key="m" :value="pad(m)">{{ pad(m) }}</option>
    </select>
  </div>
</template>

<script setup>
import { computed } from "vue"

const props = defineProps({
  modelValue: { type: String, default: "" },
  step:       { type: Number, default: 1 }, // minutos entre opciones (1, 5, 15, 30...)
})
const emit = defineEmits(["update:modelValue", "change"])

const pad = (n) => String(n).padStart(2, "0")

const horas   = computed(() => props.modelValue ? props.modelValue.substring(0, 2) : "")
const minutos = computed(() => props.modelValue ? props.modelValue.substring(3, 5) : "")

const minutosOpciones = computed(() => {
  const opts = []
  for (let m = 0; m < 60; m += props.step) opts.push(m)
  return opts
})

const emitir = (h, m) => {
  if (h === "" || m === "") { emit("update:modelValue", ""); return }
  const val = `${h}:${m}`
  emit("update:modelValue", val)
  emit("change", val)
}

const onHoras   = (e) => emitir(e.target.value, minutos.value  || "00")
const onMinutos = (e) => emitir(horas.value  || "00", e.target.value)
</script>
