<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Repositories</h1>
          <p class="text-muted-foreground">Manage your Composer repositories</p>
        </div>
        <Link :href="route('repositories.create')">
          <Button>New Repository</Button>
        </Link>
      </div>

      <!-- Search -->
      <div class="flex gap-3">
        <Input v-model="search" placeholder="Search repositories..." class="max-w-sm" />
        <Select v-model="typeFilter">
          <SelectTrigger class="w-36">
            <SelectValue placeholder="All types" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            <SelectItem value="public">Public</SelectItem>
            <SelectItem value="private">Private</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Empty state -->
      <div v-if="filteredRepositories.length === 0" class="text-center py-16 text-muted-foreground border rounded-lg">
        <p class="text-lg font-medium">No repositories found</p>
        <p class="text-sm mt-1">
          <Link :href="route('repositories.create')" class="text-primary hover:underline">Create your first repository</Link>
        </p>
      </div>

      <!-- Table -->
      <div v-else class="border rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Packages</TableHead>
              <TableHead>Created</TableHead>
              <TableHead class="w-20"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="repo in filteredRepositories" :key="repo.id">
              <TableCell>
                <Link :href="route('repositories.show', repo.slug)" class="font-medium hover:underline">
                  {{ repo.name }}
                </Link>
                <p v-if="repo.description" class="text-xs text-muted-foreground mt-0.5 truncate max-w-xs">
                  {{ repo.description }}
                </p>
              </TableCell>
              <TableCell>
                <Badge :variant="repo.type === 'public' ? 'secondary' : 'outline'">
                  {{ repo.type }}
                </Badge>
              </TableCell>
              <TableCell>{{ repo.packages_count }}</TableCell>
              <TableCell class="text-muted-foreground text-sm">
                {{ formatDate(repo.created_at) }}
              </TableCell>
              <TableCell>
                <Link :href="route('repositories.show', repo.slug)">
                  <Button variant="ghost" size="sm">View</Button>
                </Link>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const props = defineProps({
  repositories: Array,
})

const search = ref('')
const typeFilter = ref('all')

const filteredRepositories = computed(() => {
  return props.repositories.filter(r => {
    const matchesSearch = !search.value || r.name.toLowerCase().includes(search.value.toLowerCase())
    const matchesType = typeFilter.value === 'all' || r.type === typeFilter.value
    return matchesSearch && matchesType
  })
})

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}
</script>
