@php
    $user = auth()->user();
    $can = fn (string $ability) => $user->can($ability);
    $team = $can('workspace');

    // Each section: [id, title, shown?]. Only the parts this person can use are listed.
    $sections = array_values(array_filter([
        ['start', 'Getting started', true],
        ['calendar', 'Calendar', true],
        ['clients', 'Clients', $can('clients.view')],
        ['sales', 'Leads, quotes and proposals', $can('sales.manage')],
        ['tasks', 'Tasks and follow-ups', $can('tasks.view')],
        ['renewals', 'Hosting and renewals', $team && $can('clients.view')],
        ['projects', 'Projects and services', $team && $can('clients.view')],
        ['finance', 'Invoices, payments and expenses', $can('finance')],
        ['support', 'Support portal and tickets', $can('support.manage') || $can('portal-users.manage')],
        ['posters', 'Social media posters', $can('posters.view')],
        ['equipment', 'Equipment', true],
        ['notifications', 'Notifications and reminders', $team],
        ['admin', 'Users, roles and settings', $can('admin')],
        ['faq', 'Common questions', true],
    ], fn ($s) => $s[2]));
@endphp

<x-layouts.app title="Help & Tutorial">
    <x-page-header title="Help & Tutorial" :subtitle="'How to use '.app_name().', step by step. You are signed in as '.$user->role_label.', so this page shows the parts you can use.'" />

    <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav class="card h-fit p-2 text-sm lg:sticky lg:top-20" aria-label="Help contents">
            <p class="px-3 pt-1 pb-1.5 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">On this page</p>
            @foreach ($sections as [$id, $title])
                <a href="#{{ $id }}" class="block rounded-md px-3 py-1.5 text-stone-700 hover:bg-stone-50">{{ $title }}</a>
            @endforeach
        </nav>

        <div class="max-w-3xl space-y-5 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-stone-900 [&_h3]:mt-4 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-stone-900 [&_li]:mt-1 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mt-2 [&_section]:scroll-mt-20 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">
            <section id="start" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Getting started</h2>
                <p>{{ app_name() }} keeps clients, renewals, sales, invoices, support and day-to-day work in one place. The menu on the left is grouped into main menus; click one to open its sub-menu. Your name at the top right opens <strong>Change Password</strong>, <strong>Change Picture</strong> and <strong>Logout</strong>. The bell next to it shows notifications.</p>
                <h3>Where to sign in</h3>
                <ul>
                    <li><strong>Super Admins and Sub Admins:</strong> <a class="link" href="{{ route('login') }}">{{ route('login') }}</a></li>
                    <li><strong>Staff:</strong> <a class="link" href="{{ route('staff.login') }}">{{ route('staff.login') }}</a></li>
                    <li><strong>Clients (support portal):</strong> <a class="link" href="{{ route('portal.home') }}">{{ route('portal.home') }}</a></li>
                </ul>
                <h3>Who can do what</h3>
                <ul>
                    <li><strong>Super Admin</strong> — everything, including finance, users and settings.</li>
                    <li><strong>Staff</strong> — the areas a Super Admin has ticked for them (clients, tasks, posters, sales, support).</li>
                    <li><strong>Sub Admin</strong> — adds clients and sees and edits only the clients they added.</li>
                </ul>
                <h3>Things that work the same everywhere</h3>
                <ul>
                    <li>Lists have a search box and filters at the top; filters stay in the page address, so you can bookmark a view.</li>
                    <li><span class="badge-red-solid">Red</span> always means overdue or urgent. Nothing else in the app is red.</li>
                    <li>Deleting asks for confirmation. Archiving or deactivating hides a record but keeps its history.</li>
                </ul>
            </section>

            <section id="calendar" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Calendar</h2>
                <p><strong>Calendar</strong> (under Dashboard) shows everything with a date on a month grid: team birthdays, clients added, hosting payments and renewals, invoice due dates, quote expiry dates, task and follow-up due dates, and project deadlines. You only see the kinds you can open elsewhere in the app.</p>
                <ul>
                    <li>Click a day to list everything on it; click it again to see the whole month.</li>
                    <li>The coloured buttons above the grid are the legend and the filter: click one to hide or show that kind.</li>
                    <li><span class="badge-red-solid">Red</span> items are overdue.</li>
                </ul>
            </section>

            @if ($can('clients.view'))
                <section id="clients" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Clients</h2>
                    <p>Open <strong>Clients &amp; Work → Clients</strong>. Search by name, contact, email or phone, and filter by status, industry, work type, source and account manager.</p>
                    @if ($can('clients.create'))
                        <h3>Add a client</h3>
                        <ol>
                            <li>Click <strong>New Client</strong>.</li>
                            <li>Fill in the company, the primary contact and how they found you (<em>Client By</em>, <em>Referred By</em>). You can add a brand logo.</li>
                            <li>Tick the work types they take. Optionally add their first hosting record on the same form.</li>
                            <li>Save. Every Super Admin is notified in the app and by email.</li>
                        </ol>
                    @endif
                    <h3>The client profile</h3>
                    <p>Click a client to open their profile. The tabs across the top hold everything about them:</p>
                    <ul>
                        <li><strong>Overview</strong> — details at a glance{{ $can('finance') ? ', plus invoiced, paid, pending and unallocated amounts' : '' }}.</li>
                        <li><strong>Contacts</strong> — the people you deal with: primary, billing, approvals, day-to-day.</li>
                        @if ($team) <li><strong>Contact Log</strong> — note every call, email, WhatsApp chat and meeting so the next person has the story.</li> @endif
                        <li><strong>Hosting &amp; Renewals</strong> — hosting records{{ $team ? ', domains, maintenance and retainers' : '' }} for this client.</li>
                        @if ($team) <li><strong>Projects</strong>, <strong>Tasks &amp; Follow-ups</strong> and <strong>Files</strong> (contracts and other documents).</li> @endif
                        @if ($can('sales.manage')) <li><strong>Quotes</strong> — every quote sent to this client.</li> @endif
                        @if ($can('support.manage')) <li><strong>Support</strong> — this client's support tickets.</li> @endif
                        @if ($can('finance')) <li><strong>Invoices &amp; Payments</strong> — invoices, payment history and what is still pending.</li> @endif
                    </ul>
                    @if ($can('clients.manage')) <p><strong>Import and export:</strong> the Clients screen has <em>Import CSV</em> (match columns, preview, duplicate check) and <em>Export CSV</em> (downloads the list as filtered).</p> @endif
                </section>
            @endif

            @if ($can('sales.manage'))
                <section id="sales" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Leads, quotes and proposals</h2>
                    <h3>From enquiry to client</h3>
                    <ol>
                        <li><strong>Sales → Leads → New Lead.</strong> Record the contact, services requested, estimated value and a next action with a due date.</li>
                        <li>Open the lead and log each call or message in the <strong>Contact log</strong>. Add tasks for anything to chase.</li>
                        <li>Click <strong>New Quote</strong> on the lead. Add one or more templates (for example a website build plus hosting plus maintenance), then tailor the wording and prices.</li>
                        <li><strong>Save draft</strong>, use <strong>Preview PDF</strong>, send the PDF to the customer yourself, then click <strong>Mark as Sent</strong>. That stores the issued PDF permanently.</li>
                        <li>When they answer, mark the quote <strong>Accepted</strong> or <strong>Declined</strong>.</li>
                        <li>Click <strong>Convert to client</strong>. The lead's contact, notes, log, tasks and quotes come across; nothing is retyped.</li>
                    </ol>
                    <h3>Changing a quote after sending</h3>
                    <p>Use <strong>Revise quote</strong>. It starts the next revision and keeps every earlier PDF under <em>Issued versions</em>. Editing a template never changes a quote that is already saved.</p>
                    <h3>Stages and statuses</h3>
                    <ul>
                        <li><strong>Lead stages:</strong> New → Contacted → Proposal Sent → Won or Lost. Logging a contact and sending a quote move the stage for you.</li>
                        <li><strong>Quote statuses:</strong> Draft, Sent, Accepted, Declined. A sent quote shows as <em>Expired</em> once its expiry date passes.</li>
                    </ul>
                    @if ($can('finance')) <h3>Invoicing an accepted quote</h3><p>On the accepted quote choose <strong>Create invoice from this quote</strong>, tick the lines to bill and change the amount for a deposit or milestone. The quote shows what is already invoiced on each line, so one-time work cannot be billed twice.</p> @endif
                </section>
            @endif

            @if ($can('tasks.view'))
                <section id="tasks" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Tasks and follow-ups</h2>
                    <p><strong>Clients &amp; Work → Tasks &amp; Follow-ups</strong> is one list for work to do and things to chase, for clients{{ $can('sales.manage') ? ' and leads' : '' }}.</p>
                    <ul>
                        <li>Start the day with the filters <strong>Due today or overdue</strong> and <strong>Assigned to me</strong>.</li>
                        <li>Change a task's status straight from the list. You can always update tasks assigned to you.</li>
                        @if ($can('tasks.manage')) <li>Use <strong>New Task</strong> here, or on the Tasks tab of a client, to add one with an assignee and due date.</li> @endif
                        @if ($can('sales.manage')) <li>A strip at the top lists lead next actions and quote follow-up dates that are due.</li> @endif
                    </ul>
                </section>
            @endif

            @if ($team && $can('clients.view'))
                <section id="renewals" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Hosting and renewals</h2>
                    <p><strong>Clients &amp; Work → Hosting &amp; Renewals</strong> holds everything that renews.</p>
                    <ul>
                        <li><strong>Overview</strong> — hosting, domains, maintenance and retainers together, soonest first.</li>
                        <li><strong>Paid Server Clients (Hosting)</strong> — hosting you bill for, with filters for overdue, due within 7 days and due within 2 months.</li>
                        <li><strong>Domain, Maintenance, Retainer</strong> — the other renewals.</li>
                    </ul>
                    @if ($can('clients.manage'))
                        <h3>When a renewal is paid</h3>
                        <ol>
                            <li>Find the record and click <strong>Record Renewal</strong>.</li>
                            <li>Check the paid date, amount and the next date, then save.</li>
                            <li>The finished period goes into <strong>History</strong>, the date rolls forward, and new reminders are scheduled from it.</li>
                        </ol>
                    @endif
                    <p><strong>Server Type</strong> is the arrangement (Paid or Self-managed); <strong>Server Chosen</strong> is the provider or plan. Only active <em>Paid</em> records are reminded.</p>
                </section>

                <section id="projects" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Projects and services</h2>
                    <p><strong>Clients &amp; Work → Projects &amp; Services</strong> tracks each client's projects and ongoing services: owner, status, deadline and the next milestone. A project can be linked to the accepted quote it came from. Overdue deadlines are flagged in red.</p>
                </section>
            @endif

            @if ($can('finance'))
                <section id="finance" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Invoices, payments and expenses</h2>
                    <h3>Invoices</h3>
                    <ol>
                        <li><strong>Finance → Invoices → New Invoice.</strong> Pick the client and recipient, add line items, and set a <em>Planned Send Date</em>.</li>
                        <li>It is saved as a <strong>Draft</strong>. On the planned date Super Admins are reminded to review and send it.</li>
                        <li>Download the PDF, send it to the client, then click <strong>Mark as Sent</strong>.</li>
                        <li>If the invoice was issued from another tool, upload that PDF under <em>Copy sent outside the CRM</em>.</li>
                    </ol>
                    <h3>Payments</h3>
                    <ol>
                        <li><strong>Record Payment</strong> from an invoice, a client, or Finance → Payments.</li>
                        <li>Enter the amount, date, payment type and <em>Payment To</em> (the Super Admin who received it).</li>
                        <li>Apply it to one or more invoices, or use <strong>Apply to oldest first</strong>. Anything left stays as an unallocated payment you can apply later.</li>
                    </ol>
                    <p><strong>Pending amount</strong> = issued invoices minus payments applied to them. Drafts and void invoices are not counted. An invoice shows as <em>Overdue</em> once its due date passes.</p>
                    <h3>Expenses</h3>
                    <p><strong>Finance → Expenses</strong> records daily spend with a category, payee and optional receipt, linked to a client or kept as general overhead. The <em>Recurring</em> tab separates subscriptions and regular bills. Totals, a category breakdown and CSV or PDF downloads follow the filters you set.</p>
                </section>
            @endif

            @if ($can('support.manage') || $can('portal-users.manage'))
                <section id="support" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Support portal and tickets</h2>
                    <p>Clients use a separate website section, the support portal, at <a class="link" href="{{ route('portal.home') }}">{{ route('portal.home') }}</a>. Link to it from your website menu or footer.</p>
                    <ul>
                        <li>A client creates a portal account (email and password, or Google), confirms their email, and submits a <strong>Support request</strong>, <strong>New work request</strong> or <strong>Feedback</strong>.</li>
                        <li>They get a ticket number such as <code>SUP-2026-0001</code> and follow replies on the portal.</li>
                    </ul>
                    @if ($can('support.manage'))
                        <h3>Answering tickets</h3>
                        <ol>
                            <li>Open <strong>Support → Support Tickets</strong>. Urgent tickets are listed first; the red number in the menu is the count of new ones.</li>
                            <li>Open a ticket, write a reply and choose the status to set. The client is emailed.</li>
                            <li>Tick <strong>Internal note only</strong> for notes the client must not see.</li>
                            <li>Assign the ticket, change priority, or link it to a client record from the panel on the right.</li>
                        </ol>
                        <p>If a request arrives by phone or WhatsApp, use <strong>New ticket</strong> to log it for that customer so everything stays in one place.</p>
                    @endif
                    @if ($can('portal-users.manage')) <p><strong>Support → Portal Users</strong> is the portal's login list. You can add a portal user with a password, link them to a client, or disable an account.</p> @endif
                </section>
            @endif

            @if ($can('posters.view'))
                <section id="posters" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Social media posters</h2>
                    <p><strong>Clients &amp; Work → Social Media Posters</strong> is the library of finished creatives. Search by title, caption, tag or campaign and filter by platform or client. Click a poster for the full-size preview and <strong>Download original</strong>.{{ $can('posters.manage') ? ' Use Upload Poster to add one with a title, platform, client, caption and tags.' : '' }}</p>
                </section>
            @endif

            <section id="equipment" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Equipment</h2>
                @if ($can('admin'))
                    <p><strong>Clients &amp; Work → Equipment</strong> is the register of laptops, phones and other items. Add an item, choose who it is assigned to, and record its serial number, condition and cost. The buttons along the top filter by person; unassigned items sit under <em>In stock</em>.</p>
                @else
                    <p><strong>My Equipment</strong> lists the items issued to you. If something is wrong or missing, tell a Super Admin.</p>
                @endif
            </section>

            @if ($team)
                <section id="notifications" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Notifications and reminders</h2>
                    <p>The bell at the top right shows recent notifications; <strong>Open Notification Center</strong> groups them by clients, hosting, renewals, invoices and support.</p>
                    <ul>
                        <li><strong>New client added</strong> — to every Super Admin.</li>
                        <li><strong>Hosting payment due</strong> — two months before and again seven days before the payment date.</li>
                        <li><strong>Domain, maintenance and retainer renewals</strong> — on the days-before schedule set in Settings.</li>
                        <li><strong>Invoice ready to send</strong> — on a draft invoice's planned send date.</li>
                        <li><strong>Support tickets</strong> — new tickets and client replies, to Super Admins and staff who answer tickets.</li>
                    </ul>
                    <p>Each reminder is sent once per person, in the app and by email. Changing a date reschedules its reminders automatically.</p>
                </section>
            @endif

            @if ($can('admin'))
                <section id="admin" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Users, roles and settings</h2>
                    <h3>Adding people</h3>
                    <ol>
                        <li><strong>Administration → Users → Add user.</strong> Choose the role and, for Staff, tick what they may do.</li>
                        <li>Type a password to make the account usable straight away, or leave it empty to email an invitation.</li>
                        <li>Share the right sign-in link, shown at the top of the Users screen.</li>
                    </ol>
                    <p>Deactivating a user signs them out and blocks sign-in but keeps their history, including payments they received.</p>
                    <h3>Settings</h3>
                    <ul>
                        <li><strong>General</strong> — currency, time zone, company details for invoices, invoice defaults, renewal reminder days.</li>
                        <li><strong>Branding</strong> — the app name, the two colours that style the whole app, the logo and the favicon.</li>
                        <li><strong>Quote Templates</strong> — reusable sections and price lists for each service.</li>
                        <li><strong>Dropdown values</strong> — work types, industries, sources, referrers, server types and plans, payment methods, expense categories, equipment types. Deactivate a value to hide it from new entries without losing it on old records.</li>
                    </ul>
                    <p><strong>Administration → Activity Log</strong> shows sign-ins and record changes, with who did it and when.</p>
                </section>
            @endif

            <section id="faq" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Common questions</h2>
                <h3>I forgot my password</h3>
                <p>Use <em>Forgot password?</em> on your sign-in page. A Super Admin can also set a new one for you from Users → Edit.</p>
                <h3>A menu item is missing</h3>
                <p>The menu only shows what your role allows. Ask a Super Admin if you need access to something else.</p>
                @if ($can('clients.view')) <h3>A dropdown does not have the value I need</h3><p>Dropdown values are managed by Super Admins under Settings → Dropdown values.</p> @endif
                @if ($can('finance')) <h3>A sent invoice is wrong</h3><p>Sent invoices cannot be edited. Void it and create a new one, so the record of what was sent stays accurate.</p> @endif
                @if ($team) <h3>Emails are not arriving</h3><p>Email and reminders depend on the mail settings and background worker on the server. Ask whoever maintains the installation to check them.</p> @endif
            </section>
        </div>
    </div>
</x-layouts.app>
