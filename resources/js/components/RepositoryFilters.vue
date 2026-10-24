<script setup>
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const props = defineProps({ filters: { type: Object, required: true } })
const form = reactive({ search: props.filters.search ?? '', type: props.filters.type ?? 'all' })
watch(() => props.filters, filters => {
  form.search = filters.search ?? ''
  form.type = filters.type ?? 'all'
})

function search() {
  router.get(route('repositories.index'), form, { preserveState: true, replace: true })
}
</script>

<template>
  <form class="flex gap-3" @submit.prevent="search">
    <Input v-model="form.search" aria-label="Search repositories" placeholder="Search repositories..." class="max-w-sm" maxlength="255" />
    <Select v-model="form.type">
      <SelectTrigger class="w-36" aria-label="Repository type"><SelectValue placeholder="All types" /></SelectTrigger>
      <SelectContent>
        <SelectItem value="all">All types</SelectItem>
        <SelectItem value="public">Public</SelectItem>
        <SelectItem value="private">Private</SelectItem>
      </SelectContent>
    </Select>
    <Button type="submit" variant="outline">Search</Button>
  </form>
</template>
