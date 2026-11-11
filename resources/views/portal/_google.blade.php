@if (\App\Http\Controllers\Portal\GoogleController::enabled())
    <a href="{{ route('portal.google') }}" class="btn-secondary w-full py-2.5">
        <svg viewBox="0 0 24 24" class="size-4" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.4a5.5 5.5 0 01-2.4 3.6v3h3.9c2.300-2.1 3.600-5.200 3.600-8.700z"/><path fill="#34A853" d="M12 24c3.200 0 6-1.100 7.900-2.900l-3.900-3c-1.100.7-2.400 1.100-4 1.100-3.100 0-5.700-2.100-6.600-4.900H1.400v3.100A12 12 0 0012 24z"/><path fill="#FBBC05" d="M5.400 14.300a7.200 7.200 0 010-4.600V6.600H1.400a12 12 0 000 10.800l4-3.100z"/><path fill="#EA4335" d="M12 4.800c1.800 0 3.300.6 4.600 1.800l3.400-3.400C17.900 1.200 15.200 0 12 0A12 12 0 001.400 6.600l4 3.100c.900-2.800 3.500-4.900 6.600-4.900z"/></svg>
        Continue with Google
    </a>
    <div class="my-5 flex items-center gap-3 text-xs text-stone-400"><span class="h-px flex-1 bg-stone-200"></span>or use your email<span class="h-px flex-1 bg-stone-200"></span></div>
@endif
