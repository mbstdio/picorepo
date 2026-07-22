<template>
  <AppLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-muted-foreground">Welcome back, {{ $page.props.auth.user.name }}</p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card v-for="stat in statCards" :key="stat.label">
          <CardHeader class="pb-2">
            <CardDescription>{{ stat.label }}</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold">{{ stat.value }}</div>
          </CardContent>
        </Card>
      </div>

      <!-- Recent repos -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">My Repositories</h2>
          <Link :href="route('repositories.index')">
            <Button variant="outline" size="sm">View all</Button>
          </Link>
        </div>

        <div v-if="recentRepos.length === 0" class="text-center py-10 text-muted-foreground border rounded-lg">
          No repositories yet.
          <Link :href="route('repositories.create')" class="text-primary ml-1 hover:underline">Create your first one.</Link>
        </div>

        <div v-else class="grid gap-3">
          <Link
            v-for="repo in recentRepos"
            :key="repo.id"
            :href="route('repositories.show', repo.slug)"
            class="block"
          >
            <Card class="hover:bg-accent/50 transition-colors cursor-pointer">
              <CardContent class="flex items-center justify-between py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">
                    {{ repo.name[0].toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-medium">{{ repo.name }}</div>
                    <div class="text-sm text-muted-foreground">{{ repo.packages_count }} packages</div>
                  </div>
                </div>
                <Badge :variant="repo.type === 'public' ? 'secondary' : 'outline'">
                  {{ repo.type }}
                </Badge>
              </CardContent>
            </Card>
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

const props = defineProps({
  stats: Object,
  recentRepos: Array,
})

const statCards = computed(() => [
  { label: 'My Repositories', value: props.stats.my_repos },
  { label: 'Total Repositories', value: props.stats.repositories },
  { label: 'My Packages', value: props.stats.packages },
  { label: 'Total Packages', value: props.stats.total_packages },
])
</script>
