import { test, expect } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill(`${'long'.repeat(12)}@example.test`)
  await page.getByLabel('Password', { exact: true }).fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
})

test('created API tokens use theme-aware surfaces in dark mode', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/profile/api-tokens')
  await page.getByLabel('Token name').fill('Dark mode laptop')
  await page.getByRole('button', { name: 'Create', exact: true }).click()
  const alert = page.getByRole('alert').filter({ hasText: 'Token created — copy it now' })
  await expect(alert).toBeVisible()
  const background = await page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor)
  await expect(alert).toHaveCSS('background-color', background)
  const foreground = await page.locator('body').evaluate(el => getComputedStyle(el).color)
  await expect(alert.locator('code')).toHaveCSS('color', foreground)
  await expect(alert.locator('code')).not.toHaveCSS('background-color', 'rgb(255, 255, 255)')
})

test('revoking API tokens reports success only through a toast', async ({ page }) => {
  await page.goto('/profile/api-tokens')
  await page.getByLabel('Token name').fill('Revoke laptop')
  await page.getByRole('button', { name: 'Create', exact: true }).click()
  const revoke = page.getByRole('button', { name: 'Revoke token Revoke laptop', exact: true })
  await expect(revoke).toBeVisible()
  page.once('dialog', dialog => dialog.accept())
  await revoke.click()
  await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'Token deleted.' })).toBeVisible()
  await expect(revoke).toHaveCount(0)
  await expect(page.getByRole('status').filter({ hasText: 'Changes saved.' })).toHaveCount(0)
})

test('mobile users can navigate every application section and log out', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 })
  const trigger = page.getByRole('button', { name: 'Navigation and account menu', includeHidden: true })
  await trigger.click()
  await page.getByRole('menuitem', { name: 'Repositories', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Repositories', exact: true })).toBeVisible()
  await trigger.click()
  await expect(page.getByRole('menuitem', { name: 'Repositories', exact: true })).toHaveAttribute('aria-current', 'page')
  await page.getByRole('menuitem', { name: 'Profile', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible()
  await trigger.click()
  await page.getByRole('menuitem', { name: 'API Tokens', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'API Tokens', exact: true })).toBeVisible()
  await trigger.click()
  await page.getByRole('menuitem', { name: 'Dashboard', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
  await trigger.click()
  await page.getByRole('menuitem', { name: 'Logout', exact: true }).click()
  await expect(page).toHaveURL(/\/login$/)
})

test('menus expose expanded state and support keyboard navigation and focus return', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 })
  const trigger = page.getByRole('button', { name: 'Navigation and account menu', includeHidden: true })
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await trigger.focus()
  for (const key of ['Enter', 'Space', 'ArrowDown']) {
    await page.keyboard.press(key)
    await expect(trigger).toHaveAttribute('aria-expanded', 'true')
    await expect(page.getByRole('menuitem', { name: 'Dashboard', exact: true })).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(page.getByRole('menuitem', { name: 'Repositories', exact: true })).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(trigger).toHaveAttribute('aria-expanded', 'false')
    await expect(trigger).toBeFocused()
  }
  await page.keyboard.press('Enter')
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { name: 'Repositories', exact: true })).toBeVisible()

  await page.setViewportSize({ width: 1280, height: 800 })
  const primary = page.getByRole('navigation', { name: 'Primary' })
  await expect(primary.getByRole('link', { name: 'Repositories', exact: true })).toHaveAttribute('aria-current', 'page')
  await trigger.focus()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('menuitem', { name: 'Profile', exact: true })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(trigger).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(page.getByRole('combobox', { name: 'Theme' })).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(primary.getByRole('link', { name: 'Repositories', exact: true })).toBeFocused()
})

for (const [name, width, height, deviceScaleFactor] of [
  ['320px', 320, 720, 1],
  // 1280x800 at 200% browser zoom has a 640x400 CSS viewport and doubled DPR.
  ['200% zoom reflow', 640, 400, 2],
]) {
  test.describe(name, () => {
    test.use({ viewport: { width, height }, deviceScaleFactor })

    test('shell and account menu fit on every main page', async ({ page }, testInfo) => {
      for (const [path, label] of [
        ['/dashboard', 'Dashboard'],
        ['/repositories', 'Repositories'],
        ['/profile', 'Profile'],
        ['/profile/api-tokens', 'API Tokens'],
      ]) {
        await page.goto(path)
        await expect(page.getByRole('heading', { name: label, exact: true })).toBeVisible()
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width)
        const trigger = page.getByRole('button', { name: 'Navigation and account menu', includeHidden: true })
        await trigger.focus()
        await page.keyboard.press('Enter')
        const menu = page.getByRole('menu')
        await expect(menu.getByRole('menuitem', { name: label, exact: true })).toHaveAttribute('aria-current', 'page')
        const box = await menu.boundingBox()
        expect(box.x).toBeGreaterThanOrEqual(0)
        expect(box.x + box.width).toBeLessThanOrEqual(width)
        expect(box.y + box.height).toBeLessThanOrEqual(height)
        await menu.getByRole('menuitem', { name: 'Logout', exact: true }).scrollIntoViewIfNeeded()
        await expect(menu.getByRole('menuitem', { name: 'Logout', exact: true })).toBeInViewport()
        await page.screenshot({ path: testInfo.outputPath(`${label}.png`) })
        await page.keyboard.press('Escape')
        await expect(trigger).toBeFocused()
        expect(await trigger.evaluate(el => getComputedStyle(el).boxShadow)).not.toBe('none')
      }
    })
  })
}
