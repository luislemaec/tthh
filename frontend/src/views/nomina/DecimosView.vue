<template>
  <div>
    <!-- Pestañas -->
    <div class="flex border-b mb-5">
      <button v-for="tab in tabs" :key="tab.id" @click="activo = tab.id"
        class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="activo === tab.id
          ? 'border-blue-600 text-blue-600'
          : 'border-transparent text-gray-500 hover:text-gray-700'">
        {{ tab.label }}
      </button>
    </div>

    <DecimoTerceroView v-if="activo === 'd13'" />
    <DecimoCuartoView  v-else-if="activo === 'd14'" />
    <ConsolidadoView   v-else-if="activo === 'consolidado'" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import DecimoTerceroView from './DecimoTerceroView.vue'
import DecimoCuartoView  from './DecimoCuartoView.vue'
import ConsolidadoView   from './ConsolidadoView.vue'

const tabs = [
  { id: 'd13',        label: 'Décimo Tercero'    },
  { id: 'd14',        label: 'Décimo Cuarto'     },
  { id: 'consolidado', label: 'Consolidado Décimos' },
]

const activo = ref('d13')
</script>
