import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { runInNewContext } from 'node:vm'

const template = readFileSync(new URL('../../resources/views/app.blade.php', import.meta.url), 'utf8')
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1]

test('login and its shared controls use theme colors instead of fixed light colors', () => {
  for (const [file, tokens] of [
    ['Layouts/GuestLayout.vue', ['bg-background', 'bg-card', 'text-card-foreground']],
    ['components/TextInput.vue', ['bg-background', 'text-foreground', 'border-input']],
    ['components/InputLabel.vue', ['text-foreground']],
    ['components/PrimaryButton.vue', ['bg-primary', 'text-primary-foreground']],
    ['components/Checkbox.vue', ['bg-background', 'text-primary', 'border-input']],
    ['Pages/Auth/Login.vue', ['text-muted-foreground', 'hover:text-foreground']],
  ]) {
    const source = readFileSync(new URL(`../../resources/js/${file}`, import.meta.url), 'utf8')
    for (const token of tokens) assert.ok(source.includes(token), `${file} must use ${token}`)
    assert.doesNotMatch(source, /(?:bg|text|border|ring)-(?:gray|indigo)-\d+|bg-white|text-white/, file)
  }
})

test('theme is applied before rendering, respecting saved and system preferences', () => {
  for (const [saved, systemDark, expected] of [
    ['light', true, false],
    ['dark', false, true],
    ['auto', true, true],
    ['auto', false, false],
    [null, true, true],
    [null, false, false],
    ['invalid', true, true],
    ['blocked', true, true],
  ]) {
    let dark
    runInNewContext(script, {
      localStorage: { getItem: () => {
        if (saved === 'blocked') throw new Error('Storage unavailable')
        return saved
      } },
      matchMedia: () => ({ matches: systemDark }),
      document: { documentElement: { classList: { toggle: (name, enabled) => {
        assert.equal(name, 'dark')
        dark = enabled
      } } } },
    })
    assert.equal(dark, expected, `${saved}, system dark: ${systemDark}`)
  }
})
