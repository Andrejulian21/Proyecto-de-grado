/**
 * Frontend Validation Mode — temporary flag for wireframe/UX validation without backend.
 * Set to `false` when integrating real API services (Sprint 5+).
 */
export const FRONTEND_VALIDATION_MODE = true;

/** Simulated network latency for realistic loading states. */
export function mockDelay(ms = 350): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
