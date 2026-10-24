<template>
  <Head :title="`Add Version - ${package_.full_name}`" />
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
          <Input id="version" v-model="form.version" placeholder="1.0.0" :aria-invalid="!!form.errors.version" :aria-describedby="form.errors.version ? 'version-error' : undefined" />
          <p v-if="form.errors.version" id="version-error" role="alert" class="text-sm text-destructive">{{ form.errors.version }}</p>
          <p class="text-xs text-muted-foreground">Composer-compatible format: 1.0.0, v2.1.0-beta, dev-main, etc.</p>
        </div>

        <!-- Type -->
        <div class="space-y-2">
          <Label for="type">Type</Label>
          <Select v-model="form.type">
            <SelectTrigger id="type" :aria-invalid="!!form.errors.type" :aria-describedby="form.errors.type ? 'type-error' : undefined"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="t in types" :key="t" :value="t">{{ t }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.type" id="type-error" role="alert" class="text-sm text-destructive">{{ form.errors.type }}</p>
        </div>

        <!-- Storage disk -->
        <fieldset class="space-y-2" :aria-invalid="!!form.errors.disk" :aria-describedby="form.errors.disk ? 'disk-error' : undefined">
          <legend class="text-sm font-medium">Storage</legend>
          <div class="flex gap-3">
            <label v-for="d in availableDisks" :key="d" class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="disk" :value="d" v-model="form.disk" class="accent-primary" />
              <span class="text-sm capitalize">{{ d === 'local' ? 'Local storage' : 'S3 / Cloud' }}</span>
            </label>
          </div>
          <p v-if="form.errors.disk" id="disk-error" role="alert" class="text-sm text-destructive">{{ form.errors.disk }}</p>
        </fieldset>

        <!-- ZIP upload -->
        <div class="space-y-2">
          <Label for="zip_file">ZIP file</Label>
          <Input id="zip_file" type="file" accept=".zip" @change="handleFile" :aria-invalid="!!form.errors.zip_file" :aria-describedby="form.errors.zip_file ? 'zip-file-error' : undefined" />
          <p v-if="form.errors.zip_file" id="zip-file-error" role="alert" class="text-sm text-destructive">{{ form.errors.zip_file }}</p>
          <p class="text-xs text-muted-foreground">Max 100MB. Must be a .zip archive.</p>
        </div>

        <!-- Description -->
        <div class="space-y-2">
          <Label for="description">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="description" v-model="form.description" rows="2" :aria-invalid="!!form.errors.description" :aria-describedby="form.errors.description ? 'description-error' : undefined" />
          <p v-if="form.errors.description" id="description-error" role="alert" class="text-sm text-destructive">{{ form.errors.description }}</p>
        </div>

        <div class="space-y-2">
          <Label for="extra">Custom Composer metadata <span class="text-muted-foreground font-normal">(optional)</span></Label>
          <Textarea id="extra" v-model="form.extra" rows="6" class="font-mono" :aria-invalid="!!form.errors.extra" :aria-describedby="form.errors.extra ? 'extra-error' : undefined" placeholder='{"require":{"php":"^8.3"},"autoload":{"psr-4":{"Acme\\":"src/"}}}' />
          <p v-if="form.errors.extra" id="extra-error" role="alert" class="text-sm text-destructive">{{ form.errors.extra }}</p>
          <p class="text-xs text-muted-foreground">JSON object, max 16 KiB. Supported keys: {{ metadataKeys.join(', ') }}. Package identity, type, and distribution are managed automatically.</p>
        </div>

        <!-- Upload progress -->
        <div v-if="form.progress" class="space-y-1" role="status">
          <div class="flex justify-between text-xs text-muted-foreground">
            <span>Uploading...</span>
            <span>{{ form.progress.percentage }}%</span>
          </div>
          <div role="progressbar" aria-label="ZIP upload" :aria-valuenow="form.progress.percentage" aria-valuemin="0" aria-valuemax="100" class="h-2 bg-muted rounded-full overflow-hidden">
            <div class="h-full bg-primary transition-all" :style="{ width: form.progress.percentage + '%' }"></div>
          </div>
        </div>

        <div class="flex gap-3 pt-2">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Uploading...' : 'Add Version' }}
          </Button>
          <Button variant="outline" as-child><Link :href="route('repositories.packages.show', [repository.slug, package_.id])">Cancel</Link></Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
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
