<div>
    <x-page-header :title="'Hello, '.str(auth()->user()->name)->before(' ')" :subtitle="today_local()->format('l, d F Y')">
        <a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" /> New Client</a>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-stat label="Clients you added" :value="$total" :href="route('clients.index')" />
        <x-stat label="Added this month" :value="$thisMonth" :hint="today_local()->format('F Y')" />
        <x-stat label="Equipment issued to you" :value="$equipment" :href="route('equipment.index')" />
    </div>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Your recent clients</h2><a href="{{ route('clients.index') }}" class="link text-xs">All your clients</a></div>
        @forelse ($recent as $client)
            <a href="{{ route('clients.show', $client) }}" class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-3 text-sm last:border-b-0 hover:bg-stone-50">
                <span class="flex min-w-0 items-center gap-3">
                    <x-avatar :src="$client->logoUrl()" :name="$client->name" rounded="rounded-md" fit="object-contain" />
                    <span class="min-w-0"><span class="block truncate font-medium text-stone-900">{{ $client->name }}</span><span class="block truncate text-xs text-stone-500">{{ $client->contact_name ?: $client->email }}</span></span>
                </span>
                <span class="text-xs whitespace-nowrap text-stone-500">{{ fmt_date($client->created_at->timezone(crm_tz())) }}</span>
            </a>
        @empty
            <x-empty>You have not added any clients yet. Use “New Client” to add the first one.</x-empty>
        @endforelse
    </section>
</div>
