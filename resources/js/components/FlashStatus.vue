<script setup>
import { nextTick, onMounted, shallowRef, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const success = shallowRef('')
const error = shallowRef('')

async function announce(flash) {
  success.value = ''
  error.value = ''
  await nextTick()
  success.value = flash?.success ?? ''
  error.value = flash?.error ?? ''
}

onMounted(() => announce(page.props.flash))
watch(() => page.props.flash, announce, { flush: 'post' })
</script>

<template>
  <div class="sr-only">
    <p role="status">{{ success }}</p>
    <p role="alert">{{ error }}</p>
  </div>
</template>
