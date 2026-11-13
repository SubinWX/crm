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
        ['gst', 'GST invoices and summary', $can('finance')],
        ['support', 'Support portal and tickets', $can('support.manage') || $can('portal-users.manage')],
        ['posters', 'Social media posters', $can('posters.view')],
        ['profile', 'My Profile', true],
        ['office', 'Documents, policies, meeting notes', true],
        ['chat', 'Chat, AI Assistant, Timeline', true],
        ['equipment', 'Equipment', true],
        ['notifications', 'Notifications and reminders', $team],
        ['careers', 'Careers and job applications', $can('careers.manage')],
        ['team', 'Timesheets, leave and attendance', $can('workspace')],
        ['email-tools', 'Email signatures and templates', true],
        ['security', 'Two-step login', true],
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
                    <li><strong>Super Admins and Admins:</strong> <a class="link" href="{{ route('login') }}">{{ route('login') }}</a></li>
                    <li><strong>Staff:</strong> <a class="link" href="{{ route('staff.login') }}">{{ route('staff.login') }}</a></li>
                    <li><strong>Clients (support portal):</strong> <a class="link" href="{{ route('portal.home') }}">{{ route('portal.home') }}</a></li>
                </ul>
                <h3>Who can do what</h3>
                <ul>
                    <li><strong>Super Admin</strong> — everything, including finance, users and settings.</li>
                    <li><strong>Staff</strong> — the areas a Super Admin has ticked for them (clients, tasks, posters, sales, support, careers, finance).</li>
                    <li><strong>Admin</strong> — adds clients and sees and edits only the clients they added, plus any extra area ticked for them (sales, support, careers, finance).</li>
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
                    <p>Open <strong>Clients → All Clients</strong>. Search by name, contact, email or phone, and filter by status, industry, work type, source and account manager.</p>
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
                    <p><strong>Projects → Tasks &amp; Follow-ups</strong> is one list for work to do and things to chase, for clients{{ $can('sales.manage') ? ' and leads' : '' }}.</p>
                    <ul>
                        <li>Start the day with the filters <strong>Due today or overdue</strong> and <strong>Assigned to me</strong>.</li>
                        <li>Change a task's status straight from the list. You can always update tasks assigned to you.</li>
                        @if ($can('tasks.manage')) <li>Use <strong>New Task</strong> here, or on the Tasks tab of a client, to add one with a due date. Tick everyone who is working on it, and choose the project it belongs to.</li> @endif
                        <li>A task can sit under a <strong>project</strong>. Open the project to see and add its tasks, or filter this list by project.</li>
                        @if ($can('sales.manage')) <li>A strip at the top lists lead next actions and quote follow-up dates that are due.</li> @endif
                    </ul>
                </section>
            @endif

            @if ($team && $can('clients.view'))
                <section id="renewals" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Hosting and renewals</h2>
                    <p><strong>Clients → Hosting &amp; Renewals</strong> holds everything that renews.</p>
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
                    <p><strong>Projects → Projects &amp; Services</strong> tracks each client's projects and ongoing services: the project owner, the people working on it, its tasks, status, deadline and the next milestone. Open a project to add tasks under it; each task can have several people. A project can be linked to the accepted quote it came from. Overdue deadlines are flagged in red.</p>
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
                    <p><strong>Finance → Expenses</strong> records daily spend with a category, payee and optional receipt, linked to a client, to an option such as Office, Self or Other, or kept as general overhead. The options are edited in Settings → Dropdown values. The <em>Recurring</em> tab separates subscriptions and regular bills. Totals, a category breakdown and CSV or PDF downloads follow the filters you set.</p>
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
                    @if ($can('support.manage')) <p><strong>Support → Page Settings</strong> sets the support portal’s banner (photo, heading, description) and the call-to-action band above its footer. The logo, header button, footer and social links come from <strong>Administration → Public Pages</strong> and are the same on the careers pages.</p> @endif
                    @if ($can('portal-users.manage')) <p><strong>Support → Portal Users</strong> is the portal's login list. You can add a portal user with a password, link them to a client, or disable an account.</p> @endif
                </section>
            @endif

            @if ($can('careers.manage'))
                <section id="careers" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Careers and job applications</h2>
                    <p>Job seekers use a public page at <a class="link" href="{{ route('careers.index') }}">{{ route('careers.index') }}</a>. It needs no sign-in. Link to it from your website menu or footer.</p>
                    <h3>Posting a vacancy</h3>
                    <ol>
                        <li>Open <strong>Careers → Vacancies</strong> in the menu and choose <strong>Add Vacancy</strong>.</li>
                        <li>Fill in the title, description and requirements. The description and requirements boxes have a toolbar for bold text, bullet lists and links. Set the status to <strong>Open</strong> to publish it, or <strong>Draft</strong> to keep it hidden while you write.</li>
                        <li>Use <strong>Copy link</strong> on the Vacancies page to share that job's own page. A closing date stops applications automatically.</li>
                    </ol>
                    <h3>Handling applications</h3>
                    <ul>
                        <li>Each application arrives with the person's details and resume. You get a notification and an email, and the applicant gets a confirmation email.</li>
                        <li><strong>Careers → Overview</strong> shows only the applications from the last 3 days, with a chart of how many arrived per day or month.</li>
                        <li>On <strong>Careers → All Applications</strong>, filter by vacancy, status or the date applied, and sort by newest, oldest, name, rating, experience or interview date.</li>
                        <li>Open an application to download the resume, set a status (Shortlisted, Interview, Offered, Hired, Rejected), give a rating, add private notes and set an interview time. Interviews and closing dates also show on the <strong>Calendar</strong>.</li>
                        <li><strong>Export CSV</strong> downloads the applications matching your filters.</li>
                    </ul>
                    <h3>Changing how the careers page looks</h3>
                    <p><strong>Careers → Page Settings</strong> sets this page’s banner (background photo, heading, description, height, how much the photo is darkened) and the call-to-action band above the footer (heading, text and one button). The logo, the header link and button, the footer and the social media links are shared with the support portal and are set by a Super Admin in <strong>Administration → Public Pages</strong>.</p>
                    <p>The "Send your resume" button on the careers page collects general applications when no listed role fits.</p>
                </section>
            @endif

            @if ($can('finance'))
                <section id="gst" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>GST invoices and the monthly summary</h2>
                    <ol>
                        <li>A Super Admin chooses your company’s state and enters its GSTIN in <strong>Settings → General</strong>. This switches invoices to GST.</li>
                        <li>On each client, fill in <strong>GSTIN</strong> (if they have one) and <strong>State</strong>.</li>
                        <li>On a new invoice, check the <strong>Place of supply</strong> and the <strong>HSN / SAC</strong> code on each line. A client in your state is charged CGST + SGST; a client in another state is charged IGST. The PDF becomes a “Tax Invoice”.</li>
                        <li>After the month ends, open <strong>Finance → GST Summary</strong>, pick the month and download the <strong>Invoice register</strong> and <strong>HSN summary</strong> for your accountant.</li>
                    </ol>
                    <p>Only invoices marked as sent are counted. Drafts and voided invoices are left out.</p>
                </section>
            @endif

            @if ($can('workspace'))
                <section id="team" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Timesheets, leave and attendance</h2>
                    <h3>Logging your time</h3>
                    <ol>
                        <li>Open <strong>Team → Timesheet</strong>. On the <strong>Tracker</strong> tab, type what you are working on, choose the project and press <strong>Start</strong>.</li>
                        <li>Press <strong>Stop</strong> when you finish. The time is saved to that project with its start and end. While a timer runs, a green clock shows at the top of every page.</li>
                        <li>Forgot to start it? Switch to <strong>Manual</strong> and add the hours in one line (1.5 means 1 hour 30 minutes).</li>
                        <li>The list below shows each day with its total. The ▶ button starts the timer again for the same work. You can change entries for the last 14 days.</li>
                    </ol>
                    <p>The <strong>Overview</strong> tab shows the week by day and by project. <strong>Projects → Projects &amp; Services</strong> shows the time tracked by each person, and who is working right now.</p>
                    <p>Each project page (<strong>Projects → Projects &amp; Services</strong>, then open a project) shows the hours spent against the estimate, and who spent them.</p>
                    <h3>Applying for leave</h3>
                    <ol>
                        <li>Open <strong>Team → Leave</strong> and press <strong>Apply for leave</strong>, or use <strong>Apply for leave</strong> on the Calendar (pick a day first to start on that day).</li>
                        <li>The boxes at the top show what you have left of each type: CL (casual), EL (earned), RH (restricted holiday), ML (medical) and LOP (loss of pay, no limit). You cannot apply for more than is left; use LOP for the rest.</li>
                        <li>For one date you can take a full day, a half day or a quarter day. A quarter day is always loss of pay.</li>
                        <li>Your leave shows on your Calendar, marked “waiting” until it is approved.</li>
                        <li>Pick the type, the first and last day (or half a day), and give a reason. Weekly offs and company holidays in between are not counted.</li>
                        <li>You are told by notification and email when it is approved or rejected. You can cancel a request that is still waiting, or approved leave that has not started.</li>
                    </ol>
                    <h3>Attendance</h3>
                    <p>Open <strong>Team → Attendance</strong> and press <strong>Check in</strong> when you start and <strong>Check out</strong> when you finish. The page shows your month: days present, on leave, absent, and any working day that was not marked.</p>
                    @if ($can('team.manage'))
                        <h3>For managers</h3>
                        <ul>
                            <li><strong>Leave → To approve</strong> lists requests waiting for you. Approve, or reject with a short reason. <strong>Everyone’s leave</strong> shows the whole team for the year.</li>
                            <li><strong>Attendance → Team sheet</strong> is the monthly grid. Click a day to mark someone present, absent, half day or working from home. Click a date at the top to mark everyone unmarked on that day as present. Export it as a spreadsheet.</li>
                            <li><strong>Attendance → Holidays &amp; settings</strong> holds the company holidays and the weekly off days.</li>
                            <li><strong>Leave → Leave types</strong> sets each type's days per year, whether it is given all at once or half-yearly, and a higher figure after years of service (for example CL 12, then 18 after 2 years).</li>
                            <li><strong>Leave → Balances</strong> shows what everyone has left. <strong>Edit</strong> sets a person's joining date, a custom yearly figure, or adds days such as earned leave.</li>
                            <li>In <strong>Apply for leave</strong>, choose a person under “Leave for” to add leave for them; it is approved straight away.</li>
                            <li><strong>Timesheet</strong> lets you view one person or everyone, correct any entry and export the week.</li>
                        </ul>
                    @endif
                </section>
            @endif

            <section id="email-tools" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Email signatures and templates</h2>
                <h3>Your email signature</h3>
                <ol>
                    <li>Open <strong>Email Tools → Email Signatures</strong>. Your name, email and the company details are filled in for you.</li>
                    <li>Pick one of the four layouts (Showcase adds a large photo, a starred highlight line and a banner image), add your designation, photo, colours and social links. The preview updates as you type.</li>
                    <li>Press <strong>Copy signature</strong> and paste it into the signature box of Gmail, Outlook or your phone’s mail app. Name it and press <strong>Save signature</strong>. Everything you save is listed under <strong>Email Tools → Saved Signatures</strong>, where you can open, preview, copy, duplicate or delete each one.</li>
                </ol>
                <h3>Email templates</h3>
                <p><strong>Email Tools → Email Templates</strong> builds a complete email with a header and footer: choose a layout, write the heading and message, and add a button or a banner image if you need one. The footer takes your company name, address, phone and email. <strong>Copy email</strong> pastes into a new message; <strong>Copy HTML code</strong> is for email services such as Mailchimp or Brevo. Name a template and save it to reuse it. Saved templates are listed under <strong>Email Tools → Saved Email Templates</strong> and are shared with the team.</p>
                <p>Both are built the way email programs need (tables and simple styles) and adjust to phone screens. Photos and logos must be at a public web address to show for the people you email.</p>
            </section>

            <section id="security" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Two-step login</h2>
                <p>With two-step login, signing in asks for a 6-digit code from an app on your phone after your password, so a stolen password is not enough.</p>
                <ol>
                    <li>Install Google Authenticator, Microsoft Authenticator or Authy on your phone.</li>
                    <li>Open the menu at the top right and choose <strong>Two-step login</strong>, then <strong>Set up</strong>.</li>
                    <li>Scan the QR code with the app and type the code it shows.</li>
                    <li>Save the eight recovery codes somewhere safe. Each one signs you in once if you lose your phone.</li>
                </ol>
                @if ($can('admin')) <p>On the <strong>Users</strong> page you can make two-step login required for every Super Admin, and use <strong>Reset 2-step</strong> for someone who has lost their phone and their recovery codes.</p> @endif
            </section>

            @if ($can('posters.view'))
                <section id="posters" class="card p-5 text-sm leading-relaxed text-stone-700">
                    <h2>Social media posters</h2>
                    <p><strong>Office → Social Media Posters</strong> is the library of finished creatives. Search by title, caption, tag or campaign and filter by platform or client. Click a poster for the full-size preview and <strong>Download original</strong>.{{ $can('posters.manage') ? ' Use Upload Poster to add one with a title, platform, client, caption and tags.' : '' }}</p>
                </section>
            @endif

            <section id="profile" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>My Profile</h2>
                <p><strong>Team → My Profile</strong> (also in the menu under your name, top right) is your own page. On the left are your picture and the leave you have left this year. <strong>Details</strong> holds your phone numbers, personal email, date of birth, address, blood group and an emergency contact; press <em>Edit</em> to fill them in. <strong>Allocated Devices</strong> lists the items issued to you.</p>
                @if ($can('staff.manage') || $can('team.manage'))
                    <p><strong>Someone else’s profile:</strong> press <em>Profile</em> next to a person in <strong>Administration → Staff</strong>, or their name in <strong>Leave → Balances</strong>. There you can correct their details and set their joining date.</p>
                @endif
            </section>

            <section id="office" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Documents, Rules &amp; Policies, Meeting Notes</h2>
                <ul>
                    <li><strong>Office → Documents</strong> — upload your own papers (ID proof, certificates, letters). Only you, your managers and Super Admins can open them. Filter by category or date, search, and click a column heading to sort.@if ($can('admin')) As a Super Admin you can tick <em>Company document</em> so that everyone can open a file, and you can upload a document for another person with <em>Belongs to</em>.@endif</li>
                    <li><strong>Office → Rules &amp; Policies</strong> — the company’s rules, the same for everyone.@if ($can('admin')) As a Super Admin you add, edit and delete them there.@endif</li>
                    <li><strong>Office → Meeting Notes</strong> — notes of reviews and one-to-one meetings held with you.@if ($can('staff.manage') || $can('team.manage')) You can add a note about a person; untick “can read this note” to keep it for managers only.@endif</li>
                </ul>
            </section>

            <section id="chat" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Chat, AI Assistant and the Timeline</h2>
                <ul>
                    <li><strong>Chat</strong> — the <em>Team room</em> is seen by everyone; pick a person for a private conversation. Attach a picture or file with the picture button, by dropping it on the message box, or by pasting a copied image. A popup in the top-right corner tells you about a new message on whichever page you are, and a red number in the menu shows unread messages.</li>
                    <li><strong>AI Assistant</strong> — drafts emails and proposals, summarises or rewords text you paste in, and answers everyday questions. It cannot see the CRM’s records, and your conversation is visible only to you. What you type is sent to Anthropic, so do not paste passwords.@if ($can('admin')) It is off until you save an Anthropic API key with the <em>Setup</em> button on that page; Anthropic bills that account for usage.@endif</li>
                    <li><strong>Timeline &amp; Change log</strong> (bottom of the menu) — what has been added to the CRM, what is in progress and what is planned.@if ($can('admin')) As a Super Admin you add items and move them from Planned to In progress to Done.@endif</li>
                </ul>
            </section>

            <section id="equipment" class="card p-5 text-sm leading-relaxed text-stone-700">
                <h2>Equipment</h2>
                @if ($can('admin'))
                    <p><strong>Office → Equipment</strong> is the register of laptops, phones and other items. Add an item, choose who it is assigned to, and record its serial number, condition and cost. The buttons along the top filter by person; unassigned items sit under <em>In stock</em>.</p>
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
                        <li><strong>Administration → Users → Add user.</strong> Choose the role, then tick under <strong>Access</strong> what this person may use. Super Admins always have everything.</li>
                        <li>Type a password to make the account usable straight away, or leave it empty to email an invitation.</li>
                        <li>Share the right sign-in link, shown at the top of the Users screen.</li>
                    </ol>
                    <p>Deactivating a user signs them out and blocks sign-in but keeps their history, including payments they received.</p>
                    <h3>How PDFs look</h3>
                    <p><strong>Administration → PDF Settings</strong> sets the logo, header, footer, page border, table style and accent colour used on every PDF: invoices, quotes and reports. Use <strong>View sample PDF</strong> to check the result before sending anything to a client.</p>
                    <h3>Email and WhatsApp</h3>
                    <p><strong>WhatsApp button.</strong> Open invoices, renewals and hosting records each have a <strong>WhatsApp</strong> button. It opens WhatsApp with the reminder already typed for that client, and you press send. It goes from whichever WhatsApp account is signed in on your computer or phone, and works without any API. If the client has no mobile number saved, WhatsApp asks you to choose the contact. Once the WhatsApp API is connected, invoices also get <strong>Send WhatsApp reminder</strong>, which sends it for you, and reminders go out automatically.</p>
                    <p><strong>Administration → Email &amp; WhatsApp</strong> connects the email mailbox you bought (so invitations, reminders and replies are really sent) and your WhatsApp Business API (so clients get renewal and payment reminders on WhatsApp). Each has a <strong>Send test</strong> button, and every WhatsApp message is listed at the bottom with whether it went through.</p>
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
