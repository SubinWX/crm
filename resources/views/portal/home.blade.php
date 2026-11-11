<x-layouts.portal title="Support">
    <div class="grid items-center gap-10 py-6 md:grid-cols-2">
        <div>
            <p class="text-xs font-semibold tracking-wider text-brand-700 uppercase">Support Portal</p>
            <h1 class="mt-2 text-3xl leading-tight font-semibold text-stone-900">How can we help with your project?</h1>
            <p class="mt-3 text-stone-600">Raise a support request for work we are doing for you, ask for new work, or share feedback. Every request gets a ticket number so you can follow it to the finish.</p>
            <ul class="mt-6 space-y-3 text-sm text-stone-700">
                @foreach (['Support request' => 'Something is broken or needs changing on work we delivered.', 'New work request' => 'A new page, campaign, feature or service you would like quoted.', 'Feedback' => 'Tell us what went well and what we should do better.'] as $title => $text)
                    <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-5 text-brand-600" /><span><strong class="text-stone-900">{{ $title }}.</strong> {{ $text }}</span></li>
                @endforeach
            </ul>
        </div>
        <div class="card p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-900">Sign in to submit a ticket</h2>
            <p class="mt-1 mb-5 text-sm text-stone-500">Create a free portal account in under a minute.</p>
            @include('portal._google')
            <div class="grid gap-2">
                <a href="{{ route('portal.register') }}" class="btn-primary w-full py-2.5">Create an account</a>
                <a href="{{ route('portal.login') }}" class="btn-secondary w-full py-2.5">I already have an account</a>
            </div>
        </div>
    </div>
</x-layouts.portal>
