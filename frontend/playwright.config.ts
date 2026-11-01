import { defineConfig, devices } from '@playwright/test'

/**
 * Browser end-to-end tests against the real Laravel API and a real MySQL database.
 *
 * Runs an isolated stack so the dev database is never touched:
 *   - backend on :8001 using database E2E_DB_DATABASE (default ride_hailing_e2e), re-seeded on start
 *   - Vite dev server on :5175 proxying /api to that backend
 *
 * The E2E database must exist: CREATE DATABASE ride_hailing_e2e;
 * Host and credentials come from backend/.env.
 */
const backendPort = 8001
const frontendPort = 5175
const e2eDatabase = process.env.E2E_DB_DATABASE ?? 'ride_hailing_e2e'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  reporter: 'list',
  use: {
    baseURL: `http://127.0.0.1:${frontendPort}`,
    trace: 'retain-on-failure',
    ...devices['Desktop Chrome'],
  },
  webServer: [
    {
      // php -S rather than `artisan serve`, which does not forward DB_* variables to the server
      // process. Laravel's router script must run from public/.
      command: `php artisan migrate:fresh --seed --force && cd public && php -S 127.0.0.1:${backendPort} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
      cwd: '../backend',
      url: `http://127.0.0.1:${backendPort}/api/health`,
      env: { DB_DATABASE: e2eDatabase, APP_ENV: 'local' },
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: `npx vite --port ${frontendPort} --strictPort --host 127.0.0.1`,
      url: `http://127.0.0.1:${frontendPort}`,
      env: { VITE_PROXY_TARGET: `http://127.0.0.1:${backendPort}` },
      reuseExistingServer: false,
      timeout: 60_000,
    },
  ],
})
