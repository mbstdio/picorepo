import assert from 'node:assert/strict'
import { test } from 'node:test'
import { effectScope, nextTick, shallowRef } from 'vue'
import { toast } from 'vue-sonner'
import { useFlashNotifications } from '../../resources/js/composables/useFlashNotifications.js'

test('flash notifications handle initial messages, repeated actions, errors and cleanup', async () => {
  const start = toast.getHistory().length
  const flash = shallowRef({ success: 'Saved.' })
  const scope = effectScope()
  scope.run(() => useFlashNotifications(() => flash.value))

  try {
    assert.equal(toast.getHistory().length, start + 1)
    assert.equal(toast.getHistory().at(-1).type, 'success')
    assert.equal(toast.getHistory().at(-1).duration, 5000)

    flash.value = { success: 'Saved.' }
    await nextTick()
    assert.equal(toast.getHistory().length, start + 2, 'identical messages from new actions are shown')

    flash.value = { error: 'Could not save.' }
    await nextTick()
    assert.equal(toast.getHistory().at(-1).type, 'error')
    assert.equal(toast.getHistory().at(-1).title, 'Could not save.')
    assert.equal(toast.getHistory().at(-1).duration, Infinity)

    flash.value = { token: 'secret-token' }
    await nextTick()
    flash.value = undefined
    await nextTick()
    assert.equal(toast.getHistory().length, start + 3, 'empty flash and tokens are not notified')

    scope.stop()
    flash.value = { success: 'After unmount.' }
    await nextTick()
    assert.equal(toast.getHistory().length, start + 3)
  } finally {
    scope.stop()
  }
})
