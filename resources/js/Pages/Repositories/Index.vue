<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Repositories</h1>
          <p class="text-muted-foreground">Manage your Composer repositories</p>
        </div>
        <Button as-child><Link :href="route('repositories.create')">New Repository</Link></Button>
      </div>

      <!-- Search -->
      <RepositoryFilters :filters="filters" />

      <!-- Empty state -->
      <div v-if="repositories.data.length === 0" class="text-center py-16 text-muted-foreground border rounded-lg">
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
            <TableRow v-for="repo in repositories.data" :key="repo.id">
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
                <Button variant="ghost" size="sm" as-child><Link :href="route('repositories.show', repo.slug)">View</Link></Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
      <ListPagination :page="repositories" />
    </div>
  </AppLayout>
</template>

<script setup>
import ListPagination from '@/components/ListPagination.vue'
import RepositoryFilters from '@/components/RepositoryFilters.vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const props = defineProps({
  repositories: Object,
  filters: Object,
})

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}
</script>
