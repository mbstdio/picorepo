<template>
  <AppLayout>
    <div class="max-w-lg space-y-6">
      <div>
        <Link :href="route('repositories.show', repository.slug)" class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to {{ repository.name }}
        </Link>
        <h1 class="text-2xl font-bold">Edit Repository</h1>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="name">Vendor name</Label>
          <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
          <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="type">Visibility</Label>
          <Select v-model="form.type">
            <SelectTrigger id="type"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="public">Public</SelectItem>
              <SelectItem value="private">Private</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="description">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="description" v-model="form.description" rows="3" />
        </div>

        <div class="flex gap-3 pt-2">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </Button>
          <Link :href="route('repositories.show', repository.slug)">
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
})

const form = useForm({
  name: props.repository.name,
  type: props.repository.type,
  description: props.repository.description ?? '',
})

function submit() {
  form.put(route('repositories.update', props.repository.slug))
}
</script>
