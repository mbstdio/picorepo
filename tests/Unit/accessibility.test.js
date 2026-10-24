import assert from 'node:assert/strict'
import { test } from 'node:test'
import { useClipboard } from '../../resources/js/composables/useClipboard.js'
import { useConfirmedAction } from '../../resources/js/composables/useConfirmedAction.js'

function replaceGlobal(t, name, value) {
  const descriptor = Object.getOwnPropertyDescriptor(globalThis, name)
  Object.defineProperty(globalThis, name, { value, configurable: true })
  t.after(() => {
    if (descriptor) Object.defineProperty(globalThis, name, descriptor)
    else delete globalThis[name]
  })
}

test('clipboard reports success only after writing and reports failures without throwing', async (t) => {
  let finish
  let writes = 0
  replaceGlobal(t, 'navigator', { clipboard: { writeText: text => {
    assert.equal(text, 'composer configuration')
    writes++
    return new Promise(resolve => { finish = resolve })
  } } })
  const { copy, status, copying } = useClipboard()
  const result = copy('composer configuration')
  assert.equal(copying.value, true)
  assert.equal(status.value, '')
  await copy('composer configuration')
  assert.equal(writes, 1, 'duplicate clicks do not start another write')
  finish()
  await result
  assert.equal(copying.value, false)
  assert.equal(status.value, 'Copied to clipboard.')

  navigator.clipboard.writeText = async () => { throw new Error('Permission denied') }
  await copy('secret')
  assert.equal(copying.value, false)
  assert.equal(status.value, 'Could not copy. Select and copy the text manually.')
  delete navigator.clipboard
  await copy('secret')
  assert.equal(status.value, 'Could not copy. Select and copy the text manually.')
})

test('destructive actions require confirmation, block duplicates, and report server outcomes', (t) => {
  let accepted = false
  replaceGlobal(t, 'window', { confirm: message => {
    assert.equal(message, 'Revoke laptop token?')
    return accepted
  } })
  const { run, pending, status } = useConfirmedAction()
  let callbacks
  let requests = 0
  const visit = options => { callbacks = options; requests++ }
  run('Revoke laptop token?', visit)
  assert.equal(requests, 0)
  assert.equal(pending.value, false)
  accepted = true
  run('Revoke laptop token?', visit)
  assert.equal(pending.value, true)
  assert.equal(status.value, 'Working…')
  run('Revoke laptop token?', visit)
  assert.equal(requests, 1)
  callbacks.onError({ role: 'At least one owner is required.' })
  callbacks.onFinish()
  assert.equal(pending.value, false)
  assert.equal(status.value, 'At least one owner is required.')
  run('Revoke laptop token?', visit)
  callbacks.onSuccess()
  callbacks.onFinish()
  assert.equal(requests, 2)
  assert.equal(status.value, 'Changes saved.')
})
