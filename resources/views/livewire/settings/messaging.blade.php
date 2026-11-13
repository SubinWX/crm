<div>
    <x-page-header title="Email, WhatsApp & SMS" subtitle="Connect the email server, WhatsApp service and SMS service you have bought, so messages reach your team and clients.">
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Settings</a>
    </x-page-header>

    <div class="max-w-4xl space-y-5">
        {{-- ------------------------------------------------------------ Email --}}
        <form wire:submit="saveMail" class="card">
            <div class="card-header">
                <h2 class="card-title">Email server (SMTP)</h2>
                <span @class(['badge-green' => \App\Support\MailSettings::enabled(), 'badge-amber' => ! \App\Support\MailSettings::enabled() && config('mail.default') === 'log', 'badge-gray' => ! \App\Support\MailSettings::enabled() && config('mail.default') !== 'log'])>{{ \App\Support\MailSettings::enabled() ? 'Connected' : (config('mail.default') === 'log' ? 'Not sending' : 'Server default') }}</span>
            </div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-600 md:col-span-2">{{ $mailSummary }} Used for invitations, password resets, reminders, support ticket replies and job application emails. Your email provider (Google Workspace, Zoho, Hostinger, Microsoft 365 and so on) gives you these details.</p>
                <x-field label="Use this email server" name="mail.mail_enabled" class="md:col-span-2">
                    <select class="input max-w-xs" wire:model.live="mail.mail_enabled">
                        <option value="0">Off</option>
                        <option value="1">On: send all emails through it</option>
                    </select>
                </x-field>
                <x-field label="Server (host)" name="mail.mail_host" :required="$mail['mail_enabled'] === '1'"><input class="input" type="text" wire:model="mail.mail_host" placeholder="smtp.yourprovider.com" autocomplete="off"></x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Security" name="mail.mail_encryption">
                        <select class="input" wire:model="mail.mail_encryption" x-on:change="$wire.set('mail.mail_port', { tls: '587', ssl: '465', none: '25' }[$event.target.value], false); $refs.port.value = { tls: '587', ssl: '465', none: '25' }[$event.target.value]">
                            @foreach (\App\Support\MailSettings::ENCRYPTIONS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Port" name="mail.mail_port"><input class="input" type="number" min="1" max="65535" wire:model="mail.mail_port" x-ref="port"></x-field>
                </div>
                <x-field label="Username" name="mail.mail_username" hint="Usually the full email address of the mailbox."><input class="input" type="text" wire:model="mail.mail_username" autocomplete="off"></x-field>
                <x-field label="Password" name="mailPassword" :hint="$hasMailPassword ? 'A password is saved. Leave empty to keep it.' : 'Stored encrypted. For Gmail or Microsoft 365, use an app password.'">
                    <div class="flex gap-2">
                        <input class="input" type="password" wire:model="mailPassword" autocomplete="new-password" placeholder="{{ $hasMailPassword ? '•••••••• (saved)' : '' }}">
                        @if ($hasMailPassword) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeMailPassword" wire:confirm="Remove the saved email password?">Remove</button> @endif
                    </div>
                </x-field>
                <x-field label="Send emails from" name="mail.mail_from_address" :required="$mail['mail_enabled'] === '1'" hint="Must be an address this mailbox is allowed to send as."><input class="input" type="email" wire:model="mail.mail_from_address" placeholder="hello@yourcompany.com"></x-field>
                <x-field label="Sender name" name="mail.mail_from_name" hint="Empty = {{ app_name() }}."><input class="input" type="text" wire:model="mail.mail_from_name" maxlength="100"></x-field>
                <x-field label="Replies go to" name="mail.mail_reply_to" hint="Optional. Empty = the “from” address."><input class="input" type="email" wire:model="mail.mail_reply_to"></x-field>
                <div class="flex items-end justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveMail">Save email settings</button></div>
            </div>
            <div class="border-t border-stone-200 p-4 sm:p-5">
                <p class="label">Send a test email</p>
                <div class="flex flex-wrap items-start gap-2">
                    <div class="w-full max-w-xs"><input class="input" type="email" wire:model="testEmail" aria-label="Send the test email to">@error('testEmail') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <button type="button" class="btn-secondary" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail"><span wire:loading.remove wire:target="sendTestEmail">Send test</span><span wire:loading wire:target="sendTestEmail">Sending…</span></button>
                </div>
                <p class="mt-1 text-xs text-stone-500">Save your changes first. The test uses the saved settings and tells you straight away if the server refuses.</p>
                @if ($mailResult) <p @class(['mt-2 rounded-md p-3 text-sm', 'bg-emerald-50 text-emerald-800' => $mailResult['ok'], 'bg-red-50 text-red-800' => ! $mailResult['ok']]) role="status">{{ $mailResult['message'] }}</p> @endif
            </div>
        </form>

        {{-- --------------------------------------------------------- WhatsApp --}}
        <form wire:submit="saveWhatsApp" class="card">
            <div class="card-header">
                <h2 class="card-title">WhatsApp reminders to clients</h2>
                <span @class(['badge-green' => $waLive, 'badge-amber' => ! $waLive && $wa['whatsapp_enabled'] === '1', 'badge-gray' => ! $waLive && $wa['whatsapp_enabled'] !== '1'])>{{ $waLive ? 'Connected' : ($wa['whatsapp_enabled'] === '1' ? 'Needs: '.implode(', ', $waMissing) : 'Off') }}</span>
            </div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-600 md:col-span-2">Sends renewal and payment reminders to the client’s primary phone number on WhatsApp. You need a WhatsApp Business API account, either directly from Meta or from a provider that resells it. A normal WhatsApp or WhatsApp Business app number cannot send automatic messages.</p>
                <x-field label="WhatsApp reminders" name="wa.whatsapp_enabled">
                    <select class="input" wire:model.live="wa.whatsapp_enabled"><option value="0">Off</option><option value="1">On</option></select>
                </x-field>
                <x-field label="Service" name="wa.whatsapp_provider">
                    <select class="input" wire:model.live="wa.whatsapp_provider">
                        @foreach (\App\Support\WhatsApp::PROVIDERS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Sending WhatsApp number" name="wa.whatsapp_number" hint="The business number messages come from. For your reference; shown to your team."><input class="input" type="tel" wire:model="wa.whatsapp_number" placeholder="+91 98765 43210"></x-field>
                <x-field label="Default country code" name="wa.whatsapp_country_code" hint="Added to client numbers saved without one."><input class="input max-w-32" type="text" wire:model="wa.whatsapp_country_code" placeholder="91"></x-field>

                @if ($wa['whatsapp_provider'] === 'meta')
                    <x-field label="Phone number ID" name="wa.whatsapp_phone_number_id" :required="$wa['whatsapp_enabled'] === '1'" hint="From WhatsApp Manager → API Setup. A long number, not the phone number."><input class="input" type="text" wire:model="wa.whatsapp_phone_number_id" autocomplete="off"></x-field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-field label="API version" name="wa.whatsapp_api_version"><input class="input" type="text" wire:model="wa.whatsapp_api_version" placeholder="v21.0"></x-field>
                        <x-field label="Template language" name="wa.whatsapp_template_language"><input class="input" type="text" wire:model="wa.whatsapp_template_language" placeholder="en"></x-field>
                    </div>
                @else
                    <x-field label="API address" name="wa.whatsapp_custom_url" :required="$wa['whatsapp_enabled'] === '1'" hint="The “send message” address from your provider’s API documentation." class="md:col-span-2"><input class="input" type="url" wire:model="wa.whatsapp_custom_url" placeholder="https://api.yourprovider.com/v1/messages" autocomplete="off"></x-field>
                    <x-field label="Token header name" name="wa.whatsapp_custom_auth_header" hint="Usually Authorization. Some providers use X-API-Key."><input class="input" type="text" wire:model="wa.whatsapp_custom_auth_header"></x-field>
                    <x-field label="Request body (JSON)" name="wa.whatsapp_custom_body" hint="{phone} and {message} are filled in for each message." class="md:col-span-2"><textarea class="input font-mono text-xs" rows="3" wire:model="wa.whatsapp_custom_body" spellcheck="false"></textarea></x-field>
                @endif

                <x-field label="{{ $wa['whatsapp_provider'] === 'meta' ? 'Access token' : 'API token / key' }}" name="waToken" class="md:col-span-2"
                         :hint="$hasWaToken ? 'A token is saved. Leave empty to keep it.' : ($wa['whatsapp_provider'] === 'meta' ? 'Use a permanent (System User) token; the temporary one expires in 24 hours. Stored encrypted.' : 'Exactly as the header should carry it, e.g. “Bearer abc123”. Stored encrypted.')">
                    <div class="flex gap-2">
                        <input class="input" type="password" wire:model="waToken" autocomplete="new-password" placeholder="{{ $hasWaToken ? '•••••••• (saved)' : '' }}">
                        @if ($hasWaToken) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeWhatsAppToken" wire:confirm="Remove the saved WhatsApp token? Reminders stop until a new one is added.">Remove</button> @endif
                    </div>
                </x-field>
            </div>

            <div class="border-t border-stone-200 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-stone-900">What to send</h3>
                <p class="mt-0.5 text-xs text-stone-500">Words in curly brackets are replaced for each client: <code>{client}</code> <code>{item}</code> <code>{date}</code> <code>{amount}</code> <code>{company}</code>.
                    @if ($wa['whatsapp_provider'] === 'meta') Meta only delivers reminders that use an <strong>approved template</strong>. Create each template in WhatsApp Manager with four variables in this order: client name, item, date, amount. Then enter its name here. @endif</p>
                <div class="mt-3 space-y-4">
                    @foreach (\App\Support\WhatsApp::KINDS as $kind => [$label])
                        <div class="rounded-md border border-stone-200 p-3" wire:key="kind-{{ $kind }}">
                            <label class="flex items-center gap-2 text-sm font-medium text-stone-900">
                                <input type="checkbox" class="rounded border-stone-300" @checked($wa["whatsapp_send_{$kind}"] === '1') x-on:change="$wire.set('wa.whatsapp_send_{{ $kind }}', $event.target.checked ? '1' : '0', false)"> {{ $label }}
                            </label>
                            <p class="mt-0.5 ml-6 text-xs text-stone-500">{{ ['hosting' => '7 days before the hosting payment date.', 'renewal' => 'On the same days your team is reminded (set per renewal type in Settings → General).', 'invoice' => 'For sent invoices that still have a balance, on the days set below.'][$kind] }}</p>
                            <div class="mt-2 grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem]">
                                <x-field label="Message" name="wa.whatsapp_text_{{ $kind }}"><textarea class="input" rows="2" wire:model="wa.whatsapp_text_{{ $kind }}" maxlength="900"></textarea></x-field>
                                @if ($wa['whatsapp_provider'] === 'meta')
                                    <x-field label="Approved template name" name="wa.whatsapp_template_{{ $kind }}" hint="Empty = send the message as plain text (only delivered if the client wrote to you in the last 24 hours)."><input class="input" type="text" wire:model="wa.whatsapp_template_{{ $kind }}" placeholder="e.g. {{ $kind }}_reminder"></x-field>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <x-field label="Invoice reminder days" name="wa.whatsapp_invoice_days" hint="Days counted from the due date, separated by commas. −3 = three days before, 0 = on the due date, 7 = a week overdue.">
                        <input class="input max-w-xs" type="text" wire:model="wa.whatsapp_invoice_days" placeholder="-3,0,7">
                    </x-field>
                </div>
                <div class="mt-4 flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveWhatsApp">Save WhatsApp settings</button></div>
            </div>

            <div class="border-t border-stone-200 p-4 sm:p-5">
                <p class="label">Send a test message</p>
                <div class="flex flex-wrap items-start gap-2">
                    <div class="w-full max-w-xs"><input class="input" type="tel" wire:model="testPhone" placeholder="+91 98765 43210" aria-label="Send the test message to">@error('testPhone') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <button type="button" class="btn-secondary" wire:click="sendTestWhatsApp" wire:loading.attr="disabled" wire:target="sendTestWhatsApp"><span wire:loading.remove wire:target="sendTestWhatsApp">Send test</span><span wire:loading wire:target="sendTestWhatsApp">Sending…</span></button>
                </div>
                <p class="mt-1 text-xs text-stone-500">Save first. With Meta, a plain test only arrives if that number messaged your business number in the last 24 hours.</p>
                @if ($waResult) <p @class(['mt-2 rounded-md p-3 text-sm break-words', 'bg-emerald-50 text-emerald-800' => $waResult['ok'], 'bg-red-50 text-red-800' => ! $waResult['ok']]) role="status">{{ $waResult['message'] }}</p> @endif
            </div>
        </form>

        {{-- -------------------------------------------------------------- SMS --}}
        <form wire:submit="saveSms" class="card">
            <div class="card-header">
                <h2 class="card-title">Text messages (SMS)</h2>
                @if ($smsLive) <span class="badge-green">Connected</span> @elseif ($sms['sms_enabled'] === '1') <span class="badge-amber">Missing: {{ implode(', ', $smsMissing) }}</span> @else <span class="badge-gray">Off</span> @endif
            </div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-600 md:col-span-2">Used by <strong>Clients → Bulk Messages</strong> to send a text message to selected clients. It works with any SMS service that has an HTTP API (MSG91, Textlocal, Fast2SMS, Twilio and others): copy the address and key from that service’s API page. In India the sender ID and each message template must be registered on a DLT portal first, or the operators block the message.</p>
                <x-field label="Text messages" name="sms.sms_enabled">
                    <select class="input" wire:model.live="sms.sms_enabled"><option value="0">Off</option><option value="1">On</option></select>
                </x-field>
                <x-field label="How the service is called" name="sms.sms_method" hint="Your SMS service’s API page shows which one it uses.">
                    <select class="input" wire:model.live="sms.sms_method">
                        @foreach (\App\Support\Sms::METHODS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="API address" name="sms.sms_url" :required="$sms['sms_enabled'] === '1'" class="md:col-span-2"
                         :hint="$sms['sms_method'] === 'get' ? 'The full address with {phone} and {message} in it, and {token}, {sender} and {template_id} where the service expects them. Example: https://api.example.com/send?apikey={token}&sender={sender}&to={phone}&text={message}&template={template_id}' : 'The address messages are posted to, starting with https://'">
                    <input class="input font-mono text-xs" type="text" wire:model="sms.sms_url" placeholder="https://">
                </x-field>
                @if ($sms['sms_method'] !== 'get')
                    <x-field label="Header that carries the key" name="sms.sms_auth_header" hint="Usually Authorization; some services use authkey or X-API-Key.">
                        <input class="input" type="text" wire:model="sms.sms_auth_header">
                    </x-field>
                @endif
                <x-field label="Sender ID" name="sms.sms_sender_id" hint="The registered name messages come from, e.g. WXAGNC.">
                    <input class="input" type="text" wire:model="sms.sms_sender_id">
                </x-field>
                <x-field label="API key" name="smsToken" class="md:col-span-2" :hint="$hasSmsToken ? 'A key is saved. Leave empty to keep it.' : ($sms['sms_method'] === 'get' ? 'Put {token} in the address where the key goes. Stored encrypted.' : 'Exactly as the header should carry it, e.g. “Bearer abc123”. Stored encrypted.')">
                    <div class="flex gap-2">
                        <input class="input" type="password" wire:model="smsToken" autocomplete="new-password" placeholder="{{ $hasSmsToken ? '•••••••• (saved)' : '' }}">
                        @if ($hasSmsToken) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeSmsToken" wire:confirm="Remove the saved SMS key? Text messages stop until a new one is added.">Remove</button> @endif
                    </div>
                </x-field>
                @if ($sms['sms_method'] !== 'get')
                    <x-field label="Request body (JSON)" name="sms.sms_body" class="md:col-span-2" hint="Must contain {phone} and {message}. {sender} and {template_id} are filled in too. Change the names on the left to what your service expects.">
                        <textarea class="input font-mono text-xs" rows="3" wire:model="sms.sms_body"></textarea>
                    </x-field>
                @endif
                <div class="flex justify-end md:col-span-2"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveSms">Save SMS settings</button></div>
            </div>

            <div class="border-t border-stone-200 p-4 sm:p-5">
                <p class="label">Send a test text message</p>
                <div class="flex flex-wrap items-start gap-2">
                    <div class="w-full max-w-xs"><input class="input" type="tel" wire:model="testSmsPhone" placeholder="+91 98765 43210" aria-label="Send the test text message to">@error('testSmsPhone') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <div class="w-full max-w-xs"><input class="input" type="text" wire:model="testSmsTemplate" placeholder="Template ID (if your service needs one)" aria-label="Template ID"></div>
                    <button type="button" class="btn-secondary" wire:click="sendTestSms" wire:loading.attr="disabled" wire:target="sendTestSms"><span wire:loading.remove wire:target="sendTestSms">Send test</span><span wire:loading wire:target="sendTestSms">Sending…</span></button>
                </div>
                <p class="mt-1 text-xs text-stone-500">Save first. With DLT, a test only arrives if its wording matches a registered template.</p>
                @if ($smsResult) <p @class(['mt-2 rounded-md p-3 text-sm break-words', 'bg-emerald-50 text-emerald-800' => $smsResult['ok'], 'bg-red-50 text-red-800' => ! $smsResult['ok']]) role="status">{{ $smsResult['message'] }}</p> @endif
            </div>
        </form>

        {{-- -------------------------------------------------------------- Log --}}
        <section class="card">
            <div class="card-header"><h2 class="card-title">Recent WhatsApp messages</h2><span class="text-xs text-stone-500">Latest 15</span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>When</th><th>To</th><th>About</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $m)
                            <tr wire:key="msg-{{ $m->id }}">
                                <td class="whitespace-nowrap">{{ fmt_datetime($m->created_at) }}</td>
                                <td><p class="text-stone-900">{{ $m->client?->name ?? '—' }}</p><p class="text-xs text-stone-500">{{ $m->recipient }}</p></td>
                                <td class="max-w-96"><p>{{ \App\Models\MessageLog::EVENTS[$m->event] ?? $m->event }}</p><p class="line-clamp-2 text-xs text-stone-500">{{ $m->body }}</p></td>
                                <td>
                                    <span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-blue', 'failed' => 'badge-red', 'skipped' => 'badge-gray'][$m->status] ?? 'badge-gray' }}">{{ ucfirst($m->status) }}</span>
                                    @if ($m->error) <p class="mt-0.5 max-w-64 text-xs break-words text-red-700">{{ $m->error }}</p> @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="4">No WhatsApp messages yet.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
