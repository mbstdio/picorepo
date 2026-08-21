<template>
  <Head title="Profile" />

  <AppLayout>
    <div class="max-w-2xl space-y-6">
      <div>
        <h1 class="text-2xl font-bold">Profile</h1>
        <p class="text-muted-foreground">Manage your account information and security settings.</p>
      </div>

      <Card>
        <CardContent>
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
          />
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <UpdatePasswordForm />
        </CardContent>
      </Card>

      <Card class="border-destructive/30">
        <CardContent>
          <DeleteUserForm />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'

defineProps({
  mustVerifyEmail: Boolean,
  status: String,
})
</script>
