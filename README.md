# Sawari: A Ride-Booking App for Lahore

> **In one sentence:** a rider books a car (Economy or Comfort) between two places in Lahore, a nearby driver accepts it, drives the rider, and finishes the trip, and the system makes sure that **only one driver can ever take a ride**, even when several tap "Accept" at the same moment.

This README explains the whole project in plain language and contains a **step-by-step demo script** for presenting it. Deeper technical answers (diagrams, scaling, deployment) are in **[ARCHITECTURE.md](ARCHITECTURE.md)**.

---

## Table of contents

1. [What the app does](#1-what-the-app-does)
2. [The ride lifecycle (the heart of the project)](#2-the-ride-lifecycle-the-heart-of-the-project)
3. [How the project is built](#3-how-the-project-is-built)
4. [Starting the app](#4-starting-the-app)
5. [Demo accounts](#5-demo-accounts)
6. [Presentation demo script (step by step)](#6-presentation-demo-script-step-by-step)
7. [How the tricky problems are solved (in simple words)](#7-how-the-tricky-problems-are-solved-in-simple-words)
8. [The database](#8-the-database)
9. [The API](#9-the-api)
10. [Tests: how we know it works](#10-tests-how-we-know-it-works)
11. [Troubleshooting](#11-troubleshooting)
12. [Questions you may be asked (short answers)](#12-questions-you-may-be-asked-short-answers)
13. [Known limitations and next steps](#13-known-limitations-and-next-steps)
14. [AI tool disclosure](#14-ai-tool-disclosure)

---

## 1. What the app does

There are **two kinds of users**, and each has its own login:

### 🧍 Rider (passenger)
1. Chooses a **pickup** and a **destination** from a list of Lahore places (DHA, Gulberg, Liberty Market, Johar Town, Model Town, Mall Road, the airport, …).
2. Sees the **price** and the **pickup time** for both ride types:
   - **Economy**: cheaper everyday cars (Suzuki Alto, Cultus…)
   - **Comfort**: bigger cars (Corolla, Civic), about 45% more expensive
3. Taps **Request ride**.
4. Watches the ride's **status update live**: *Finding your driver → Driver on the way → On your trip → Trip completed*.
5. Sees the driver's **name, car and number plate** once a driver accepts.
6. Can **cancel** the ride, but only before the trip starts.

### 🚗 Driver
1. Goes **online** or **offline**.
2. Sees a list of **nearby ride requests** that match their car type, closest first.
3. **Accepts** a ride. If another driver was faster, they see *"Another driver has already accepted this ride."*
4. Taps **Start trip** after picking the rider up, and **Complete trip** at the destination.

### What the system guarantees
- ✅ One ride can only ever have **one** driver.
- ✅ One driver can only be on **one** ride at a time.
- ✅ A rider can only have **one** ongoing ride.
- ✅ Pressing "Request ride" twice (or a retry after a bad network) **never books two rides**.
- ✅ Impossible actions are refused: cancelling a finished ride, finishing a ride that never started, and so on.
- ✅ The **price is calculated by the server**, so a user cannot change their own fare.

---

## 2. The ride lifecycle (the heart of the project)

Every ride is always in exactly one of five states:

```
                 driver accepts          driver starts trip        driver completes
   REQUESTED ─────────────────► ACCEPTED ─────────────────► IN PROGRESS ─────────────────► COMPLETED
       │                            │
       │ rider cancels              │ rider cancels (driver is freed)
       ▼                            ▼
   CANCELLED ◄──────────────────────┘
```

| State | What it means | Rider sees | Driver sees |
|---|---|---|---|
| **requested** | Ride booked, waiting for a driver | "Finding your driver" + Cancel button | The ride in "Nearby ride requests" |
| **accepted** | A driver took it and is driving to the pickup | "Driver on the way" + driver name and plate + Cancel button | "Pick up your rider" + **Start trip** |
| **in_progress** | Rider is in the car | "On your trip" (no Cancel button any more) | **Complete trip** button |
| **completed** | Trip finished | "Trip completed" + final fare | "Trip complete. Collect PKR … in cash." |
| **cancelled** | Rider cancelled | "Ride cancelled" | The request disappears |

**Rules (all enforced by the server):**

| Who | Can do | Only when the ride is… |
|---|---|---|
| Driver | Accept | requested, and the driver is online, free, and has the right car type |
| Assigned driver | Start trip | accepted |
| Assigned driver | Complete trip | in progress |
| The rider who booked it | Cancel | requested or accepted |

Anything else is refused with a clear message. For example, cancelling a completed ride returns *"A ride that is completed cannot become cancelled."*

What happens to the **driver** along the way:
- **available** (online) → accepts a ride → **busy** → completes or the rider cancels → **available** again.
- After completing a trip, the driver's location moves to the **drop-off point**, just like in real life.

---

## 3. How the project is built

```
ride-hailing/
├── backend/        Laravel (PHP) API: all business rules and the database
├── frontend/       React website: rider screens and driver screens
├── docker/         MySQL start-up script for Docker
├── docker-compose.yml   runs the whole thing with one command
├── README.md       this file
└── ARCHITECTURE.md      technical design, diagrams, scaling answers
```

| Part | Technology | Simple explanation |
|---|---|---|
| Website | **React + TypeScript + Tailwind** | What the rider and driver see and click |
| API (server) | **Laravel 12 (PHP)** | Receives requests from the website, checks the rules, saves data |
| Database | **MySQL 8** (MariaDB also works) | Stores riders, drivers, rides, assignments |
| Login | **Laravel Sanctum** | Gives each user a token after login; riders and drivers are separate account types |
| Tests | **PHPUnit, Vitest, Playwright** | Automatic checks for the backend, the screens, and a real browser |

**How a click travels through the system:**

```
Rider clicks "Request ride"
   → React sends  POST /api/rides  with the login token
   → Laravel checks the input (Form Request)
   → RideService checks the rules and saves the ride inside a database transaction
   → Laravel replies with the ride as JSON
   → React shows "Finding your driver" and checks the status every 4 seconds
```

**Where the important code is:**

| File | What it does |
|---|---|
| `backend/app/Services/RideService.php` | **The brain**: request, accept, start, complete, cancel, with locking |
| `backend/app/Enums/RideStatus.php` | The list of states and the allowed moves between them |
| `backend/app/Services/FareCalculator.php` | The price formula |
| `backend/app/Services/DriverMatchingService.php` | Finds nearby drivers, pickup ETA, the driver's request list |
| `backend/database/migrations/` | The database tables and their safety rules |
| `backend/public/openapi.yaml` | Full API documentation |
| `frontend/src/components/rider/` | Booking form and live ride status |
| `frontend/src/components/driver/DriverDashboard.tsx` | Driver screen |
| `frontend/src/api/` | The only place the website talks to the server |

---

## 4. Starting the app

You can run it **either** with XAMPP (local) **or** with Docker. Pick one.

### Option A: Local with XAMPP (what this project was developed on)

**You need:** XAMPP (MySQL running), PHP 8.2+, Composer, Node.js 20+.

**First time only:**

1. Start **MySQL** in the XAMPP Control Panel.
2. Create the databases (in phpMyAdmin → SQL tab, or the MySQL console):
   ```sql
   CREATE DATABASE ride_hailing      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE ride_hailing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE ride_hailing_e2e  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   (`_test` is for backend tests and `_e2e` for browser tests. Your demo data is never touched by tests.)
3. Install the backend:
   ```bash
   cd backend
   composer install
   copy .env.example .env        # macOS/Linux/Git Bash: cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```
4. Install the frontend:
   ```bash
   cd frontend
   npm install
   ```

**Every time you run it: open two terminals and keep both open.**

```bash
# Terminal 1: backend API  → http://127.0.0.1:8000
cd backend
php artisan serve
```

```bash
# Terminal 2: website      → http://localhost:5173
cd frontend
npm run dev
```

Open the address Terminal 2 prints (normally **http://localhost:5173**; if that port is busy Vite uses 5174).

> ⚠️ The website needs the backend. If Terminal 1 is closed you will see `ECONNREFUSED 127.0.0.1:8000` in Terminal 2 and logins will fail.

### Option B: Docker (no XAMPP needed)

```bash
docker compose up --build
```

| Open | What |
|---|---|
| http://localhost:8080 | The website |
| http://localhost:8000/docs | API documentation (Swagger) |
| http://localhost:8000/api/health | Health check |

The database, test data and everything else are created automatically. Stop with `docker compose down`.

### Useful addresses (local option)

| Address | What |
|---|---|
| http://localhost:5173 | Website |
| http://127.0.0.1:8000/docs | Interactive API documentation (Swagger UI) |
| http://127.0.0.1:8000/api/health | Shows `"status":"ok"` when the server and database are fine |

---

## 5. Demo accounts

**Every account's password is `password`.** On the login page you don't need to type anything: click a demo button and it fills in the email and password for you.

| Who | Email | Car | Starts as | Starting location |
|---|---|---|---|---|
| **Ayesha** (rider) | `rider@example.com` | – | – | – |
| **Bilal** (rider) | `rider2@example.com` | – | – | – |
| **Usman** (driver) | `driver@example.com` | Economy, Suzuki Alto | 🟢 online | Gulberg |
| **Hamza** (driver) | `driver2@example.com` | Economy, Suzuki Cultus | 🟢 online | Johar Town |
| **Sana** (driver) | `driver3@example.com` | Comfort, Toyota Corolla | 🟢 online | DHA |
| **Imran** (driver) | `driver4@example.com` | Comfort, Honda Civic | ⚪ offline | Gulberg |
| **Faisal** (driver) | `driver5@example.com` | Economy, Suzuki Wagon R | ⚪ offline | Model Town |

### ⚠️ Why a driver might NOT see a ride (important for the demo)

A driver only sees a request when **all three** are true:
1. the driver is **online**;
2. the ride's type matches the driver's **car type** (Economy rides → Economy drivers);
3. the pickup is within **8 km** of the driver.

So for the demo, use these pairs:

| Rider picks up at… | Category | Driver who will see it |
|---|---|---|
| **Liberty Market**, Gulberg III, MM Alam Road | Economy | **Usman** (and **Faisal** after he goes online) |
| **DHA Phase 5 / Phase 6** | **Comfort** | **Sana** |
| Johar Town, Emporium Mall | Economy | **Hamza** |

Drivers **move to the drop-off point** after each completed trip. So **reset the data before presenting** (next section) so that everyone is back at their starting location.

---

## 6. Presentation demo script (step by step)

Total time: about 10–15 minutes. Each part says **what to click** and **what to say**.

### Step 0: Prepare (before the audience arrives)

1. Start XAMPP MySQL, then **reset the demo data** so every driver is back at their starting point:
   ```bash
   cd backend
   php artisan migrate:fresh --seed
   ```
2. Start the backend (`php artisan serve`) and the website (`npm run dev`) in two terminals.
3. Open http://127.0.0.1:8000/api/health and check it shows `"status":"ok"`.
4. Arrange **browser tabs** (or two windows side by side):
   - **Tab 1 → Rider** (Ayesha)
   - **Tab 2 → Driver** (Usman)
   - **Tab 3 → Driver** (Faisal), for the "race" demo
   - **Tab 4 → API docs** http://127.0.0.1:8000/docs

   Each tab keeps **its own login**, so one tab can be the rider and another a driver. Open new tabs by pasting the URL, not with "Duplicate tab".

---

### Part 1: The rider books a ride (≈ 3 min)

| Do | Say |
|---|---|
| **Tab 1:** click **Ayesha (rider)** → **Sign in as rider** | "Riders and drivers are separate account types with separate logins." |
| Click **Request ride** without choosing anything | "Validation: the form tells you what's missing." It shows *"Choose a pickup location."* |
| Pickup **Liberty Market**, destination **Johar Town** | "The map draws the route, and prices for both categories load from the server." |
| Point at **Economy PKR 600 · 4 min away** and **Comfort PKR 870** | "Fare = base fare + distance × rate, times the category multiplier. It's calculated on the **server**, so it can't be manipulated." |
| Leave **Economy** selected → click **Request Economy · PKR 600** | "The button shows a loading state, and the app sends a unique **Idempotency-Key** with the request, so a retry can never create a second booking." |
| Screen shows **Finding your driver** | "The ride is now **requested**. The screen checks for updates every 4 seconds." |

### Part 2: The driver accepts (≈ 2 min)

| Do | Say |
|---|---|
| **Tab 2:** click **I'm a driver** → **Usman · Economy** → **Sign in as driver** | "Usman is online, drives an Economy car, and is in Gulberg." |
| Point at the request card (*0.6 km to pickup*) | "Drivers only see requests that match their car type and are within 8 km, nearest first." |
| Click **Accept ride** | "Accepting runs one database transaction that **locks** the ride, so nobody else can take it at the same time." |
| **Switch to Tab 1** | Within a few seconds: **Driver on the way**, with Usman's name, car and plate **LEA-1234**. |

### Part 3: The trip (≈ 2 min)

| Do | Say |
|---|---|
| **Tab 2:** **Rider picked up · Start trip** | Ride → **in_progress**. |
| **Tab 1:** **On your trip**, and the **Cancel button is gone** | "You can't cancel once the trip has started; the server enforces this too." |
| **Tab 2:** **Arrived · Complete trip** | Driver sees *"Trip complete. Collect PKR 600 in cash."* Usman becomes **available** again. |
| **Tab 1:** **Trip completed**, **Final fare PKR 600** | "Lifecycle complete: requested → accepted → in progress → completed." Click **Book another ride**. |

### Part 4: Two drivers race for the same ride ⭐ (≈ 2 min)

This shows the most important requirement.

**First, put the drivers back in place.** After Part 3, Usman is at Johar Town (the drop-off point), too far from Liberty Market. In a **third terminal** run:
```bash
cd backend
php artisan migrate:fresh --seed
```
(The backend keeps running.) Old logins are now invalid, so **sign in again** in each tab: Tab 1 **Ayesha**, Tab 2 **Usman**.

| Do | Say |
|---|---|
| (Reset as above) | "Drivers move to the drop-off point after a trip, so I've reset the demo positions." |
| **Tab 1 (Ayesha):** book **Liberty Market → Johar Town, Economy** | |
| **Tab 3:** sign in as **Faisal · Economy (offline)** → click **Go online** | "Faisal was offline, so he saw nothing. Now he's online and sees the request too." |
| Both **Tab 2 (Usman)** and **Tab 3 (Faisal)** now show the same request | "Two drivers, one ride." |
| Click **Accept ride** in **Tab 3 (Faisal)** first, then **quickly (within ~3 s)** **Accept ride** in **Tab 2 (Usman)**. Put the two windows side by side to be fast. | Usman sees: ***"Another driver has already accepted this ride."*** (If you wait longer, Usman's list refreshes and the ride simply disappears from it, which also shows it's taken.) |
| **Tab 1:** shows **Faisal** as the driver | "Exactly one driver won. Even if both had clicked in the very same millisecond, the database lock lets only one through. Our automated tests prove this with **8 drivers clicking at the same instant** (Part 7)." |

> 💡 **Shortcut** if you don't want to reset between Parts 3 and 4: do Part 4 **first** (right after Part 1), then continue the trip with whichever driver won.

### Part 5: Cancelling (≈ 1 min)

| Do | Say |
|---|---|
| **Tab 1:** finish or cancel the current ride, then book **DHA Phase 5 → Mall Road** | |
| Click **Cancel ride** → a confirmation appears → **Yes, cancel** | "There's a confirmation step, then the status becomes **Ride cancelled**." |
| (Optional) cancel **after** a driver accepted | "The driver is automatically freed and goes back to available." |

### Part 6: The server refuses impossible actions (≈ 2 min, using Swagger)

Open **Tab 4: http://127.0.0.1:8000/docs**.

1. **Log in through Swagger:**
   - Open **POST /auth/rider/login** → **Try it out** → Execute (the example body already has `rider@example.com` / `password`).
   - Copy the `token` value from the response.
   - Click **Authorize** (top right) → paste the token → **Authorize**.
2. **Invalid transition:** open **POST /rides/{id}/cancel** → Try it out → enter the **id of the completed ride** from Part 3 (usually `1`) → Execute.
   → **409** with code `INVALID_RIDE_TRANSITION`: *"A ride that is completed cannot become cancelled."*
   **Say:** "The response also lists which moves *are* allowed from that state."
3. **Duplicate request protection (idempotency):** open **POST /rides** → Try it out → type any value in **Idempotency-Key** (e.g. `demo-key-12345`) → Execute **twice**.
   - First time: **201 Created**, a new ride.
   - Second time: **200 OK** with the **same ride id** and header `Idempotent-Replayed: true`.
   **Say:** "A phone that loses signal and retries can never create two bookings."
   (If you already have an active ride you get **409 ACTIVE_RIDE_EXISTS** instead. That's another protection: one active ride per rider.)
4. **Wrong user:** with a **rider** token, try **POST /rides/{id}/accept** → **403** `WRONG_ACCOUNT_TYPE`.
   **Say:** "Riders can't use driver actions, and the driver is always identified by their login token, never by an ID sent from the browser."

### Part 7: Show the automated tests (≈ 2 min)

```bash
cd backend
php artisan test --testsuite=Concurrency
```

**Say:** "These tests start **separate processes** that hit the database at the same moment:
- 8 drivers accept one ride at once → exactly **1** wins, 7 are refused;
- one driver accepts 2 rides at once → only 1 succeeds;
- 6 identical retries at once → only **1** ride is created."

Then the full suites:
```bash
cd backend  && php artisan test       # 90 tests
cd frontend && npm test               # 27 tests (screens: loading, errors, validation, cancel…)
cd frontend && npm run test:e2e       # 4 real-browser tests (whole flow, two tabs, mobile)
```

### Part 8: Show it works on a phone (30 s)

Press **F12** → toggle the device toolbar (phone icon) → choose an iPhone. The layout stacks: map on top, form below.

---

## 7. How the tricky problems are solved (in simple words)

### Problem 1: Two drivers tap "Accept" at the same moment

**Analogy:** there is one key to the ride's room. The first driver takes the key, checks the ride is still free, writes their name on it, and hands the key back. The second driver has to **wait for the key**. When they get it, they see a name is already written and are politely refused.

**Technically:**
1. Accept runs inside a **database transaction** (all or nothing).
2. It **locks the ride row** (`SELECT … FOR UPDATE`), so other accepts for the same ride wait.
3. It checks the ride is still `requested`, then locks the **driver row** and checks the driver is online, free and has the right car type.
4. It creates the assignment, marks the ride `accepted` and the driver `busy`, then **commits**.
5. Everyone who waited now sees `accepted` → **409 "Another driver has already accepted this ride."**

**Extra safety net:** the database itself has a **unique rule** that makes a second active driver for the same ride impossible. Even buggy code could not break it.

### Problem 2: Network retries and double-clicks

**Analogy:** every booking attempt carries a **receipt number** (the *Idempotency-Key*). If the same receipt number arrives twice, the server says "I've already handled this one; here's your ride" instead of booking again.

- The website creates a new receipt number for each booking attempt and **reuses it when retrying**.
- The database has a unique rule on (rider + receipt number), so even two identical requests arriving **at the same instant** produce one ride.
- Same receipt number but a **different** trip → refused (`IDEMPOTENCY_KEY_REUSED`).
- Without any receipt number, there's still protection: **a rider can only have one active ride**, enforced by the database.

### Problem 3: Invalid actions

All allowed moves are written in **one place** (`RideStatus.php`). Every change goes through that list. If a move isn't on the list, the server refuses it with **409** and explains the current state and what is allowed.

### Problem 4: Wrong person doing an action

- The rider's or driver's identity comes from their **login token**, never from data the browser sends.
- Only the **rider who booked** can cancel. Only the **assigned driver** can start or complete. Riders can't use driver buttons and vice versa (**403**).

### Problem 5: Errors on the website

- Every server error has the same shape: a **code** and a **friendly message**.
- Form mistakes show next to the right field.
- If the network fails while booking: *"We couldn't confirm your booking. Retrying is safe."* with a **Try again** button (same receipt number, so no double booking).
- If live updates fail for a moment: *"Connection problem: reconnecting…"*, while the last known status stays on screen.
- Every screen has a **loading**, **empty** (e.g. "No requests nearby"), **error** and **success** state.

### How the price is calculated

```
road distance = straight-line distance × 1.3
fare          = (PKR 120 + PKR 45 × road km), at least PKR 200
              × 1.0 for Economy, × 1.45 for Comfort
              rounded up to the next PKR 5
```

Example: Liberty Market → Johar Town is ≈ 10.6 road km → **PKR 600** Economy, **PKR 870** Comfort.
**Pickup time** = distance of the nearest available driver ÷ 22 km/h + 2 minutes.
All numbers can be changed in `backend/.env` without touching code.

---

## 8. The database

Five main tables:

| Table | Stores | Important rules |
|---|---|---|
| `riders` | Passengers | unique email and phone |
| `drivers` | Drivers, car type, **online/busy/offline**, location | unique email, phone, number plate |
| `rides` | Each ride: places, coordinates, category, **status**, fares, timestamps | fare can't be negative; **one active ride per rider** |
| `driver_assignments` | Which driver serves which ride (keeps history) | **one active driver per ride**, **one active ride per driver** |
| `idempotency_keys` | Receipt numbers for retries | unique per rider + key |

```
riders ──< rides ──< driver_assignments >── drivers
   └──< idempotency_keys
```

**Why a separate `driver_assignments` table?** It keeps the history (who drove which ride, when) and would allow re-assigning a ride if a driver dropped out, while the database still allows only **one active** assignment at a time.

**Money** is stored as `DECIMAL(10,2)`, never as a floating-point number.

---

## 9. The API

Full interactive documentation: **http://127.0.0.1:8000/docs** (file: `backend/public/openapi.yaml`).

| Method | Address | Who | What it does |
|---|---|---|---|
| POST | `/api/auth/rider/login` · `/api/auth/driver/login` | anyone | Log in, get a token |
| POST | `/api/fare-estimates` | rider | Price and ETA for both categories |
| POST | `/api/rides` | rider | **Request a ride** |
| GET | `/api/rides/{id}` | rider / driver | **Ride details and status** |
| POST | `/api/rides/{id}/accept` | driver | **Accept and assign a driver** |
| POST | `/api/rides/{id}/start` | assigned driver | Start the trip |
| POST | `/api/rides/{id}/complete` | assigned driver | **Complete the ride** |
| POST | `/api/rides/{id}/cancel` | rider who booked | **Cancel the ride** |
| GET | `/api/rides` | rider | My rides (`?status=active` = current one) |
| GET | `/api/driver/rides/available` | driver | Nearby requests |
| GET | `/api/driver/rides/current` | driver | My current ride |
| PUT | `/api/driver/availability` | driver | Go online / offline |
| GET | `/api/locations` · `/api/health` | anyone | Place list · health check |

The endpoints in **bold** are the ones the assignment required. `start` was added because a trip has to start before it can be completed.

**Response codes:** `200` OK · `201` created · `401` not logged in · `403` not allowed · `404` not found · `409` not possible right now (e.g. ride already taken) · `422` invalid input · `500` server error.

**Every error looks like this:**
```json
{ "error": { "code": "RIDE_ALREADY_ASSIGNED",
             "message": "Another driver has already accepted this ride.",
             "details": {}, "request_id": "8f1c…" } }
```
The `request_id` also appears in the server log, so a reported error can be traced.

<details>
<summary>Command-line examples (curl, Git Bash)</summary>

```bash
API=http://127.0.0.1:8000/api
H='-H Content-Type:application/json -H Accept:application/json'

RIDER=$(curl -s $H -X POST $API/auth/rider/login  -d '{"email":"rider@example.com","password":"password"}'  | jq -r .data.token)
DRIVER=$(curl -s $H -X POST $API/auth/driver/login -d '{"email":"driver@example.com","password":"password"}' | jq -r .data.token)

BODY='{"pickup":{"address":"Liberty Market","latitude":31.5104,"longitude":74.3446},
       "destination":{"address":"Johar Town","latitude":31.4697,"longitude":74.2728},"category":"economy"}'

curl -si $H -H "Authorization: Bearer $RIDER" -H 'Idempotency-Key: demo-1234' -X POST $API/rides -d "$BODY"  # 201
curl -si $H -H "Authorization: Bearer $RIDER" -H 'Idempotency-Key: demo-1234' -X POST $API/rides -d "$BODY"  # 200, same ride
curl -s  $H -H "Authorization: Bearer $DRIVER" -X POST $API/rides/1/accept      # accepted
curl -s  $H -H "Authorization: Bearer $DRIVER" -X POST $API/rides/1/accept      # 409 already assigned
curl -s  $H -H "Authorization: Bearer $DRIVER" -X POST $API/rides/1/start
curl -s  $H -H "Authorization: Bearer $DRIVER" -X POST $API/rides/1/complete
curl -s  $H -H "Authorization: Bearer $RIDER"  -X POST $API/rides/1/cancel      # 409 invalid transition
```
</details>

---

## 10. Tests: how we know it works

| Command | Tests | What they check |
|---|---|---|
| `cd backend && php artisan test` | **90** | Booking, bad input, prices, driver matching, accepting, invalid moves, cancelling, completing, retries/idempotency, permissions, database rules, the full flow through the API |
| `php artisan test --testsuite=Concurrency` | 5 of the 90 | **Real simultaneous requests** from separate processes (8 drivers vs 1 ride, etc.) |
| `cd frontend && npm test` | **27** | Form shows correctly, required fields, choosing a category, loading state, success, error messages, retry, cancel |
| `cd frontend && npm run test:e2e` | **4** | A real Chrome browser: rider books → driver accepts, starts, completes → rider sees each step; cancel; two tabs with different logins; phone layout |
| `npm run lint` · `npm run typecheck` · `./vendor/bin/pint --test` | – | Code style and type checks |

- Backend tests use the separate **`ride_hailing_test`** database, and browser tests use **`ride_hailing_e2e`**. Your demo data is never changed by tests.
- Backend tests run on real MySQL (not a fake in-memory database), because only a real database can prove the locking works.
- The concurrency tests were checked by **deliberately removing the lock**: they then fail, which shows they really catch the problem.
- First time running the browser tests: `npx playwright install chromium`.
- Everything was also verified on **MySQL 8 in Docker**: `docker compose exec backend php artisan test` → 90 passed.

---

## 11. Troubleshooting

| Problem | Cause | Fix |
|---|---|---|
| Login fails; terminal shows `ECONNREFUSED 127.0.0.1:8000` | Backend isn't running | `cd backend && php artisan serve` and keep that terminal open |
| `/api/health` shows `database: unreachable` | MySQL is stopped | Start MySQL in XAMPP |
| Website opens on **5174** instead of 5173 | Another Vite server is already using 5173 | Use 5174, or close the other terminal running `npm run dev` |
| Driver sees **"No requests nearby right now"** | Wrong car type, more than 8 km away, or the driver moved after a trip | Use the pairs in [section 5](#5-demo-accounts), or reset with `php artisan migrate:fresh --seed` |
| Driver sees **"You're offline"** | Driver is offline | Click **Go online** |
| Rider gets **"You already have a ride in progress"** | One active ride per rider | Finish or cancel the current ride, or use the other rider (Bilal) |
| A new tab opens already logged in as the same user | Tab was **duplicated** (copies its login) | Open a fresh tab and paste the URL |
| "Go online" button is disabled | Driver is busy on a ride | Complete the ride first |
| Want a clean start | – | `cd backend && php artisan migrate:fresh --seed` (deletes all rides, restores demo users) |
| Want a larger driver search area for the demo | – | In `backend/.env` set `RIDES_MATCHING_RADIUS_KM=25`, then restart `php artisan serve` |

---

## 12. Questions you may be asked (short answers)

**Why Laravel + MySQL + React?**
Laravel gives validation, authentication, database migrations and testing out of the box, so the time went into the ride rules. MySQL gives transactions and row locks, which are exactly what "only one driver per ride" needs. React + TypeScript gives a typed, testable interface.

**How do you stop two drivers accepting the same ride?**
A database transaction that locks the ride row, so the second driver waits and then sees it's taken. Backed up by a unique database rule. Proven with tests where 8 processes accept at the same instant.

**How do you handle retries and duplicates?**
Idempotency keys: the same key returns the same ride. The database guarantees it even for simultaneous duplicates, and a rider can only have one active ride anyway.

**What does the frontend do when the API fails?**
It shows a clear message and keeps what's already on screen. Booking failures offer a safe "Try again" (same key, no double booking). If the login expires, it returns to the login screen. Form errors appear next to the field.

**Why one application and not microservices?**
It's one small domain. Splitting it would make the most important guarantee (one driver per ride) harder, because it would need distributed transactions. The code is still split into layers, so parts can be separated later.

**How would it scale to many cities and thousands of users?**
Run several copies of the API behind a load balancer (it keeps no state in memory), add a `city_id` and split data by city, use read replicas, keep live driver locations in Redis (geo search), and replace polling with WebSockets. Locks are per ride and per driver, so more users don't slow each other down.

**When would you add Redis, a queue, or separate services?**
Redis when live GPS locations and real-time updates become heavy. A queue when there are many side effects (SMS, notifications, receipts, payments). Separate services only for clearly separate areas such as payments or location tracking, with their own team or scaling needs.

**How would you deploy, monitor and roll back?**
Automated tests on every change, Docker images, rolling deploys with the health check, logs with request IDs, metrics and alerts (error rate, time-to-accept), and rollback by redeploying the previous image. Database changes are always backward compatible.

**Top three improvements before production?**
(1) Real dispatching with live GPS and automatic ride expiry. (2) Stronger security: phone OTP sign-up, driver verification, cookie-based login. (3) Real-time push updates, plus a queue for notifications and payments, and full monitoring.

More detail on all of these: **[ARCHITECTURE.md](ARCHITECTURE.md)**.

---

## 13. Known limitations and next steps

What is simplified on purpose:
- **No real map or GPS.** Places come from a fixed list of Lahore locations, and the map is a drawn illustration. Distance is a straight line × 1.3.
- **Drivers don't move live**; they only jump to the drop-off point after a trip.
- **Drivers pull requests** (a list) instead of the system offering the ride to one driver at a time. Requests don't expire automatically.
- **Status updates every 4 seconds** (polling) instead of instant push.
- **Final fare = estimate**, cash only, no surge pricing.
- **Demo login:** the token is kept in the browser tab's storage, and there's no sign-up or password reset.
- Developed on MariaDB 10.4 (XAMPP) and verified on MySQL 8.0 (Docker).

What would come next: live driver locations and automatic dispatch, push notifications, payments, driver cancellation and re-assignment, production login (phone OTP), and monitoring dashboards.

---

## 14. AI tool disclosure

This project was built with the help of an AI coding assistant (**Claude Code**), which wrote much of the code and documentation under my direction. Its output was **checked, not trusted blindly**:

- Every feature was tried against the running server and in a real browser (Playwright), including screenshots of every screen on desktop and mobile.
- All test suites were run repeatedly: 90 backend tests (including the multi-process concurrency tests), 27 frontend tests and 4 browser tests. Lint, type checks and code style are clean.
- The concurrency tests were **checked by breaking the code on purpose** (removing the lock); they failed as they should.
- The database structure was inspected directly, which caught a MariaDB setting that would have silently overwritten assignment times. It was fixed.
- Testing also caught and fixed: an HTML error page being treated as a success, Docker containers not passing database settings to the server, the test suite wiping the Docker demo database, and logins being shared between browser tabs.
