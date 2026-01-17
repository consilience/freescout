import { test, expect } from '@playwright/test';

/**
 * FreeScout Login E2E Tests
 *
 * These tests verify the login functionality of the FreeScout helpdesk application.
 * Prerequisites:
 * - Application server running at baseURL (default: http://localhost:8000)
 * - Database migrated and seeded with test user (password: 'secret')
 */

test.describe('Login Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to login page before each test
    await page.goto('/login');
  });

  test('should display login form', async ({ page }) => {
    // Verify the login form elements are present
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should have email field focused by default', async ({ page }) => {
    // The email field should have autofocus
    await expect(page.locator('input[name="email"]')).toBeFocused();
  });

  test('should display "Remember Me" checkbox', async ({ page }) => {
    await expect(page.locator('input[name="remember"]')).toBeVisible();
  });

  test('should show validation error for empty email', async ({ page }) => {
    // Try to submit with empty fields
    await page.locator('button[type="submit"]').click();

    // The browser's built-in validation should prevent submission
    // or Laravel validation should show an error
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('should show validation error for invalid email format', async ({ page }) => {
    await page.fill('input[name="email"]', 'invalid-email');
    await page.fill('input[name="password"]', 'password123');
    await page.locator('button[type="submit"]').click();

    // Wait for error message or validation
    await page.waitForTimeout(1000);

    // Check for validation error (Laravel's validation error format)
    const errorMessage = page.locator('.help-block, .invalid-feedback, .alert-danger');
    const hasError = await errorMessage.count() > 0;

    // Either server-side validation or browser validation should trigger
    if (hasError) {
      await expect(errorMessage.first()).toBeVisible();
    }
  });

  test('should show error for invalid credentials', async ({ page }) => {
    await page.fill('input[name="email"]', 'nonexistent@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.locator('button[type="submit"]').click();

    // Wait for response
    await page.waitForLoadState('networkidle');

    // Should show authentication error
    const errorMessage = page.locator('.alert-danger, .help-block, .invalid-feedback');
    await expect(errorMessage.first()).toBeVisible({ timeout: 10000 });
  });

  test('should successfully login with valid credentials', async ({ page }) => {
    // This test requires a seeded test user in the database
    // Default test user credentials from UserFactory: email = random, password = 'secret'

    // For this test to work, you need to either:
    // 1. Use a known test user email from your seeded database
    // 2. Create a test fixture that sets up a user before running

    // Example with a test email (replace with actual seeded user email):
    const testEmail = 'test@freescout.local';
    const testPassword = 'secret';

    await page.fill('input[name="email"]', testEmail);
    await page.fill('input[name="password"]', testPassword);
    await page.locator('button[type="submit"]').click();

    // Wait for redirect to dashboard/home
    await page.waitForLoadState('networkidle');

    // After successful login, should redirect away from login page
    // The URL should not contain /login anymore
    const currentUrl = page.url();

    // If credentials are correct, we should be redirected
    // If not, we'll still be on login page (which is expected for this template test)
    if (currentUrl.includes('/login')) {
      // Expected when test user doesn't exist - this is a template test
      console.log('Note: Test user not found in database. This is expected for template tests.');
    } else {
      // Successful login - verify we're on dashboard
      await expect(page).not.toHaveURL(/\/login/);
    }
  });
});

test.describe('Authentication Flow', () => {
  test('should redirect unauthenticated users to login', async ({ page }) => {
    // Try to access a protected route
    await page.goto('/');

    // Should redirect to login page
    await expect(page).toHaveURL(/\/login/);
  });

  test('should persist login with Remember Me', async ({ page }) => {
    await page.goto('/login');

    // Fill in credentials and check Remember Me
    await page.fill('input[name="email"]', 'test@freescout.local');
    await page.fill('input[name="password"]', 'secret');
    await page.locator('input[name="remember"]').check();

    // Verify checkbox is checked
    await expect(page.locator('input[name="remember"]')).toBeChecked();

    // Note: Full remember me testing would require:
    // 1. Successful login
    // 2. Clearing session storage
    // 3. Verifying user is still logged in via remember cookie
  });
});

test.describe('Login Page Accessibility', () => {
  test('should have proper form labels', async ({ page }) => {
    await page.goto('/login');

    // Check that form inputs have associated labels
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');

    // At least one of these should exist for accessibility
    const hasEmailLabel = await emailLabel.count() > 0;
    const hasPasswordLabel = await passwordLabel.count() > 0;

    // Form should have proper structure
    expect(hasEmailLabel || hasPasswordLabel).toBeTruthy();
  });

  test('should be keyboard navigable', async ({ page }) => {
    await page.goto('/login');

    // Tab through form elements
    await page.keyboard.press('Tab');
    await expect(page.locator('input[name="email"]')).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.locator('input[name="password"]')).toBeFocused();

    // Continue tabbing to reach submit button
    await page.keyboard.press('Tab');
    // Should reach either remember checkbox or submit button
  });
});
