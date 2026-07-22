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
          <div class="flex gap-3">
            <Input v-model="form.name" placeholder="Token name (e.g. My laptop)" class="flex-1" :class="{ 'border-destructive': form.errors.name }" />
            <Button @click="createToken" :disabled="form.processing">Create</Button>
          </div>
          <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>

          <!-- Newly created token -->
          <Alert v-if="newToken" class="border-green-200 bg-green-50">
            <AlertDescription class="space-y-2">
              <p class="font-medium text-green-800">Token created — copy it now, it won't be shown again.</p>
              <div class="relative">
                <code class="block bg-white border rounded p-3 text-sm font-mono break-all">{{ newToken }}</code>
                <Button variant="outline" size="sm" class="absolute top-2 right-2" @click="copyToken">
                  {{ copied ? 'Copied!' : 'Copy' }}
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>

      <!-- Token list -->
      <div>
        <h2 class="text-base font-semibold mb-3">Active Tokens</h2>
        <div v-if="tokens.length === 0" class="text-center py-8 text-muted-foreground border rounded-lg text-sm">
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
              <TableRow v-for="token in tokens" :key="token.id">
                <TableCell class="font-medium">{{ token.name }}</TableCell>
                <TableCell class="text-muted-foreground text-sm">{{ token.last_used ? formatDate(token.last_used) : 'Never' }}</TableCell>
                <TableCell class="text-muted-foreground text-sm">{{ formatDate(token.created_at) }}</TableCell>
                <TableCell>
                  <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="deleteToken(token)"
                  >
                    Revoke
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const props = defineProps({
  tokens: Array,
})

const form = useForm({ name: '' })
const copied = ref(false)

const newToken = computed(() => usePage().props.flash?.token ?? null)

function createToken() {
  form.post(route('api-tokens.store'), {
    onSuccess: () => form.reset(),
  })
}

function copyToken() {
  navigator.clipboard.writeText(newToken.value)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function deleteToken(token) {
  router.delete(route('api-tokens.destroy', token.id))
}

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}
</script>
