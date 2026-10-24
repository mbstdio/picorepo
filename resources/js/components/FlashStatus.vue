<script setup>
import { onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useColorMode } from '@vueuse/core'
import { Toaster } from '@/components/ui/sonner'
import { useFlashNotifications } from '@/composables/useFlashNotifications'
import 'vue-sonner/style.css'

const page = usePage()
const theme = useColorMode({ storageKey: 'theme' })

onMounted(() => useFlashNotifications(() => page.props.flash))
</script>

<template>
  <Toaster position="top-right" :theme="theme" rich-colors close-button :duration="5000" />
</template>
