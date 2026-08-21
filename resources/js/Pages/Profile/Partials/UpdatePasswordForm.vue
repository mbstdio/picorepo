<template>
  <section>
    <header>
      <h2 class="text-base font-semibold">Update Password</h2>
      <p class="mt-1 text-sm text-muted-foreground">Use a long, random password to keep your account secure.</p>
    </header>

    <form class="mt-6 space-y-4" @submit.prevent="updatePassword">
      <div class="space-y-2">
        <Label for="current_password">Current Password</Label>
        <Input id="current_password" ref="currentPasswordInput" v-model="form.current_password" type="password" autocomplete="current-password" :class="{ 'border-destructive': form.errors.current_password }" />
        <p v-if="form.errors.current_password" class="text-sm text-destructive">{{ form.errors.current_password }}</p>
      </div>

      <div class="space-y-2">
        <Label for="password">New Password</Label>
        <Input id="password" ref="passwordInput" v-model="form.password" type="password" autocomplete="new-password" :class="{ 'border-destructive': form.errors.password }" />
        <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
      </div>

      <div class="space-y-2">
        <Label for="password_confirmation">Confirm Password</Label>
        <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="{ 'border-destructive': form.errors.password_confirmation }" />
        <p v-if="form.errors.password_confirmation" class="text-sm text-destructive">{{ form.errors.password_confirmation }}</p>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Changes' }}</Button>
        <p v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">Saved.</p>
      </div>
    </form>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const passwordInput = ref(null)
const currentPasswordInput = ref(null)
const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function updatePassword() {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation')
        passwordInput.value.focus()
      }

      if (form.errors.current_password) {
        form.reset('current_password')
        currentPasswordInput.value.focus()
      }
    },
  })
}
</script>
