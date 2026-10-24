import { shallowRef } from 'vue'

export function useClipboard() {
  const status = shallowRef('')
  const copying = shallowRef(false)

  async function copy(text) {
    if (copying.value) return
    copying.value = true
    status.value = ''
    try {
      await navigator.clipboard.writeText(text)
      status.value = 'Copied to clipboard.'
    } catch {
      status.value = 'Could not copy. Select and copy the text manually.'
    } finally {
      copying.value = false
    }
  }

  return { status, copying, copy }
}
