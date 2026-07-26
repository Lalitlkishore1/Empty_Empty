import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL;

if (!baseURL) {
  throw new Error('E2E_BASE_URL is required for GalaxyOne staging tests.');
}

export default defineConfig({
  testDir: '.',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    browserName: 'chromium',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: [
    {
      name: 'desktop',
      use: {
        viewport: { width: 1280, height: 900 }
      }
    },
    {
      name: 'mobile',
      use: {
        ...devices['iPhone 13']
      }
    }
  ]
});
