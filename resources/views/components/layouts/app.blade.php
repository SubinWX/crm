<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ app_name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-head />
    @livewireStyles
</head>
<body class="min-h-screen" x-data="{ nav: false }">
    @php
        $user = auth()->user();
        // Main menus and their sub-items: [route, label, icon, visible, active pattern].
        $nav = [
            ['Clients', 'users', [
                ['clients.index', 'All Clients', 'users', $user->can('clients.view'), ['clients.index', 'clients.show', 'clients.edit']],
                ['clients.create', 'Add Client', 'plus', $user->can('clients.create'), 'clients.create'],
                ['campaigns.index', 'Bulk Messages', 'bell', $user->can('admin'), 'campaigns.*'],
                ['renewals.index', 'Hosting & Renewals', 'server', $user->can('workspace') && $user->can('clients.view'), 'renewals.*'],
            ]],
            ['Projects', 'check', [
                ['projects.index', 'Projects & Services', 'document', $user->can('workspace') && $user->can('clients.view'), 'projects.*'],
                ['tasks.index', 'Tasks & Follow-ups', 'check', $user->can('tasks.view'), 'tasks.*'],
            ]],
            ['Office', 'desktop', [
                ['posters.index', 'Social Media Posters', 'photo', $user->can('posters.view'), 'posters.*'],
                ['equipment.index', $user->isSuperAdmin() ? 'Equipment' : 'My Equipment', 'desktop', true, 'equipment.*'],
                ['documents', 'Documents', 'document', true, 'documents'],
                ['policies', 'Rules & Policies', 'book', true, 'policies'],
                ['meeting-notes', 'Meeting Notes', 'document', true, 'meeting-notes'],
            ]],
            ['Team', 'clock', [
                ['profile', 'My Profile', 'users', true, 'profile'],
                ['timesheet', 'Timesheet', 'clock', $user->can('workspace'), 'timesheet'],
                ['leave', 'Leave', 'calendar', $user->can('workspace'), 'leave'],
                ['attendance', 'Attendance', 'check', $user->can('workspace'), 'attendance'],
            ]],
            ['Sales', 'document', [
                ['leads.index', 'Leads', 'users', $user->can('sales.manage'), 'leads.*'],
                ['quotes.index', 'Quotes & Proposals', 'document', $user->can('sales.manage'), 'quotes.*'],
            ]],
            ['Support', 'bell', [
                ['support.tickets.index', 'Support Tickets', 'bell', $user->can('support.manage'), 'support.tickets.*'],
                ['support.portal-users', 'Portal Users', 'users', $user->can('portal-users.manage'), 'support.portal-users'],
                ['support.settings', 'Page Settings', 'cog', $user->can('support.manage'), 'support.settings'],
            ]],
            ['Careers', 'users', [
                ['careers.manage', 'Overview', 'home', $user->can('careers.manage'), 'careers.manage'],
                ['careers.applications', 'All Applications', 'document', $user->can('careers.manage'), 'careers.applications'],
                ['careers.vacancies', 'Vacancies', 'users', $user->can('careers.manage'), 'careers.vacancies'],
                ['careers.settings', 'Page Settings', 'cog', $user->can('careers.manage'), 'careers.settings'],
            ]],
            ['Email Tools', 'document', [
                ['email-tools.signature', 'Email Signatures', 'document', true, 'email-tools.signature'],
                ['email-tools.saved-signatures', 'Saved Signatures', 'document', true, 'email-tools.saved-signatures'],
                ['email-tools.templates', 'Email Templates', 'document', true, 'email-tools.templates'],
                ['email-tools.saved-templates', 'Saved Email Templates', 'document', true, 'email-tools.saved-templates'],
            ]],
            ['Finance', 'cash', [
                ['invoices.index', 'Invoices', 'document', $user->can('finance'), 'invoices.*'],
                ['payments.index', 'Payments', 'cash', $user->can('finance'), 'payments.*'],
                ['expenses.index', 'Expenses', 'receipt', $user->can('finance'), 'expenses.*'],
                ['gst.summary', 'GST Summary', 'document', $user->can('finance'), 'gst.*'],
            ]],
            ['Administration', 'shield', [
                ['users.index', 'Admins', 'shield', $user->can('admin'), 'users.*'],
                ['staff.index', 'Staff', 'users', $user->can('staff.manage'), 'staff.index'],
                ['activity.index', 'Activity Log', 'clock', $user->can('admin'), 'activity.*'],
                ['settings.index', 'Settings', 'cog', $user->can('admin'), ['settings.index', 'quote-templates.*']],
                ['settings.messaging', 'Email, WhatsApp & SMS', 'bell', $user->can('admin'), 'settings.messaging'],
                ['settings.public-pages', 'Public Pages', 'document', $user->can('admin'), 'settings.public-pages'],
                ['settings.pdf', 'PDF Settings', 'document', $user->can('admin'), 'settings.pdf*'],
            ]],
        ];
        $newApplications = $user->can('careers.manage') ? \App\Models\JobApplication::where('status', 'new')->count() : 0;
        $pendingLeave = $user->can('team.manage') ? \App\Models\LeaveRequest::where('status', 'pending')->count() : 0;
        $unreadChat = \App\Models\ChatMessage::unreadFor($user);
        $openTickets = $user->can('support.manage') ? \App\Models\SupportTicket::where('status', 'open')->count() : 0;
    @endphp

    {{-- Sidebar: fixed on desktop, slide-over drawer on tablets. Each main menu opens to show its sub-menu. --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-900/50 lg:hidden" @click="nav = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col bg-ink-900 transition-transform lg:translate-x-0"
           :class="nav && 'translate-x-0'">
        <a href="{{ route('dashboard') }}" class="flex h-14 shrink-0 items-center gap-2.5 px-4 text-white">
            <x-brand-mark />
            <span class="truncate text-sm font-semibold">{{ app_name() }}</span>
        </a>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3" aria-label="Main">
            <a href="{{ route('dashboard') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('dashboard')]) @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon name="home" /> Dashboard
            </a>
            <a href="{{ route('calendar') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('calendar')]) @if (request()->routeIs('calendar')) aria-current="page" @endif>
                <x-icon name="calendar" /> Calendar
            </a>

            @foreach ($nav as [$group, $groupIcon, $items])
                @php
                    $visible = collect($items)->filter(fn ($i) => $i[3]);
                    $active = $visible->contains(fn ($i) => request()->routeIs($i[4]));
                    $key = 'crm-nav-'.\Illuminate\Support\Str::slug($group);
                @endphp
                @if ($visible->isNotEmpty())
                    {{-- Open state is remembered per browser; the menu holding the current page is always open. --}}
                    <div x-data="{ open: @js($active) || localStorage.getItem('{{ $key }}') === '1' }" x-init="$watch('open', v => localStorage.setItem('{{ $key }}', v ? '1' : '0'))">
                        <button type="button" class="nav-link w-full cursor-pointer {{ $active ? 'text-white' : '' }}" @click="open = !open" :aria-expanded="open" aria-controls="{{ $key }}">
                            <x-icon :name="$groupIcon" />
                            <span class="flex-1 text-left">{{ $group }}</span>
                            @if ($group === 'Support' && $openTickets) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white" x-show="!open">{{ $openTickets }}</span> @endif
                            @if ($group === 'Team' && $pendingLeave) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white" x-show="!open" title="Leave requests to approve">{{ $pendingLeave }}</span> @endif
                            @if ($group === 'Careers' && $newApplications) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white" x-show="!open" title="New applications">{{ $newApplications }}</span> @endif
                            <x-icon name="chevron" class="size-3.5 transition-transform" ::class="open && 'rotate-180'" />
                        </button>
                        <div id="{{ $key }}" x-show="open" x-collapse.duration.150ms @unless ($active) x-cloak @endunless class="mt-0.5 ml-[1.15rem] space-y-0.5 border-l border-white/10 pl-2">
                            @foreach ($visible as [$route, $label, $icon, , $pattern])
                                <a href="{{ route($route) }}" @class(['nav-link py-1.5 text-[13px]', 'nav-link-active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                                    {{ $label }}
                                    @if ($route === 'support.tickets.index' && $openTickets) <span class="ml-auto rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $openTickets }}</span> @endif
                                    @if ($route === 'leave' && $pendingLeave) <span class="ml-auto rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white" title="Leave requests to approve">{{ $pendingLeave }}</span> @endif
                                    @if ($route === 'careers.applications' && $newApplications) <span class="ml-auto rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white" title="New applications">{{ $newApplications }}</span> @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="shrink-0 border-t border-white/10 p-3">
            <a href="{{ route('roadmap') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('roadmap')])><x-icon name="clock" /> Timeline &amp; Change log</a>
            <a href="{{ route('help') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('help')])><x-icon name="book" /> Help &amp; Tutorial</a>
        </div>
    </aside>

    <div class="lg:pl-60">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-stone-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="btn-ghost lg:hidden" @click="nav = true" aria-label="Open navigation"><x-icon name="menu" class="size-5" /></button>
            <p class="truncate text-sm font-semibold text-stone-900">{{ $title ?? '' }}</p>
            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                <livewire:search.box />
                {{-- A timer left running shows here on every page, counting up, so it is never forgotten. --}}
                @php $runningTimer = auth()->user()->can('workspace') ? \App\Models\TimeEntry::running()->with('project')->where('user_id', auth()->id())->latest('started_at')->first() : null; @endphp
                @if ($runningTimer)
                    <a href="{{ route('timesheet') }}" class="flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100" title="Timer running on {{ $runningTimer->project?->name }}. Click to open the tracker."
                       x-data="{ started: {{ $runningTimer->started_at->getTimestamp() * 1000 }}, now: Date.now(), clock() { const s = Math.max(0, Math.floor((this.now - this.started) / 1000)); return [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':') } }" x-init="setInterval(() => now = Date.now(), 1000)">
                        <span class="size-2 rounded-full bg-emerald-600"></span>
                        <span class="hidden max-w-32 truncate sm:inline">{{ $runningTimer->project?->name }}</span>
                        <span class="font-mono tabular-nums" x-text="clock()">{{ \App\Models\TimeEntry::clock($runningTimer->seconds()) }}</span>
                    </a>
                @endif
                <span class="hidden text-xs text-stone-500 md:inline">{{ \Illuminate\Support\Carbon::now(crm_tz())->format('D, d M Y') }}</span>
                {{-- AI assistant: the bot in the top bar. --}}
                <a href="{{ route('assistant') }}" @class(['btn-ghost', 'bg-stone-100 text-brand-700' => request()->routeIs('assistant')]) title="AI Assistant" aria-label="AI Assistant"><x-icon name="bot" class="size-5" /></a>
                <livewire:notifications.bell />
                <livewire:account.menu />
            </div>
        </header>

        <main class="mx-auto max-w-[1400px] px-4 pt-6 pb-24 sm:px-6 sm:pb-6">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm text-brand-800" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>

    {{-- Chat: a round button in the bottom-right corner of every page, with the number of unread messages. Kept up to date by the chat notifier. --}}
    @unless (request()->routeIs('chat'))
        <a href="{{ route('chat') }}" class="fixed right-5 bottom-5 z-40 grid size-14 place-items-center rounded-full bg-brand-600 text-white shadow-lg transition hover:bg-brand-700 focus-visible:ring-4 focus-visible:ring-brand-200 focus-visible:outline-none" title="Chat" aria-label="Chat">
            <x-icon name="chat" class="size-7" />
            <span x-data="{ n: {{ (int) $unreadChat }} }" x-on:chat-unread.window="n = $event.detail.count" x-show="n > 0" x-text="n" x-cloak
                  class="absolute -top-1 -right-1 min-w-5 rounded-full bg-red-600 px-1.5 text-center text-[11px] leading-5 font-bold text-white ring-2 ring-white" title="Unread messages"></span>
        </a>
    @endunless

    {{-- Toasts raised with $this->dispatch('toast', message: '...') --}}
    <div x-data="{ toasts: [] }" class="pointer-events-none fixed right-4 bottom-24 z-50 space-y-2"
         @toast.window="const t = { id: Date.now(), message: $event.detail.message, type: $event.detail.type || 'ok' }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4000)">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-lg"
                 :class="t.type === 'error' ? 'bg-red-700' : 'bg-ink-800'" role="status" x-text="t.message"></div>
        </template>
    </div>
    {{-- New chat messages: a popup in the top-right corner of whichever page is open. Click it to open the conversation. --}}
    <livewire:chat.notifier />
    <div x-data="{ popups: [] }" class="pointer-events-none fixed top-16 right-4 z-50 w-80 max-w-[calc(100vw-2rem)] space-y-2" aria-live="polite"
         @chat-popup.window="const p = { id: Date.now() + Math.random(), ...$event.detail }; popups.push(p); setTimeout(() => popups = popups.filter(x => x.id !== p.id), 8000)">
        <template x-for="p in popups" :key="p.id">
            <div class="card pointer-events-auto flex items-start gap-3 p-3 shadow-xl" role="status" x-transition.opacity>
                <a :href="p.url" class="min-w-0 flex-1">
                    <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase" x-text="'New message · ' + p.room"></p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-stone-900" x-text="p.name"></p>
                    <p class="line-clamp-2 text-sm text-stone-600" x-text="p.text"></p>
                </a>
                <button type="button" class="btn-ghost btn-sm" @click="popups = popups.filter(x => x.id !== p.id)" aria-label="Dismiss"><x-icon name="x" /></button>
            </div>
        </template>
    </div>
    @livewireScripts
</body>
</html>
