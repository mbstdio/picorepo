import { watch } from 'vue'
import { toast } from 'vue-sonner'

export function useFlashNotifications(getFlash) {
  return watch(getFlash, (flash) => {
    if (flash?.success) toast.success(flash.success, { duration: 5000 })
    if (flash?.error) toast.error(flash.error, { duration: Infinity })
  }, { immediate: true, flush: 'post' })
}
