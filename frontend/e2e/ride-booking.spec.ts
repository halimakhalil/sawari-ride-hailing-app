import { expect, test, type Page } from '@playwright/test'

// Optional: E2E_SCREENSHOT_DIR=/some/dir saves screenshots of each step for review.
const screenshotDir = process.env.E2E_SCREENSHOT_DIR

async function snap(page: Page, name: string) {
  if (screenshotDir) await page.screenshot({ path: `${screenshotDir}/${name}.png`, fullPage: true })
}

async function signIn(page: Page, role: 'rider' | 'driver', demoLabel: RegExp) {
  await page.goto('/')
  await page.getByRole('tab', { name: role === 'rider' ? /rider/i : /driver/i }).click()
  await page.getByRole('button', { name: demoLabel }).click()
  await page.getByRole('button', { name: `Sign in as ${role}` }).click()
}

test('rider books a ride and follows it while a driver accepts, starts and completes it', async ({ browser }) => {
  const riderPage = await (await browser.newContext()).newPage()
  const driverPage = await (await browser.newContext()).newPage()

  // Rider books Liberty Market → Johar Town.
  await signIn(riderPage, 'rider', /ayesha/i)
  await expect(riderPage.getByRole('heading', { name: 'Where to?' })).toBeVisible()
  await snap(riderPage, '01-rider-booking-empty')

  await riderPage.getByLabel('Pickup location').selectOption({ label: 'Liberty Market — Gulberg' })
  await riderPage.getByLabel('Destination', { exact: true }).selectOption({ label: 'Johar Town — Johar Town' })
  await expect(riderPage.getByRole('radio', { name: /economy/i })).toContainText('PKR 600')
  await snap(riderPage, '02-rider-estimates')

  await riderPage.getByRole('button', { name: /request economy · pkr 600/i }).click()
  await expect(riderPage.getByRole('heading', { name: /finding your driver/i })).toBeVisible()
  await snap(riderPage, '03-rider-requested')

  // Driver sees the request nearby and accepts it.
  await signIn(driverPage, 'driver', /usman/i)
  const request = driverPage.getByRole('region', { name: /ride request #\d+ from ayesha khan/i })
  await expect(request).toBeVisible()
  await snap(driverPage, '04-driver-feed')
  await request.getByRole('button', { name: 'Accept ride' }).click()
  await expect(driverPage.getByRole('heading', { name: 'Pick up your rider' })).toBeVisible()

  // Rider's status card updates by polling.
  await expect(riderPage.getByRole('heading', { name: /driver on the way/i })).toBeVisible({ timeout: 10_000 })
  await expect(riderPage.getByText('Usman Tariq')).toBeVisible()
  await snap(riderPage, '05-rider-driver-assigned')

  await driverPage.getByRole('button', { name: /start trip/i }).click()
  await expect(riderPage.getByRole('heading', { name: /on your trip/i })).toBeVisible({ timeout: 10_000 })
  await expect(riderPage.getByRole('button', { name: 'Cancel ride' })).toHaveCount(0)

  await driverPage.getByRole('button', { name: /complete trip/i }).click()
  await expect(driverPage.getByText(/trip complete\. collect pkr 600/i)).toBeVisible()
  await snap(driverPage, '06-driver-completed')

  await expect(riderPage.getByRole('heading', { name: /trip completed/i })).toBeVisible({ timeout: 10_000 })
  await expect(riderPage.getByText('Final fare')).toBeVisible()
  await snap(riderPage, '07-rider-completed')
})

test('rider cancels a requested ride', async ({ page }) => {
  await signIn(page, 'rider', /bilal/i)
  await page.getByLabel('Pickup location').selectOption({ label: 'DHA Phase 5 — Defence' })
  await page.getByLabel('Destination', { exact: true }).selectOption({ label: 'Mall Road — Downtown' })
  await page.getByRole('button', { name: /request economy/i }).click()
  await expect(page.getByRole('heading', { name: /finding your driver/i })).toBeVisible()

  await page.getByRole('button', { name: 'Cancel ride' }).click()
  await page.getByRole('button', { name: 'Yes, cancel' }).click()

  await expect(page.getByRole('heading', { name: /ride cancelled/i })).toBeVisible()
  await page.getByRole('button', { name: 'Book another ride' }).click()
  await expect(page.getByRole('heading', { name: 'Where to?' })).toBeVisible()
})

test('mobile layout fits a phone screen', async ({ browser }) => {
  const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })).newPage()
  await page.goto('/')
  await snap(page, '08-mobile-login')
  await signIn(page, 'rider', /ayesha/i)
  // Either the booking form or a ride status card, depending on earlier tests.
  await expect(page.getByRole('heading', { name: 'Where to?' }).or(page.getByText(/^Ride #\d+$/))).toBeVisible()

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(overflow).toBeLessThanOrEqual(0)
  await snap(page, '09-mobile-booking')
})

test('two tabs in one browser keep separate sessions', async ({ browser }) => {
  // Pages in one context share storage exactly like tabs in one browser window.
  const context = await browser.newContext()
  const riderTab = await context.newPage()
  const driverTab = await context.newPage()

  await signIn(riderTab, 'rider', /bilal/i)
  await expect(riderTab.getByText('Bilal Ahmed')).toBeVisible()

  // A new tab starts signed out instead of inheriting the rider's session.
  await driverTab.goto('/')
  await expect(driverTab.getByRole('button', { name: /sign in as/i })).toBeVisible()
  await signIn(driverTab, 'driver', /hamza/i)
  await expect(driverTab.getByText('Hamza Iqbal')).toBeVisible()

  // Reloading keeps each tab's own account.
  await riderTab.reload()
  await expect(riderTab.getByText('Bilal Ahmed')).toBeVisible()

  // Signing out the driver tab leaves the rider tab signed in, even after its next poll.
  await driverTab.getByRole('button', { name: 'Sign out' }).click()
  await expect(driverTab.getByRole('button', { name: /sign in as/i })).toBeVisible()
  await riderTab.reload()
  await expect(riderTab.getByText('Bilal Ahmed')).toBeVisible()
  await expect(riderTab.getByRole('button', { name: 'Sign out' })).toBeVisible()
})
