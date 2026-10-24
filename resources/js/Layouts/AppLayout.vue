<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import ThemeSelector from '@/components/ThemeSelector.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const page = usePage()
const user = computed(() => page.props.auth.user)
const userInitials = computed(() => user.value.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2))
const primaryLinks = [
  { label: 'Dashboard', route: 'dashboard', pattern: 'dashboard' },
  { label: 'Repositories', route: 'repositories.index', pattern: 'repositories.*' },
]
const accountLinks = [
  { label: 'Profile', route: 'profile.edit', pattern: 'profile.*' },
  { label: 'API Tokens', route: 'api-tokens.index', pattern: 'api-tokens.*' },
]

function currentPage(link) {
  return route().current(link.pattern) ? 'page' : undefined
}
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <nav aria-label="Primary" class="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-16 items-center justify-between gap-2 py-2">
          <div class="flex min-w-0 items-center gap-8">
            <Link :href="route('dashboard')" class="flex shrink-0 items-center gap-2 rounded-md text-base font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
              <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-primary"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <span>PicoRepo</span>
            </Link>
            <div class="hidden md:flex items-center gap-1">
              <Link
                v-for="link in primaryLinks"
                :key="link.route"
                :href="route(link.route)"
                :aria-current="currentPage(link)"
                class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground aria-[current=page]:bg-accent aria-[current=page]:text-accent-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <ThemeSelector />
            <DropdownMenu v-if="user">
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" aria-label="Navigation and account menu" class="gap-2 px-2 md:px-3">
                  <svg aria-hidden="true" class="md:hidden" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                  <Avatar aria-hidden="true" class="hidden h-7 w-7 shrink-0 md:flex">
                    <AvatarFallback class="text-xs">{{ userInitials }}</AvatarFallback>
                  </Avatar>
                  <span class="hidden max-w-40 truncate md:block">{{ user.name }}</span>
                  <svg aria-hidden="true" class="hidden shrink-0 md:block" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" :collision-padding="8" class="w-64 max-w-[calc(100vw-1rem)]">
                <DropdownMenuLabel class="min-w-0 whitespace-normal break-all">
                  <div>{{ user.name }}</div>
                  <div class="text-xs font-normal text-muted-foreground">{{ user.email }}</div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  v-for="link in primaryLinks"
                  :key="link.route"
                  as-child
                  class="md:hidden aria-[current=page]:bg-accent aria-[current=page]:text-accent-foreground"
                >
                  <Link :href="route(link.route)" :aria-current="currentPage(link)">{{ link.label }}</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator class="md:hidden" />
                <DropdownMenuItem
                  v-for="link in accountLinks"
                  :key="link.route"
                  as-child
                  class="aria-[current=page]:bg-accent aria-[current=page]:text-accent-foreground"
                >
                  <Link :href="route(link.route)" :aria-current="currentPage(link)">{{ link.label }}</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @select="router.post(route('logout'))" class="text-destructive focus:text-destructive">
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button v-else variant="ghost" as-child><Link :href="route('login')">Log in</Link></Button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto min-w-0 px-4 sm:px-6 lg:px-8 py-8">
      <slot />
    </main>
  </div>
</template>
