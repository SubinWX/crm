{{--
    The guided tour: a short walk through the screen for someone signing in for the first time.
    Each step points at one part of the page (spotlight + a small card with Back / Next). It starts by itself on
    the dashboard until the person finishes or closes it, and can be replayed from Help & Tutorial or with ?tour=1.
    Steps whose part of the page is not there for this person (no access, or hidden on a phone) are left out.
--}}
@php
    $user = auth()->user();
    $team = $user->can('workspace');
    $first = str($user->name)->before(' ');

    $steps = [
        ['title' => 'Welcome, '.$first, 'text' => 'This is '.app_name().'. Here is a one-minute tour of where things are. You can close it at any time and replay it later from Help & Tutorial.'],
        ['target' => '[data-tour=menu]', 'title' => 'The menu', 'text' => 'Everything is in this menu, grouped by area. Click a heading to open it. You only see the areas you have been given access to.'],
        ['target' => '[data-tour=pending]', 'title' => 'Start here every day', 'text' => 'This box lists what is waiting for you: tasks, messages, approvals and reminders. Click a line to open it. When it is green, you are all caught up.'],
        ['target' => '[data-tour=search]', 'title' => 'Search everything', 'text' => 'Type a name, phone number or invoice number to find a client, project, task or ticket from any page. Press / to jump here.'],
        ['target' => '[data-tour=assistant]', 'title' => 'AI Assistant', 'text' => 'Drafts emails and proposals, and rewords or summarises text you paste in.'],
        ['target' => '[data-tour=bell]', 'title' => 'Notifications', 'text' => 'The bell shows what has happened that concerns you. A red number means there is something new.'],
        ['target' => '[data-tour=account]', 'title' => 'Your account', 'text' => 'Open this for My Profile, to change your password or picture, and to sign out.'],
        ['target' => '[data-tour=chat]', 'title' => 'Chat', 'text' => 'Message the whole team or one person. You can attach pictures and files. A popup tells you when a new message arrives.'],
        ['target' => '[data-tour=help]', 'title' => 'Help when you need it', 'text' => 'Help & Tutorial explains every screen step by step. Timeline & Change log shows what is new and what is planned.'],
        ['title' => 'You are ready', 'text' => 'Good first steps:', 'links' => array_values(array_filter([
            ['label' => 'Fill in your profile and emergency contact', 'url' => route('profile')],
            $team ? ['label' => 'Check in for today', 'url' => route('attendance')] : null,
            $user->can('tasks.view') ? ['label' => 'See the tasks assigned to you', 'url' => route('tasks.index', ['assignee' => $user->id])] : null,
            $user->can('clients.create') ? ['label' => 'Add your first client', 'url' => route('clients.create')] : null,
            ['label' => 'Read the company Rules & Policies', 'url' => route('policies')],
        ]))],
    ];
    $auto = $user->tour_seen_at === null && request()->routeIs('dashboard');
    $replay = request()->boolean('tour');
@endphp
<div x-data="{
        all: @js($steps), steps: [], i: -1, spot: null, card: { top: 0, left: 0 },
        get step() { return this.steps[this.i] ?? null },
        there(s) {
            if (! s.target) return true;
            const el = document.querySelector(s.target);
            if (! el || el.getClientRects().length === 0) return false;
            // On a phone the menu is slid off the side of the screen: skip the steps about it.
            const r = el.getBoundingClientRect();
            return r.right > 0 && r.left < window.innerWidth;
        },
        start() { this.steps = this.all.filter(s => this.there(s)); this.i = 0; this.place() },
        next() { if (this.i >= this.steps.length - 1) return this.finish(); this.i++; this.place() },
        back() { if (this.i > 0) { this.i--; this.place() } },
        place() {
            const s = this.step; if (! s) return;
            const el = s.target ? document.querySelector(s.target) : null;
            if (el) el.scrollIntoView({ block: 'nearest' });
            this.$nextTick(() => {
                const W = this.$refs.card.offsetWidth || 340, H = this.$refs.card.offsetHeight || 220, vw = window.innerWidth, vh = window.innerHeight;
                if (! el) { this.spot = null; this.card = { top: Math.max(12, (vh - H) / 2), left: Math.max(12, (vw - W) / 2) }; return; }
                const r = el.getBoundingClientRect();
                this.spot = { top: r.top - 6, left: r.left - 6, width: r.width + 12, height: r.height + 12 };
                let top, left;
                if (r.height > vh * 0.5) {
                    // A tall part of the page (the menu): the card goes beside it.
                    left = r.right + 18; if (left + W > vw - 12) left = Math.max(12, r.left - W - 18);
                    top = Math.max(12, Math.min(vh - H - 12, r.top + 60));
                } else {
                    top = r.bottom + 16; if (top + H > vh - 12) top = Math.max(12, r.top - H - 16);
                    left = Math.max(12, Math.min(vw - W - 12, r.left + r.width / 2 - W / 2));
                }
                this.card = { top, left };
            });
        },
        finish() {
            this.i = -1; this.spot = null;
            fetch(@js(route('tour.seen')), { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' } }).catch(() => {});
        },
     }"
     x-init="if (@js($auto || $replay)) setTimeout(() => start(), 600)"
     @start-tour.window="start()" @resize.window="if (step) place()" @keydown.escape.window="if (step) finish()"
     @keydown.right.window="if (step) next()" @keydown.left.window="if (step) back()">
    <template x-if="step">
        <div class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Guided tour">
            {{-- Dim the page; the part being explained stays bright. --}}
            <div x-show="! spot" class="absolute inset-0 bg-ink-900/60"></div>
            <div x-show="spot" class="pointer-events-none absolute rounded-lg ring-2 ring-white"
                 :style="spot ? `top:${spot.top}px;left:${spot.left}px;width:${spot.width}px;height:${spot.height}px;box-shadow:0 0 0 9999px rgba(15,23,42,.6)` : ''"></div>

            <div x-ref="card" class="card absolute w-[21rem] max-w-[calc(100vw-1.5rem)] p-4 shadow-2xl" :style="`top:${card.top}px;left:${card.left}px`">
                <div class="flex items-start gap-3">
                    <p class="flex-1 text-xs font-semibold tracking-wide text-brand-700 uppercase" x-text="`Step ${i + 1} of ${steps.length}`"></p>
                    <button type="button" class="-mt-1 -mr-1 btn-ghost btn-sm" @click="finish()" aria-label="Close the tour"><x-icon name="x" /></button>
                </div>
                <h2 class="mt-1 text-base font-semibold text-stone-900" x-text="step.title"></h2>
                <p class="mt-1 text-sm leading-relaxed text-stone-600" x-text="step.text"></p>
                <template x-if="step.links">
                    <ul class="mt-2 space-y-1">
                        <template x-for="link in step.links" :key="link.url">
                            <li><a :href="link.url" @click="finish()" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"><span aria-hidden="true">→</span> <span x-text="link.label"></span></a></li>
                        </template>
                    </ul>
                </template>
                <div class="mt-4 flex items-center gap-2">
                    <button type="button" class="link text-xs" @click="finish()" x-show="i < steps.length - 1">Skip the tour</button>
                    <span class="flex-1"></span>
                    <button type="button" class="btn-secondary btn-sm" @click="back()" x-show="i > 0">Back</button>
                    <button type="button" class="btn-primary btn-sm" @click="next()" x-text="i >= steps.length - 1 ? 'Finish' : 'Next'" x-init="$nextTick(() => $el.focus())"></button>
                </div>
            </div>
        </div>
    </template>
</div>
