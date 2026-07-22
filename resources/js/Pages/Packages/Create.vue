<template>
  <AppLayout>
    <div class="max-w-lg space-y-6">
      <div>
        <Link :href="route('repositories.show', repository.slug)" class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to {{ repository.name }}
        </Link>
        <h1 class="text-2xl font-bold">Add Package</h1>
        <p class="text-muted-foreground">Create a new package in <strong>{{ repository.name }}</strong></p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="name">Package name</Label>
          <div class="flex items-center gap-2">
            <span class="text-muted-foreground text-sm font-mono">{{ repository.name }}/</span>
            <Input id="name" v-model="form.name" placeholder="my-package" :class="{ 'border-destructive': form.errors.name }" />
          </div>
          <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
          <p class="text-xs text-muted-foreground">This creates the Composer package <code class="bg-muted px-1 rounded">{{ repository.name }}/{{ form.name || 'my-package' }}</code></p>
        </div>

        <div class="space-y-2">
          <Label for="description">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="description" v-model="form.description" rows="2" />
        </div>

        <div class="flex gap-3 pt-2">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Creating...' : 'Create Package' }}
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

const props = defineProps({
  repository: Object,
})

const form = useForm({
  name: '',
  description: '',
})

function submit() {
  form.post(route('repositories.packages.store', props.repository.slug))
}
</script>
