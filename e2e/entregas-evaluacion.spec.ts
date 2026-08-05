import { test, expect, type Page } from '@playwright/test';

/**
 * E2E flow for the `entregas-evaluacion` change (PR5 — T-029).
 *
 * Acceptance scenarios covered:
 *  - RF-EVA-01: the evaluador sees their assignment cards (pending by default).
 *  - RF-EVA-04: the "Ver ya evaluados" toggle reveals evaluated cards and the
 *    detail opens in read-only mode after submitting a grade.
 *  - RF-EVA-03: submitting a grade succeeds and the detail becomes read-only.
 *
 * RUN: `npx playwright test e2e/entregas-evaluacion.spec.ts`
 * Requires: Laravel dev server on http://localhost:8000 with a seeded
 * evaluador externo that has at least one pending assignment. Credentials
 * come from E2E_EVALUADOR_EMAIL / E2E_EVALUADOR_PASSWORD (defaults below).
 * Full-stack execution is wired for Sprint 6 CI.
 */

test.use({ baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000' });

const EVALUADOR_EMAIL = process.env.E2E_EVALUADOR_EMAIL ?? 'evaluador@test.local';
const EVALUADOR_PASSWORD = process.env.E2E_EVALUADOR_PASSWORD ?? 'password';

async function loginEvaluador(page: Page): Promise<void> {
    await page.goto('/login/externo');
    await page.getByLabel('Usuario o Correo Electrónico').fill(EVALUADOR_EMAIL);
    await page.getByLabel('Contraseña').fill(EVALUADOR_PASSWORD);
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(/\/(dashboard|evaluador)/);
}

test.describe('Flujo evaluador — entregas-evaluacion', () => {
    test('login → ver cards → evaluar → detalle solo lectura', async ({ page }) => {
        await loginEvaluador(page);

        // RF-EVA-01 / RF-EVA-04: pending cards shown by default.
        await page.goto('/evaluador/mis-asignaciones');
        await expect(page.getByRole('heading', { name: 'Mis Asignaciones' })).toBeVisible();

        const firstEvaluar = page.getByRole('button', { name: 'Evaluar', exact: true }).first();

        await expect(page.getByTestId('asignacion-card').first()).toBeVisible();
        await expect(firstEvaluar).toBeVisible();

        // Navigate to the grading detail.
        await firstEvaluar.click();
        await page.waitForURL(/\/evaluador\/asignaciones\/\d+$/);

        // RF-EVA-03: submit a valid grade + observations.
        await expect(page.getByRole('heading', { name: /Calificación/ })).toBeVisible();
        await page.getByLabel(/Nota \(0/).fill('4.5');
        await page.getByLabel('Observaciones').fill('Documento bien estructurado');
        await page.getByRole('button', { name: 'Enviar Evaluación', exact: true }).click();

        // Success screen, then read-only detail (RF-EVA-04).
        await expect(page.getByRole('heading', { name: 'Evaluación enviada' })).toBeVisible();
        await page.getByRole('button', { name: 'Ver evaluación', exact: true }).click();

        // Read-only mode: inputs disabled and own nota/observaciones visible.
        await expect(page.getByLabel(/Nota \(0/)).toBeDisabled();
        await expect(page.getByLabel('Observaciones')).toBeDisabled();
        await expect(page.getByText(/Tu nota: 4\.50/)).toBeVisible();

        // Re-submit attempt is blocked client-side (button gone in read-only).
        await expect(page.getByRole('button', { name: 'Enviar Evaluación', exact: true })).toHaveCount(0);
    });

    test('toggle "Ver ya evaluados" revela las tarjetas evaluadas', async ({ page }) => {
        await loginEvaluador(page);
        await page.goto('/evaluador/mis-asignaciones');

        // Evaluated cards are hidden by default: only "Evaluar" buttons.
        await expect(page.getByRole('button', { name: 'Ver', exact: true })).toHaveCount(0);

        // RF-EVA-04: activating the toggle shows evaluated cards with "Ver".
        await page.getByRole('button', { name: 'Ver ya evaluados', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Ver', exact: true }).first()).toBeVisible();
        await expect(page.getByTestId('asignacion-card').first()).toBeVisible();
    });
});
