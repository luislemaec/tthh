<template>
  <nav class="sit-breadcrumb" aria-label="Migas de pan">
    <ol><li v-for="(item, index) in items" :key="index">
      <router-link v-if="item.to" :to="item.to">{{ item.label }}</router-link>
      <span v-else :aria-current="index === items.length - 1 ? 'page' : undefined">{{ item.label }}</span>
    </li></ol>
  </nav>
</template>
<script setup>
defineProps({ items: { type: Array, required: true } })
</script>
