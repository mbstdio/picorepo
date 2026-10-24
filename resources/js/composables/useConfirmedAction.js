import { shallowRef } from 'vue'

export function useConfirmedAction() {
  const pending = shallowRef(false)
  const status = shallowRef('')

  function run(message, visit) {
    if (pending.value || !window.confirm(message)) return
    pending.value = true
    status.value = 'Working…'
    visit({
      onSuccess: () => { status.value = 'Changes saved.' },
      onError: (errors) => { status.value = Object.values(errors).join(' ') || 'Could not save changes.' },
      onFinish: () => { pending.value = false },
    })
  }

  return { pending, status, run }
}
