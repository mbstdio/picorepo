<script setup>
import { useColorMode } from '@vueuse/core'

const { store: theme } = useColorMode({ storageKey: 'theme' })
</script>

<template>
  <select
    v-model="theme"
    aria-label="Theme"
    class="h-9 rounded-md border border-input bg-background py-1 pl-3 pr-8 text-sm text-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
  >
    <option value="light">Light</option>
    <option value="dark">Dark</option>
    <option value="auto">System</option>
  </select>
</template>
