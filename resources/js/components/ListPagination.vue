<script setup>
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineProps({ page: { type: Object, required: true } })
</script>

<template>
  <nav v-if="page.last_page > 1" aria-label="Pagination" class="flex items-center justify-between gap-3 mt-4">
    <Button v-if="page.prev_page_url" variant="outline" as-child>
      <Link :href="page.prev_page_url" preserve-state preserve-scroll>Previous</Link>
    </Button>
    <span v-else></span>
    <span class="text-sm text-muted-foreground">Page {{ page.current_page }} of {{ page.last_page }} · {{ page.total }} total</span>
    <Button v-if="page.next_page_url" variant="outline" as-child>
      <Link :href="page.next_page_url" preserve-state preserve-scroll>Next</Link>
    </Button>
    <span v-else></span>
  </nav>
</template>
