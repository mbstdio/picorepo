<template>
  <AppLayout>
    <div class="space-y-6 max-w-2xl">
      <div>
        <h1 class="text-2xl font-bold">API Tokens</h1>
        <p class="text-muted-foreground">Tokens allow Composer to access private repositories on your behalf.</p>
      </div>

      <!-- Create token -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Create Token</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <form @submit.prevent="createToken" class="space-y-2">
            <Label for="token-name">Token name</Label>
            <div class="flex gap-3">
              <Input id="token-name" v-model="form.name" placeholder="e.g. My laptop" class="flex-1" :aria-invalid="!!form.errors.name" :aria-describedby="form.errors.name ? 'token-name-error' : undefined" />
              <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create' }}</Button>
            </div>
            <p v-if="form.errors.name" id="token-name-error" role="alert" class="text-sm text-destructive">{{ form.errors.name }}</p>
          </form>

          <!-- Newly created token -->
          <Alert v-if="newToken" class="bg-background">
            <AlertDescription class="space-y-2">
              <p class="font-medium text-foreground">Token created — copy it now, it won't be shown again.</p>
              <div class="relative">
                <code class="block bg-muted text-foreground border rounded p-3 pr-20 text-sm font-mono break-all">{{ newToken }}</code>
                <Button variant="outline" size="sm" class="absolute top-2 right-2" :disabled="copying" @click="copyToken">
                  Copy
                </Button>
              </div>
            </AlertDescription>
          </Alert>
          <p role="status" class="text-sm">{{ copyStatus }}</p>
        </CardContent>
      </Card>

      <!-- Token list -->
      <Dialog v-model:open="showDeleteDialog">
        <div>
          <h2 class="text-base font-semibold mb-3">Active Tokens</h2>
          <div v-if="tokens.data.length === 0" class="text-center py-8 text-muted-foreground border rounded-lg text-sm">
            No tokens yet.
          </div>
          <div v-else class="border rounded-lg overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Last used</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead class="w-16"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="token in tokens.data" :key="token.id">
                  <TableCell class="font-medium">{{ token.name }}</TableCell>
                  <TableCell class="text-muted-foreground text-sm">{{ token.last_used ? formatDate(token.last_used) : 'Never' }}</TableCell>
                  <TableCell class="text-muted-foreground text-sm">{{ formatDate(token.created_at) }}</TableCell>
                  <TableCell>
                    <DialogTrigger as-child>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="selectedToken = token"
                        :disabled="pending"
                        :aria-label="`Revoke token ${token.name}`"
                      >
                        {{ pending ? 'Working…' : 'Revoke' }}
                      </Button>
                    </DialogTrigger>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <ListPagination :page="tokens" />
        </div>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Revoke Token</DialogTitle>
            <DialogDescription>
              Are you sure you want to revoke <strong>{{ selectedToken?.name }}</strong>?
              Clients using it will lose access. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" :disabled="pending" @click="showDeleteDialog = false">Cancel</Button>
            <Button variant="destructive" :disabled="pending" :aria-busy="pending" @click="deleteToken">
              {{ pending ? 'Revoking…' : 'Revoke' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, shallowRef } from 'vue'
import { toast } from 'vue-sonner'
import { useClipboard } from '@/composables/useClipboard'
import { Label } from '@/components/ui/label'
import ListPagination from '@/components/ListPagination.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'

const props = defineProps({
  tokens: Object,
})

const form = useForm({ name: '' })
const { status: copyStatus, copying, copy } = useClipboard()
const pending = shallowRef(false)
const showDeleteDialog = shallowRef(false)
const selectedToken = shallowRef(null)

const newToken = computed(() => usePage().props.flash?.token ?? null)

function createToken() {
  form.post(route('api-tokens.store'), {
    onSuccess: () => form.reset(),
  })
}

function copyToken() {
  copy(newToken.value)
}

function deleteToken() {
  if (pending.value || !selectedToken.value) return
  pending.value = true
  router.delete(route('api-tokens.destroy', selectedToken.value.id), {
    onSuccess: () => { showDeleteDialog.value = false },
    onError: errors => toast.error(Object.values(errors).join(' ') || 'Could not save changes.', { duration: Infinity }),
    onFinish: () => { pending.value = false },
  })
}

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}
</script>
