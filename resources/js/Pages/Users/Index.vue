<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-start justify-between">
        <div>
          <Link :href="route('repositories.show', repository.slug)" class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            {{ repository.name }}
          </Link>
          <h1 class="text-2xl font-bold">Access Management</h1>
          <p class="text-muted-foreground">Control who has access to <strong>{{ repository.name }}</strong></p>
        </div>
      </div>

      <!-- Invite user -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Invite User</CardTitle>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="invite" class="flex gap-3">
            <Input v-model="inviteForm.email" type="email" aria-label="Invite user email" placeholder="user@example.com" class="flex-1" :aria-invalid="!!inviteForm.errors.email" :aria-describedby="inviteForm.errors.email ? 'invite-email-error' : undefined" />
            <Select v-model="inviteForm.role" class="w-36">
              <SelectTrigger aria-label="Invited user role" :aria-invalid="!!inviteForm.errors.role" :aria-describedby="inviteForm.errors.role ? 'invite-role-error' : undefined"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="maintainer">Maintainer</SelectItem>
                <SelectItem value="owner">Owner</SelectItem>
              </SelectContent>
            </Select>
            <Button type="submit" :disabled="inviteForm.processing">Invite</Button>
          </form>
          <p v-if="inviteForm.errors.email" id="invite-email-error" role="alert" class="text-sm text-destructive mt-2">{{ inviteForm.errors.email }}</p>
          <p v-if="inviteForm.errors.role" id="invite-role-error" role="alert" class="text-sm text-destructive mt-2">{{ inviteForm.errors.role }}</p>
        </CardContent>
      </Card>

      <!-- Users table -->
      <div class="border rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>User</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Role</TableHead>
              <TableHead class="w-32"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="user in users.data" :key="user.id">
              <TableCell class="font-medium">{{ user.name }}</TableCell>
              <TableCell class="text-muted-foreground">{{ user.email }}</TableCell>
              <TableCell>
                <Select :model-value="user.role" :disabled="pending" @update:model-value="updateRole(user, $event)" class="w-32">
                  <SelectTrigger class="h-8 text-xs" :aria-label="`Role for ${user.name} (${user.email})`" :aria-invalid="roleUserId === user.id && !!roleError" :aria-describedby="roleUserId === user.id && roleError ? `role-error-${user.id}` : undefined"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="maintainer">Maintainer</SelectItem>
                    <SelectItem value="owner">Owner</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="roleUserId === user.id && roleError" :id="`role-error-${user.id}`" class="text-sm text-destructive">{{ roleError }}</p>
              </TableCell>
              <TableCell>
                <Button
                  variant="ghost"
                  size="sm"
                  class="text-destructive hover:text-destructive"
                  @click="removeUser(user)"
                  :disabled="pending || user.id === $page.props.auth.user.id"
                  :aria-label="`Remove ${user.name} (${user.email})`"
                >
                  {{ pending ? 'Working…' : 'Remove' }}
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
      <ListPagination :page="users" />
      <p role="status" class="text-sm">{{ actionStatus }}</p>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3'
import { shallowRef } from 'vue'
import { useConfirmedAction } from '@/composables/useConfirmedAction'
import ListPagination from '@/components/ListPagination.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const props = defineProps({
  repository: Object,
  users: Object,
})

const inviteForm = useForm({
  email: '',
  role: 'maintainer',
})
const { pending, status: actionStatus, run } = useConfirmedAction()
const roleUserId = shallowRef(null)
const roleError = shallowRef('')

function invite() {
  inviteForm.post(route('repositories.users.store', props.repository.slug), {
    onSuccess: () => inviteForm.reset(),
  })
}

function updateRole(user, role) {
  run(`Change ${user.name} (${user.email}) to ${role}?`, options => {
    roleUserId.value = user.id
    roleError.value = ''
    router.patch(route('repositories.users.update', [props.repository.slug, user.id]), { role }, {
      ...options,
      onError: errors => {
        roleError.value = errors.role ?? ''
        options.onError(errors)
      },
    })
  })
}

function removeUser(user) {
  run(`Remove ${user.name} (${user.email}) from this repository? They will lose access.`, options => {
    router.delete(route('repositories.users.destroy', [props.repository.slug, user.id]), options)
  })
}
</script>
