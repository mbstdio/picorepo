<template>
  <AppLayout>
    <div class="max-w-lg space-y-6">
      <div>
        <Link :href="route('repositories.packages.show', [repository.slug, package_.id])" class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to {{ package_.full_name }}
        </Link>
        <h1 class="text-2xl font-bold">Edit Version</h1>
        <p class="text-muted-foreground">Update version <code class="bg-muted px-1 rounded">{{ version.version }}</code> for {{ package_.full_name }}</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="version">Version</Label>
          <Input id="version" v-model="form.version" placeholder="1.0.0" :class="{ 'border-destructive': form.errors.version }" />
          <p v-if="form.errors.version" class="text-sm text-destructive">{{ form.errors.version }}</p>
          <p class="text-xs text-muted-foreground">Composer-compatible format: 1.0.0, v2.1.0-beta, dev-main, etc.</p>
        </div>

        <div class="space-y-2">
          <Label for="type">Type</Label>
          <Select v-model="form.type">
            <SelectTrigger id="type"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="type in types" :key="type" :value="type">{{ type }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
        </div>

        <div class="space-y-2">
          <Label for="description">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="description" v-model="form.description" rows="3" />
          <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
        </div>

        <p class="text-xs text-muted-foreground">The ZIP archive remains stored on {{ version.disk }}.</p>

        <div class="flex gap-3 pt-2">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </Button>
          <Link :href="route('repositories.packages.show', [repository.slug, package_.id])">
            <Button type="button" variant="outline">Cancel</Button>
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const props = defineProps({
  repository: Object,
  package: Object,
  version: Object,
  types: Array,
})

const package_ = props.package

const form = useForm({
  version: props.version.version,
  type: props.version.type,
  description: props.version.description ?? '',
})

function submit() {
  form.put(route('repositories.packages.versions.update', [props.repository.slug, package_.id, props.version.id]))
}
</script>
