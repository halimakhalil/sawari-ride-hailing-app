# Sawari: A Ride-Booking App for Lahore

A rider books a car (Economy or Comfort) between two places in Lahore. A nearby driver accepts the ride, drives the rider and completes the trip. The system guarantees that **only one driver can ever take a ride**, even when several drivers tap "Accept" at the same moment.

- **Backend:** Laravel 12 (PHP 8.2+), MySQL 8 / MariaDB, Sanctum, PHPUnit
- **Frontend:** React 19, TypeScript, Vite, Tailwind CSS, Axios, Vitest, Playwright

Design details, diagrams and scaling notes are in **[ARCHITECTURE.md](ARCHITECTURE.md)**.

---



Simplified on purpose for this project:
- **No real map or GPS.** Places come from a fixed list of Lahore locations, the map is an illustration, and road distance is approximated as straight-line × 1.3.
- **Drivers don't move live;** they jump to the drop-off point after a trip.
- **Drivers pull requests** from a list instead of the system offering rides to one driver at a time. Requests don't expire automatically.
- **Status updates use polling** (every 4 seconds) instead of push.
- **Final fare equals the estimate;** cash only, no surge pricing.
- **Demo authentication:** bearer tokens in per-tab session storage; no sign-up or password reset. A production build would use Sanctum's cookie-based SPA auth.

Possible next steps: live driver locations and automatic dispatch, WebSocket updates, payments, driver cancellation and re-assignment, phone OTP sign-up, and monitoring.

---

*Built with the help of an AI coding assistant (Claude Code). All features and tests were verified by running them.*
