# Sawari Web (React + TypeScript + Vite)

Rider booking app and driver demo console. See the [root README](../README.md) for full setup and demo accounts.

```bash
npm install
npm run dev          # http://localhost:5173, proxies /api to http://127.0.0.1:8000
npm test             # Vitest + React Testing Library
npm run test:e2e     # Playwright (isolated backend on :8001, needs ride_hailing_e2e DB)
npm run lint && npm run typecheck && npm run build
```

| Path | Purpose |
|---|---|
| `src/api/` | Axios client, `ApiError`, typed service functions and API types |
| `src/auth/` | Session provider (token storage, 401 handling) |
| `src/components/rider/` | Booking form, ride status card, rider screen |
| `src/components/driver/` | Driver dashboard |
| `src/hooks/usePolling.ts` | Resilient status polling |
| `e2e/` | Playwright browser tests |
