import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/Browser',
  workers: 1,
  use: { baseURL: 'http://127.0.0.1:8010', browserName: 'chromium' },
  webServer: {
    command: 'php tests/Browser/server.php',
    url: 'http://127.0.0.1:8010/login',
    reuseExistingServer: false,
  },
})
