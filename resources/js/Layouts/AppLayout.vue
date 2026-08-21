<template>
  <div class="min-h-screen bg-background">
    <!-- Navbar -->
    <nav class="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Logo + Nav -->
          <div class="flex items-center gap-8">
            <Link :href="route('dashboard')" class="flex items-center gap-2 font-bold text-lg">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <span>PicoRepo</span>
            </Link>
            <div class="hidden md:flex items-center gap-1">
              <Link
                :href="route('dashboard')"
                :class="['px-3 py-2 rounded-md text-sm font-medium transition-colors', isActive('dashboard') ? 'bg-accent text-accent-foreground' : 'text-muted-foreground hover:text-foreground hover:bg-accent']"
              >
                Dashboard
              </Link>
              <Link
                :href="route('repositories.index')"
                :class="['px-3 py-2 rounded-md text-sm font-medium transition-colors', isActive('repositories') ? 'bg-accent text-accent-foreground' : 'text-muted-foreground hover:text-foreground hover:bg-accent']"
              >
                Repositories
              </Link>
            </div>
          </div>

          <!-- User menu -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" class="flex items-center gap-2">
                <Avatar class="h-7 w-7">
                  <AvatarFallback class="text-xs">{{ userInitials }}</AvatarFallback>
                </Avatar>
                <span class="hidden md:block text-sm">{{ $page.props.auth.user.name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
              <DropdownMenuLabel>{{ $page.props.auth.user.email }}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="router.visit(route('profile.edit'))">
                Profile
              </DropdownMenuItem>
              <DropdownMenuItem @click="router.visit(route('api-tokens.index'))">
                API Tokens
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="logout" class="text-destructive focus:text-destructive">
                Logout
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </nav>

    <!-- Flash messages -->
    <div v-if="$page.props.flash?.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
      <Alert class="border-green-200 bg-green-50 text-green-800">
        <AlertDescription>{{ $page.props.flash.success }}</AlertDescription>
      </Alert>
    </div>
    <div v-if="$page.props.flash?.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
      <Alert variant="destructive">
        <AlertDescription>{{ $page.props.flash.error }}</AlertDescription>
      </Alert>
    </div>

    <!-- Page content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const page = usePage()

const userInitials = computed(() => {
  const name = page.props.auth.user.name
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function isActive(routeName) {
  return route().current(routeName + '*')
}

function logout() {
  router.post(route('logout'))
}
</script>
