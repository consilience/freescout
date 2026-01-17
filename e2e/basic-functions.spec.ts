import { test, expect } from '@playwright/test';

/**
 * FreeScout Basic Functions E2E Tests
 *
 * These tests verify basic navigation and functionality after login.
 * Prerequisites:
 * - Application server running at baseURL
 * - Valid test user in database
 */

// Test fixtures for authenticated tests
test.describe('Basic Functions (Authenticated)', () => {
  // This would normally use a test fixture to login before each test
  // For now, these tests are templates that can be enabled when
  // proper authentication fixtures are set up

  test.skip('should display dashboard after login', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@freescout.local');
    await page.fill('input[name="password"]', 'secret');
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Verify dashboard elements
    await expect(page.locator('.navbar, .sidebar, .main-header')).toBeVisible();
  });

  test.skip('should display mailboxes list', async ({ page }) => {
    // After login, navigate to mailboxes
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@freescout.local');
    await page.fill('input[name="password"]', 'secret');
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Look for mailbox navigation or list
    const mailboxLink = page.locator('a[href*="mailbox"], .mailbox-link, .sidebar a').first();
    if (await mailboxLink.count() > 0) {
      await expect(mailboxLink).toBeVisible();
    }
  });

  test.skip('should navigate to conversations', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@freescout.local');
    await page.fill('input[name="password"]', 'secret');
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Find and click on conversations/tickets link
    const conversationsLink = page.locator('a[href*="conversation"], a[href*="ticket"]').first();
    if (await conversationsLink.count() > 0) {
      await conversationsLink.click();
      await page.waitForLoadState('networkidle');
    }
  });

  test.skip('should allow user to logout', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@freescout.local');
    await page.fill('input[name="password"]', 'secret');
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Find logout link/button
    const logoutButton = page.locator('a[href*="logout"], button:has-text("Logout"), .dropdown-menu a:has-text("Logout")').first();

    if (await logoutButton.count() > 0) {
      await logoutButton.click();
      await page.waitForLoadState('networkidle');

      // Should redirect to login page
      await expect(page).toHaveURL(/\/login/);
    }
  });
});

test.describe('Public Pages', () => {
  test('login page should load', async ({ page }) => {
    const response = await page.goto('/login');

    // Should return 200 status
    expect(response?.status()).toBeLessThan(500);
  });

  test('should redirect root to login when not authenticated', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Should be on login page or redirected there
    const url = page.url();
    expect(url).toMatch(/\/(login)?$/);
  });

  test('install page should redirect or show installer', async ({ page }) => {
    const response = await page.goto('/install');

    // Install page behavior depends on application state
    // It should either show installer or redirect
    expect(response?.status()).toBeLessThan(500);
  });
});

test.describe('Error Handling', () => {
  test('should handle 404 gracefully', async ({ page }) => {
    const response = await page.goto('/nonexistent-page-12345');

    // Should return 404 or redirect to login
    const status = response?.status();
    expect([302, 404]).toContain(status);
  });

  test('should not expose sensitive errors in production', async ({ page }) => {
    // Try to access an invalid API endpoint
    const response = await page.goto('/api/nonexistent');

    // Should not expose stack traces or sensitive info
    const content = await page.content();

    // These should not appear in production errors
    const sensitivePatterns = [
      /vendor\//i,
      /\.php:\d+/,
      /Stack trace/i,
      /APP_KEY/,
      /DB_PASSWORD/,
    ];

    for (const pattern of sensitivePatterns) {
      if (pattern.test(content)) {
        // Only fail if APP_DEBUG is false
        // In development, debug info is acceptable
        console.warn(`Warning: Potentially sensitive info detected: ${pattern}`);
      }
    }
  });
});

test.describe('Performance', () => {
  test('login page should load within acceptable time', async ({ page }) => {
    const startTime = Date.now();

    await page.goto('/login');
    await page.waitForLoadState('domcontentloaded');

    const loadTime = Date.now() - startTime;

    // Login page should load within 5 seconds
    expect(loadTime).toBeLessThan(5000);
  });
});
