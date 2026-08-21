<template>
  <section>
    <header>
      <h2 class="text-base font-semibold">Profile Information</h2>
      <p class="mt-1 text-sm text-muted-foreground">Update your account's profile information and email address.</p>
    </header>

    <form class="mt-6 space-y-4" @submit.prevent="form.patch(route('profile.update'))">
      <div class="space-y-2">
        <Label for="name">Name</Label>
        <Input id="name" v-model="form.name" autocomplete="name" autofocus required :class="{ 'border-destructive': form.errors.name }" />
        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
      </div>

      <div class="space-y-2">
        <Label for="email">Email</Label>
        <Input id="email" v-model="form.email" type="email" autocomplete="username" required :class="{ 'border-destructive': form.errors.email }" />
        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
      </div>

      <div v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm">
        <p>
          Your email address is unverified.
          <Link :href="route('verification.send')" method="post" as="button" class="text-primary underline underline-offset-4 hover:text-primary/80">
            Re-send the verification email.
          </Link>
        </p>
        <p v-show="status === 'verification-link-sent'" class="mt-2 font-medium text-green-700">
          A new verification link has been sent to your email address.
        </p>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Changes' }}</Button>
        <p v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">Saved.</p>
      </div>
    </form>
  </section>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineProps({
  mustVerifyEmail: Boolean,
  status: String,
})

const user = usePage().props.auth.user
const form = useForm({
  name: user.name,
  email: user.email,
})
</script>
