import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'

const build = new URL('../../public/build/', import.meta.url)
const manifest = JSON.parse(readFileSync(new URL('manifest.json', build), 'utf8'))
const css = manifest['resources/js/app.js'].css
  .map((file) => readFileSync(new URL(file, build), 'utf8')).join('\n')

function utilityRule(className, state = '') {
  const selector = '.' + className.replace(/[^a-zA-Z0-9_-]/g, '\\$&') + state
  const start = css.indexOf(selector)
  assert.notEqual(start, -1, `Production CSS must include ${selector}`)
  assert.ok(['{', ','].includes(css[start + selector.length]), `Expected complete selector ${selector}`)
  return css.slice(start, css.indexOf('}', start) + 1)
}

test('core controls have a three-pixel keyboard focus ring in production', () => {
  assert.match(utilityRule('focus-visible:ring-3', ':focus-visible'), /3px/)
  assert.match(utilityRule('focus-visible:ring-ring/50', ':focus-visible'), /--ring/)
})

test('popups use the dimensions and transform origins supplied by Reka', () => {
  for (const [className, declaration] of [
    ['w-(--reka-dropdown-menu-trigger-width)', 'width:var(--reka-dropdown-menu-trigger-width)'],
    ['max-h-(--reka-dropdown-menu-content-available-height)', 'max-height:var(--reka-dropdown-menu-content-available-height)'],
    ['max-h-(--reka-select-content-available-height)', 'max-height:var(--reka-select-content-available-height)'],
    ['origin-(--reka-select-content-transform-origin)', 'transform-origin:var(--reka-select-content-transform-origin)'],
  ]) {
    assert.ok(utilityRule(className).includes(declaration), `Expected ${declaration}`)
  }
})

test('popup animations and tabs respond to Reka data-state attributes', () => {
  assert.match(utilityRule('data-open:animate-in', ':where([data-state=open])'), /animation:enter /)
  assert.match(utilityRule('data-closed:animate-out', ':where([data-state=closed])'), /animation:exit /)
  assert.match(utilityRule('data-open:fade-in-0', ':where([data-state=open])'), /--tw-enter-opacity:0/)
  assert.match(utilityRule('data-closed:zoom-out-95', ':where([data-state=closed])'), /--tw-exit-scale:(?:\.95|95%)/)
  assert.match(utilityRule('data-[side=bottom]:slide-in-from-top-2', '[data-side=bottom]'), /--tw-enter-translate-y:/)
  assert.match(utilityRule('data-active:bg-background', ':where([data-state=active])'), /background-color:var\(--background\)/)
  assert.ok(css.includes('@keyframes enter{'))
  assert.ok(css.includes('@keyframes exit{'))
})

test('dark styles follow the theme selector class rather than the OS media query', () => {
  assert.match(utilityRule('dark:bg-input/30', ':where(.dark,.dark *)'), /background-color:var\(--input\)/)
  assert.ok(!css.includes('prefers-color-scheme'))
})
