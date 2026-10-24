<template>
  <section>
    <header>
      <h2 class="text-base font-semibold text-destructive">Delete Account</h2>
      <p class="mt-1 text-sm text-muted-foreground">Permanently delete your account and all of its resources and data.</p>
    </header>

    <Button class="mt-6" variant="destructive" @click="confirmUserDeletion">Delete Account</Button>

    <Dialog :open="confirmingUserDeletion" @update:open="updateDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Account</DialogTitle>
          <DialogDescription>
            This action permanently deletes your account and its data. Enter your password to confirm.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="deleteUser">
          <p v-if="form.errors.account" role="alert" class="text-sm text-destructive">{{ form.errors.account }}</p>
          <div class="space-y-2">
            <Label for="delete-password">Password</Label>
            <Input id="delete-password" ref="passwordInput" v-model="form.password" type="password" autocomplete="current-password" :aria-invalid="!!form.errors.password" :aria-describedby="form.errors.password ? 'delete-password-error' : undefined" />
            <p v-if="form.errors.password" id="delete-password-error" role="alert" class="text-sm text-destructive">{{ form.errors.password }}</p>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
            <Button type="submit" variant="destructive" :disabled="form.processing">Delete Account</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </section>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const confirmingUserDeletion = ref(false)
const passwordInput = ref(null)
const form = useForm({ password: '' })

function confirmUserDeletion() {
  confirmingUserDeletion.value = true
  nextTick(() => passwordInput.value.focus())
}

function deleteUser() {
  form.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: closeDialog,
    onError: () => passwordInput.value.focus(),
    onFinish: () => form.reset(),
  })
}

function updateDialog(isOpen) {
  if (isOpen) {
    confirmingUserDeletion.value = true
    return
  }

  closeDialog()
}

function closeDialog() {
  confirmingUserDeletion.value = false
  form.clearErrors()
  form.reset()
}
</script>
