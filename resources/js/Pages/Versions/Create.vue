<template>
  <AppLayout>
    <div class="max-w-lg space-y-6">
      <div>
        <Link :href="route('repositories.packages.show', [repository.slug, package_.id])" class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to {{ package_.full_name }}
        </Link>
        <h1 class="text-2xl font-bold">Add Version</h1>
        <p class="text-muted-foreground">New version for <code class="bg-muted px-1 rounded">{{ package_.full_name }}</code></p>
      </div>

      <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
        <!-- Version -->
        <div class="space-y-2">
          <Label for="version">Version</Label>
          <Input id="version" v-model="form.version" placeholder="1.0.0" :class="{ 'border-destructive': form.errors.version }" />
          <p v-if="form.errors.version" class="text-sm text-destructive">{{ form.errors.version }}</p>
          <p class="text-xs text-muted-foreground">Composer-compatible format: 1.0.0, v2.1.0-beta, dev-main, etc.</p>
        </div>

        <!-- Type -->
        <div class="space-y-2">
          <Label for="type">Type</Label>
          <Select v-model="form.type">
            <SelectTrigger id="type"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="t in types" :key="t" :value="t">{{ t }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
        </div>

        <!-- Storage disk -->
        <div class="space-y-2" v-if="availableDisks.length > 1">
          <Label>Storage</Label>
          <div class="flex gap-3">
            <label v-for="d in availableDisks" :key="d" class="flex items-center gap-2 cursor-pointer">
              <input type="radio" :value="d" v-model="form.disk" class="accent-primary" />
              <span class="text-sm capitalize">{{ d === 'local' ? 'Local storage' : 'S3 / Cloud' }}</span>
            </label>
          </div>
        </div>

        <!-- ZIP upload -->
        <div class="space-y-2">
          <Label for="zip_file">ZIP file</Label>
          <Input id="zip_file" type="file" accept=".zip" @change="handleFile" :class="{ 'border-destructive': form.errors.zip_file }" />
          <p v-if="form.errors.zip_file" class="text-sm text-destructive">{{ form.errors.zip_file }}</p>
          <p class="text-xs text-muted-foreground">Max 100MB. Must be a .zip archive.</p>
        </div>

        <!-- Description -->
        <div class="space-y-2">
          <Label for="description">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="description" v-model="form.description" rows="2" />
        </div>

        <div class="space-y-2">
          <Label for="extra">Custom Composer metadata <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="extra" v-model="form.extra" rows="6" class="font-mono" :class="{ 'border-destructive': form.errors.extra }" placeholder='{"require":{"php":"^8.3"},"autoload":{"psr-4":{"Acme\\":"src/"}}}' />
          <p v-if="form.errors.extra" class="text-sm text-destructive">{{ form.errors.extra }}</p>
          <p class="text-xs text-muted-foreground">JSON object, max 16 KiB. Supported keys: {{ metadataKeys.join(', ') }}. Package identity, type, and distribution are managed automatically.</p>
        </div>

        <!-- Upload progress -->
        <div v-if="form.progress" class="space-y-1">
          <div class="flex justify-between text-xs text-muted-foreground">
            <span>Uploading...</span>
            <span>{{ form.progress.percentage }}%</span>
          </div>
          <div class="h-2 bg-muted rounded-full overflow-hidden">
            <div class="h-full bg-primary transition-all" :style="{ width: form.progress.percentage + '%' }"></div>
          </div>
        </div>

        <div class="flex gap-3 pt-2">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Uploading...' : 'Add Version' }}
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
  availableDisks: Array,
  types: Array,
  metadataKeys: Array,
})

const package_ = props.package

const form = useForm({
  version: '',
  type: 'library',
  disk: props.availableDisks[0],
  zip_file: null,
  description: '',
  extra: '',
})

function handleFile(e) {
  form.zip_file = e.target.files[0]
}

function submit() {
  form.post(route('repositories.packages.versions.store', [props.repository.slug, package_.id]), {
    forceFormData: true,
  })
}
</script>
