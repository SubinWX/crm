<x-layouts.portal title="Support">
    {{-- Heading, description and background image are set in Support → Page Settings. --}}
    <x-slot:banner>
        <x-public.banner page="support" width="max-w-5xl" />
    </x-slot:banner>

    <div class="grid items-start gap-5 md:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="card p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-900">What you can send us</h2>
            <ul class="mt-4 space-y-4 text-sm text-stone-700">
                @foreach (['Support request' => 'Something is broken or needs changing on work we delivered.', 'New work request' => 'A new page, campaign, feature or service you would like quoted.', 'Feedback' => 'Tell us what went well and what we should do better.'] as $title => $text)
                    <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-5 shrink-0 text-brand-600" /><span><strong class="text-stone-900">{{ $title }}.</strong> {{ $text }}</span></li>
                @endforeach
            </ul>
            <p class="mt-5 border-t border-stone-100 pt-4 text-sm text-stone-500">Every request gets a ticket number, and you can follow each reply here until it is finished.</p>
        </div>
        <div class="card p-6 shadow-sm">
            @auth('portal')
                <h2 class="text-lg font-semibold text-stone-900">Welcome back</h2>
                <p class="mt-1 mb-5 text-sm text-stone-500">Open a new ticket or check on one you already sent.</p>
                <div class="grid gap-2">
                    <a href="{{ route('portal.tickets.create') }}" class="btn-primary w-full py-2.5">New ticket</a>
                    <a href="{{ route('portal.tickets.index') }}" class="btn-secondary w-full py-2.5">My tickets</a>
                </div>
            @else
                <h2 class="text-lg font-semibold text-stone-900">Sign in to submit a ticket</h2>
                <p class="mt-1 mb-5 text-sm text-stone-500">Create a free portal account in under a minute.</p>
                @include('portal._google')
                <div class="grid gap-2">
                    <a href="{{ route('portal.register') }}" class="btn-primary w-full py-2.5">Create an account</a>
                    <a href="{{ route('portal.login') }}" class="btn-secondary w-full py-2.5">I already have an account</a>
                </div>
            @endauth
        </div>
    </div>
</x-layouts.portal>
