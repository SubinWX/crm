# Agency CRM

A desktop-first CRM for a web development and digital marketing agency: clients, services,
hosting renewals, invoices, payments, expenses, tasks and social media posters in one place.

**Stack:** Laravel 12 · MySQL 8 · Blade · Livewire 3 · Tailwind CSS 4 · dompdf.
Defaults to **INR** and **Asia/Kolkata**; both are changed in *Settings → General*.

---

## Requirements

- PHP **8.2 or newer** with `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`, `zip`
- Composer 2
- MySQL 8 (or MariaDB 10.6+)
- Node 20+ (only to build the CSS)

> If `php -v` shows an older version (for example Herd defaulting to 7.4), switch first:
> `herd use 8.3`, or call the binary directly (`php83 artisan …`).

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

### MySQL configuration

Create an empty database and point `.env` at it:

```sql
CREATE DATABASE client_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306          # MAMP uses 8889 by default
DB_DATABASE=client_crm
DB_USERNAME=root
DB_PASSWORD=
```

Then create the tables and the starter data (settings and dropdown values such as work types,
server types, payment methods and expense categories):

```bash
php artisan migrate --seed
```

`php artisan db:seed` is safe to run again; it only adds values that are missing.

### First Super Admin

Accounts are normally created by invitation, so the very first one is made from the terminal:

```bash
php artisan crm:create-admin
```

It asks for a name, email and password (or pass `--name= --email= --password=`). Sign in at
`/login` (Super Admins and Sub Admins use `/login`; Staff use `/staff`), then add everyone else from **Users → Add user**. Either type a password for them
(the account works immediately; share the password privately) or leave it empty to email an
invitation so they choose their own. Invitation links are valid for 7 days and can be re-sent.
The Users screen always shows the login link to copy, and a Super Admin can set a new password
for any other user from Users → Edit.

### Run it

```bash
php artisan serve            # http://localhost:8000
php artisan queue:work       # sends email and reminder deliveries
php artisan schedule:work    # runs the reminder check (local stand-in for cron)
```

`composer run dev` starts the web server, queue worker and Vite together.

### Optional demo data

```bash
php artisan db:seed --class=DemoSeeder
```

Adds sample clients, hosting, invoices, payments, expenses, tasks and posters, plus three
demo users (`admin@example.test`, `partner@example.test`, `staff@example.test`, password
`password`). For local use only; the seeder refuses to run in production.

---

## Email configuration

Out of the box `MAIL_MAILER=log`, so emails are written to `storage/logs/laravel.log`.
For real delivery set SMTP details in `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="crm@your-agency.com"
MAIL_FROM_NAME="${APP_NAME}"
APP_URL=https://crm.your-agency.com   # links inside emails use this
```

Emails sent: user invitations, password resets, "new client added", the two hosting payment
reminders, and the invoice planned-send reminder.

## Queue worker

Email and reminder deliveries are queued (`QUEUE_CONNECTION=database`), so a worker must be
running. In production keep it alive with Supervisor (or systemd):

```ini
[program:crm-queue]
command=php /var/www/crm/artisan queue:work --sleep=3 --tries=5 --max-time=3600
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/www/crm/storage/logs/queue.log
```

Run `php artisan queue:restart` after each deploy.

## Scheduler

Add one cron entry on the server:

```cron
* * * * * cd /var/www/crm && php artisan schedule:run >> /dev/null 2>&1
```

This runs `crm:send-reminders` every hour. To run it by hand, or to rehearse a future date:

```bash
php artisan crm:send-reminders
php artisan crm:send-reminders --date=2027-01-03
```

### How reminders work

| Reminder | Sent to | When |
|---|---|---|
| Hosting payment, first notice | every active Super Admin, in-app + email | two calendar months before the next payment date |
| Hosting payment, second notice | every active Super Admin, in-app + email | seven days before the next payment date |
| Invoice ready to send | every active Super Admin, in-app + email | on the invoice's Planned Send Date, while it is still a draft |

- Only **active** hosting records whose **Server Type is billed by the agency** ("Paid") and
  that have a payment date are reminded. Archived clients are skipped.
- Nothing is pre-scheduled. Dates are derived from the record on every run, so changing a
  billing date or planned send date automatically moves the reminders.
- Each delivery is tracked in `reminder_logs` by event, record, scheduled date, recipient and
  channel, under a unique index. Rerunning the scheduler or retrying a queue job cannot send
  a duplicate. Deliveries that were claimed but never confirmed are re-queued after an hour.
- If a scheduler run is missed, the next run catches up (the two-month notice while the
  seven-day one is not yet due; the seven-day notice until the payment date).
- A reminder is dropped at delivery time if the record became inactive, the invoice was
  already marked as sent, or the date moved after it was queued.

---

## Roles and permissions

| Area | Super Admin | Staff | Sub Admin |
|---|---|---|---|
| Dashboard | Full | Team view | Own clients only |
| Clients, hosting, client files | Yes | With `clients.view` / `clients.manage` | Add clients; view and edit only the clients they added (no archiving) |
| Tasks, posters, Paid Server Clients | Yes | Yes (`tasks.manage` / `posters.manage` to change) | No |
| Equipment | Manage everyone's | Own items, read-only | Own items, read-only |
| Invoices, payments, expenses, receipts, invoice PDFs | Yes | No | No |
| Support tickets | Yes | With `support.manage` | No |
| Support portal users | Manage all | No | Add, and manage the ones they added |
| Users, settings, activity log | Yes | No | No |

Staff permissions are ticked per user on the Users screen. Deactivating a user signs them out
and blocks sign-in but never deletes them, so payments they received, tasks and log entries
keep their name. The last active Super Admin cannot be deactivated or demoted.

## How money is calculated

- **Pending amount** for a client = totals of issued invoices (Sent, Partially Paid, Paid)
  minus payments applied to those invoices. Drafts and void invoices are excluded.
- **Unallocated payments** = payments received minus amounts applied to invoices. They are
  shown separately and can be applied later from the payment's page.
- Invoice status moves Draft → Sent → Partially Paid → Paid as payments are applied.
  **Overdue** is shown automatically when a sent or partially paid invoice passes its due date.
- Payments are recorded in the default currency.

## Sales and client work (leads, quotes, projects, renewals)

### Upgrading an existing install

```bash
composer install
php artisan migrate          # adds new tables and nullable columns only; existing data is untouched
php artisan db:seed          # adds missing starter values and, on first run, five starter quote templates
npm install && npm run build
php artisan queue:restart
```

The migration also gives every existing client a primary contact (and a billing contact where
billing details were filled in), copied from the details already on the client record. Back up
the database first, as with any upgrade: `mysqldump client_crm > backup.sql`.

### What each screen does

- **Settings → Quote Templates** (Super Admins): one template per product or service. Each has
  sections you can add, reorder, hide and remove (Introduction, Scope, Deliverables, Exclusions,
  Optional Add-ons, Timeline, Milestones, Payment Terms, Quote Validity, References), reusable
  line items with a billing frequency, and reference attachments such as your source proposal.
  Templates can be duplicated and archived. The five starter templates contain placeholder text
  marked `[Starter text — replace …]`.
- **Quotes & Proposals**: pick a client or lead and a recipient, add one or more templates,
  tailor the wording and prices, and save as a draft. One-time and recurring charges are totalled
  separately; discount and tax apply to every line. A quote stores its own copy of everything, so
  later template edits never change it.
- **Sending and revising**: *Mark as Sent* stores the PDF for that revision. To change a sent
  quote use *Revise*, which starts the next revision and keeps every earlier PDF under
  "Issued versions". Statuses are Draft, Sent, Accepted, Declined, and Expired (shown
  automatically once a sent quote passes its expiry date).
- **Invoice from a quote** (Super Admins): on an accepted quote, tick the lines to bill and adjust
  the amount for a deposit or milestone. The draft invoice records the quote and revision it came
  from, and the quote shows what has already been invoiced per line so one-time work cannot be
  billed twice. A quote for a lead asks you to convert the lead to a client first.
- **Leads**: stages New, Contacted, Proposal Sent, Won, Lost, with source, services requested,
  estimated value, owner and a next action with a due date. *Convert to client* carries over the
  contact, notes, contact log, follow-ups, quotes and activity; if a client with the same email or
  name exists the lead is linked to it instead of creating a duplicate.
- **Tasks & Follow-ups**: one list for work to do and things to chase, for clients and for
  leads. Filter by "Due today or overdue" and "Assigned to me". Sales staff also see lead next
  actions and quote follow-up dates that are due at the top of the page.
- **Client profile**: tabs for Contacts (primary, billing, approvals, daily), Contact Log
  (calls, emails, WhatsApp, meetings, notes), Quotes, Projects, Support, and one
  Hosting & Renewals tab.
- **Projects & Services**: the team-wide list of the same records.
- **Hosting & Renewals**: one section for everything that renews. *Overview* lists hosting,
  domains, maintenance and retainers together by date; *Paid Server Clients (Hosting)* is the
  hosting list with its overdue / 7 days / 2 months filters and Record Renewal; the other tabs
  hold domains, maintenance contracts and retainers.
- **Bugs, content changes and support requests** are support tickets. The team can open a
  ticket on a customer's behalf (*Support → Support Tickets → New ticket*, or the Support tab on a
  client profile), so there is one place for requests whether they arrive through the portal, by
  phone or by WhatsApp.
- **Renewal reminders**: domain, maintenance and retainer renewals use the same reminder engine as
  hosting (hosting itself is always reminded two months and seven days before). The days-before schedule is set per type in *Settings → General → Renewal reminders*
  (defaults: domain 60 and 7, maintenance 30 and 7, retainer 14 and 3).
- **Import / Export CSV**: on the Clients and Leads screens. Import walks through column
  matching, a preview, and duplicate checks (same email or name, in the CRM or repeated in the
  file) before anything is saved. Export downloads the list as currently filtered.

### Who can use them

| Area | Super Admin | Staff | Sub Admin |
|---|---|---|---|
| Quote templates | Yes | No | No |
| Leads, quotes, proposals, their PDFs, lead import/export | Yes | With `sales.manage` | No |
| Create an invoice from a quote | Yes | No | No |
| Tasks & Follow-ups; contact log on clients | Yes | View; change with `tasks.manage` (sales staff can add tasks on leads) | No |
| Projects, hosting & renewals, client import/export | Yes | View; change with `clients.manage` | Hosting on clients they added |
| Client contacts | Yes | View; change with `clients.manage` | On clients they added |

## Support portal

A public, separate section for clients at **`/support`** — link to it from your website menu or footer.

- **Accounts are separate from team logins.** Portal users live in their own table and sign in
  at `/support/login`; a portal account can never open the CRM, and team passwords do not work
  on the portal.
- **Sign-up:** anyone can create a portal account with email and password, or with Google.
  Email sign-ups must confirm their address from an emailed link (valid 48 hours) before they can
  submit or view tickets; Google sign-ins and accounts created by the team are already confirmed.
- **Tickets:** a signed-in customer submits a *Support request*, *New work request* or
  *Feedback* (with an optional 1–5 rating), can attach files, and gets a ticket number such as
  `SUP-2026-0001` on screen and by email. They follow replies and answer on the portal.
- **Team side:** *Support → Support Tickets* lists and filters tickets; open one to reply,
  add internal notes (never shown to the customer), set status and priority, assign it, and link
  it to a CRM client. Super Admins and Staff with the `support.manage` permission can use it and
  are notified of new tickets in the notification bell and by email.
- **Portal Users:** *Support → Portal Users* is the portal's login list. Super Admins manage all
  accounts (add, set a password, link to a client, disable). Sub Admins can add portal users and
  manage the ones they added.

### Google sign-in (optional)

The "Continue with Google" button appears only when these are set in `.env`:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

Create them at <https://console.cloud.google.com> → APIs & Services → Credentials → *OAuth client
ID* (type: Web application), and add this **Authorised redirect URI**:
`<APP_URL>/support/auth/google/callback` (for example `https://crm.your-agency.com/support/auth/google/callback`).
`APP_URL` in `.env` must match the address people actually use.

Ticket emails and notifications are queued, so the queue worker must be running.

## Branding, menu and help

- **Settings → Branding** (Super Admins): the app name, a brand colour and a sidebar colour (or one
  of the quick themes), the logo and the favicon. The whole app, both sign-in pages, the support
  portal and the PDFs follow them. Colours too light to read white text against are refused, and
  red stays reserved for overdue and urgent items.
- **Sidebar**: main menus (Clients & Work, Sales, Support, Finance, Administration) open to show
  their sub-menus. The menu holding the current page is always open; the rest remember their state
  in the browser.
- **Help & Tutorial** (bottom of the sidebar, `/help`): a how-to guide for the team that shows
  only the sections the signed-in person can use.

## PDF reports, logos and headshots

- **Download PDF** on the Invoices, Payments and Expenses screens produces one PDF listing every
  record that matches the filters currently applied (clear the filters to get everything).
  Expenses also keep the CSV export. A single invoice's own PDF is on the invoice page.
- A client's **brand logo** is added or changed on the client form (JPG, PNG or WebP, up to 2 MB).
- A user's **headshot** is added or changed from Users → Edit.

## Files

Contracts, invoice copies, expense receipts, poster images, client logos and headshots are stored on the private
`local` disk (`storage/app/private`) and are only served through authenticated,
permission-checked routes. Nothing is placed under `public/`. Back up this folder together
with the database.

## Tests

```bash
mysql -e "CREATE DATABASE client_crm_test"
php artisan test
```

The suite covers the full workflow (add client → hosting reminders → record renewal → invoice
reminder → mark sent → partial payment → PDF download) and access control for financial
records and attachments. It uses the `client_crm_test` database from `phpunit.xml`.

## Project map

```
app/Livewire/            screens (Dashboard, Clients, Hosting, Invoices, Payments, Expenses, Posters, Tasks, …)
app/Models/              Eloquent models; LogsActivity trait writes the activity log
app/Services/ReminderService.php   decides which reminders are due
app/Jobs/DeliverReminder.php       delivers one reminder exactly once
app/Notifications/       in-app + email notifications
app/Http/Controllers/FileController.php   authorised downloads, invoice PDF, CSV export
app/Providers/AppServiceProvider.php      permission gates and sign-in logging
database/migrations/     schema
database/seeders/        starter data (DatabaseSeeder) and demo data (DemoSeeder)
routes/web.php           routes and their permission middleware
routes/console.php       scheduler
```
