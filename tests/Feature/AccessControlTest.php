<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Account\Menu;
use App\Livewire\Clients\Form as ClientForm;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Payments\Form as PaymentForm;
use App\Livewire\Posters\Index as PostersIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\Poster;
use App\Models\User;
use App\Notifications\UserInvited;
use App\Support\ExpenseFilters;
use App\Support\InvoiceFilters;
use App\Support\PaymentFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected Client $client;

    protected Invoice $invoice;

    protected Expense $expense;

    protected Poster $poster;

    protected ClientFile $file;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->superAdmin()->create();
        $this->client = Client::create(['name' => 'Acme']);

        Storage::disk('local')->put('invoices/copy.pdf', '%PDF-1.4 copy');
        Storage::disk('local')->put('receipts/r.pdf', '%PDF-1.4 receipt');
        Storage::disk('local')->put('posters/p.png', 'png-bytes');
        Storage::disk('local')->put('clients/1/contract.pdf', '%PDF-1.4 contract');

        $this->invoice = Invoice::create(['number' => 'INV-1', 'client_id' => $this->client->id, 'invoice_date' => '2026-11-01', 'total' => 1000, 'status' => 'sent', 'uploaded_pdf_path' => 'invoices/copy.pdf', 'uploaded_pdf_name' => 'copy.pdf']);
        $this->expense = Expense::create(['expense_date' => '2026-11-01', 'amount' => 250, 'payee' => 'Hostinger', 'receipt_path' => 'receipts/r.pdf', 'receipt_name' => 'r.pdf']);
        $this->poster = Poster::create(['title' => 'Diwali', 'platform' => 'instagram', 'image_path' => 'posters/p.png', 'original_name' => 'p.png', 'mime' => 'image/png', 'size' => 9]);
        $this->file = ClientFile::create(['client_id' => $this->client->id, 'name' => 'contract.pdf', 'path' => 'clients/1/contract.pdf']);
    }

    /** @return array<string, string> */
    protected function financeUrls(): array
    {
        return [
            'invoices' => route('invoices.index'),
            'invoice' => route('invoices.show', $this->invoice),
            'invoice pdf' => route('invoices.pdf', $this->invoice),
            'invoice uploaded copy' => route('invoices.uploaded', $this->invoice),
            'new invoice' => route('invoices.create'),
            'payments' => route('payments.index'),
            'record payment' => route('payments.create'),
            'expenses' => route('expenses.index'),
            'expenses csv' => route('expenses.export'),
            'invoices pdf report' => route('invoices.export'),
            'payments pdf report' => route('payments.export'),
            'expenses pdf report' => route('expenses.export-pdf'),
            'expense receipt' => route('expenses.receipt', $this->expense),
        ];
    }

    public function test_guests_are_sent_to_login_for_everything(): void
    {
        $urls = $this->financeUrls() + [
            'dashboard' => route('dashboard'), 'clients' => route('clients.index'), 'client' => route('clients.show', $this->client),
            'client file' => route('files.client', $this->file), 'poster image' => route('posters.image', $this->poster),
            'poster thumb' => route('posters.thumb', $this->poster), 'poster download' => route('posters.download', $this->poster),
            'users' => route('users.index'), 'settings' => route('settings.index'), 'activity' => route('activity.index'),
            'equipment' => route('equipment.index'), 'tasks' => route('tasks.index'), 'posters' => route('posters.index'),
        ];
        foreach ($urls as $name => $url) {
            $this->get($url)->assertRedirect(route('login'), "guest reached {$name}");
        }
    }

    public function test_staff_cannot_reach_financial_records_attachments_or_admin_screens(): void
    {
        $staff = User::factory()->staff(['clients.manage', 'tasks.manage', 'posters.manage'])->create();

        foreach ($this->financeUrls() + ['users' => route('users.index'), 'settings' => route('settings.index'), 'activity' => route('activity.index')] as $name => $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }

        // Livewire components refuse direct calls too, not just the routes.
        Livewire::actingAs($staff)->test(InvoicesIndex::class)->assertForbidden();
        Livewire::actingAs($staff)->test(PaymentForm::class)->assertForbidden();
        Livewire::actingAs($staff)->test(ExpensesIndex::class)->assertForbidden();
        Livewire::actingAs($staff)->test(UsersIndex::class)->assertForbidden();
        Livewire::actingAs($staff)->test(SettingsIndex::class)->assertForbidden();

        // What staff CAN do.
        $this->actingAs($staff)->get(route('clients.index'))->assertOk();
        $this->actingAs($staff)->get(route('clients.show', $this->client))->assertOk()->assertDontSee('Invoices &amp; Payments', false)->assertDontSee('Pending amount');
        $this->actingAs($staff)->get(route('clients.show', ['client' => $this->client, 'tab' => 'finance']))->assertOk()->assertDontSee('Payment history');
        $this->actingAs($staff)->get(route('files.client', $this->file))->assertOk();
        $this->actingAs($staff)->get(route('posters.download', $this->poster))->assertOk();
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertDontSee('Outstanding invoice balance')->assertDontSee('Expenses this month');
    }

    public function test_staff_permissions_are_enforced_per_area(): void
    {
        $viewer = User::factory()->staff(['clients.view'])->create();
        $none = User::factory()->staff([])->create();

        $this->actingAs($viewer)->get(route('clients.index'))->assertOk();
        $this->actingAs($viewer)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('clients.edit', $this->client))->assertForbidden();
        Livewire::actingAs($viewer)->test(ClientForm::class)->assertForbidden();
        Livewire::actingAs($viewer)->test(PostersIndex::class)->call('create')->assertForbidden();
        Livewire::actingAs($viewer)->test(PostersIndex::class)->call('delete', $this->poster->id)->assertForbidden();

        $this->actingAs($none)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($none)->get(route('files.client', $this->file))->assertForbidden();
        $this->actingAs($none)->get(route('renewals.index'))->assertForbidden();
    }

    public function test_super_admin_can_access_financial_records_and_attachments(): void
    {
        foreach ($this->financeUrls() as $name => $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->assertStringContainsString('Hostinger', $this->actingAs($this->admin)->get(route('expenses.export'))->streamedContent());
    }

    public function test_files_are_not_publicly_reachable(): void
    {
        $this->get('/storage/invoices/copy.pdf')->assertNotFound();
        $this->get('/storage/posters/p.png')->assertNotFound();
    }

    public function test_deactivated_users_lose_access_but_history_is_kept(): void
    {
        $other = User::factory()->superAdmin()->create(['name' => 'Former Admin']);
        $payment = Payment::create(['client_id' => $this->client->id, 'amount' => 400, 'payment_date' => '2026-11-02', 'received_by' => $other->id]);

        Livewire::actingAs($this->admin)->test(UsersIndex::class)->call('toggleActive', $other->id);
        $other->refresh();
        $this->assertFalse($other->is_active);

        // Cannot sign in, and an existing session is ended.
        auth()->logout();
        $this->post(route('login'), ['email' => $other->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('login'));

        // The payment still shows who received it…
        $this->assertSame('Former Admin', $payment->fresh()->receiver->name);
        $this->actingAs($this->admin)->get(route('payments.index'))->assertOk()->assertSee('Former Admin');

        // …but they can no longer be picked for a new payment.
        Livewire::actingAs($this->admin)->test(PaymentForm::class)
            ->set('form.client_id', (string) $this->client->id)
            ->set('form.payment_method_id', (string) Lookup::where('type', 'payment_method')->value('id'))
            ->set('form.amount', '100')
            ->set('form.received_by', (string) $other->id)
            ->call('save')
            ->assertHasErrors('form.received_by');

        // The last active Super Admin cannot be removed.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)->call('toggleActive', $this->admin->id);
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_invitation_flow_creates_a_usable_account(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin)->test(UsersIndex::class, ['scope' => 'staff'])
            ->call('create')
            ->set('form.name', 'New Staff')
            ->set('form.email', 'new@agency.test')
            ->set('form.role', 'staff')
            ->set('form.permissions', ['clients.view', 'tasks.manage'])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'new@agency.test')->firstOrFail();
        $this->assertNull($user->password);

        $token = null;
        Notification::assertSentTo($user, UserInvited::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        auth()->logout();
        $this->get(route('invitation.show', 'wrong-token'))->assertOk()->assertSee('no longer valid');
        $this->get(route('invitation.show', $token))->assertOk()->assertSee('New Staff');
        $this->post(route('invitation.accept', $token), ['password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // The link is single use.
        auth()->logout();
        $this->get(route('invitation.show', $token))->assertSee('no longer valid');
        $this->post(route('staff.login'), ['email' => 'new@agency.test', 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id]);
    }

    public function test_deactivated_dropdown_values_stay_on_existing_records(): void
    {
        $industry = Lookup::where('type', 'industry')->first();
        $this->client->update(['industry_id' => $industry->id]);

        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('setTab', 'industry')->call('toggleActive', $industry->id);

        $this->assertFalse($industry->fresh()->is_active);
        $this->assertSame($industry->name, $this->client->fresh()->industry->name);
        $this->assertFalse(Lookup::options('industry')->contains('id', $industry->id));               // hidden for new records
        $this->assertTrue(Lookup::options('industry', $industry->id)->contains('id', $industry->id)); // kept when editing this one
        // In use, so it cannot be deleted.
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('setTab', 'industry')->call('delete', $industry->id);
        $this->assertNotNull($industry->fresh());
    }

    public function test_list_reports_download_as_pdf_and_respect_filters(): void
    {
        Payment::create(['client_id' => $this->client->id, 'amount' => 400, 'payment_date' => '2026-11-02', 'received_by' => $this->admin->id]);

        foreach (['invoices.export' => 'invoices', 'payments.export' => 'payments', 'expenses.export-pdf' => 'expenses'] as $route => $slug) {
            $response = $this->actingAs($this->admin)->get(route($route, ['from' => '2026-11-01', 'to' => '2026-11-30']));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertStringContainsString($slug.'-', $response->headers->get('content-disposition'));
        }

        // The report is built from the same filtered query as the screen.
        $this->assertSame(1, InvoiceFilters::apply(Invoice::query(), ['status' => 'sent'])->count());
        $this->assertSame(0, InvoiceFilters::apply(Invoice::query(), ['status' => 'paid'])->count());
        $this->assertSame(0, ExpenseFilters::apply(Expense::query(), ['from' => '2026-12-01'])->count());
        $this->assertSame(1, PaymentFilters::apply(Payment::query(), ['unallocated' => '1'])->count());
    }

    public function test_client_logo_and_user_headshot_can_be_added_updated_and_removed(): void
    {
        // Client logo on create.
        Livewire::actingAs($this->admin)->test(ClientForm::class)
            ->set('form.name', 'Logo Co')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('save')->assertHasNoErrors();
        $client = Client::where('name', 'Logo Co')->firstOrFail();
        Storage::disk('local')->assertExists($client->logo_path);
        $this->actingAs($this->admin)->get(route('clients.logo', $client))->assertOk();

        // Replace, then remove.
        $old = $client->logo_path;
        Livewire::actingAs($this->admin)->test(ClientForm::class, ['client' => $client])
            ->set('logo', UploadedFile::fake()->image('new.jpg', 300, 300))->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->assertNotSame($old, $client->fresh()->logo_path);
        Livewire::actingAs($this->admin)->test(ClientForm::class, ['client' => $client->fresh()])
            ->set('removeLogo', true)->call('save')->assertHasNoErrors();
        $this->assertNull($client->fresh()->logo_path);

        // Not an image → rejected.
        Livewire::actingAs($this->admin)->test(ClientForm::class)
            ->set('form.name', 'Bad Logo')
            ->set('logo', UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'))
            ->call('save')->assertHasErrors('logo');

        // Super Admin headshot.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $this->admin->id)
            ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
            ->call('save')->assertHasNoErrors();
        $this->admin->refresh();
        Storage::disk('local')->assertExists($this->admin->avatar_path);
        $this->actingAs($this->admin)->get(route('users.avatar', $this->admin))->assertOk();

        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $this->admin->id)->set('removePhoto', true)->call('save')->assertHasNoErrors();
        $this->assertNull($this->admin->fresh()->avatar_path);

        // Images are private: guests cannot load them, staff without client access cannot load logos.
        $client->update(['logo_path' => 'logos/x.png']);
        Storage::disk('local')->put('logos/x.png', 'png');
        auth()->logout();
        $this->get(route('clients.logo', $client))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->staff([])->create())->get(route('clients.logo', $client))->assertForbidden();
    }

    public function test_account_menu_lets_a_user_change_their_own_password_and_picture(): void
    {
        $staff = User::factory()->staff([])->create();
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('Change Password')->assertSee('Change Picture')->assertSee($staff->email);

        Livewire::actingAs($staff)->test(Menu::class)
            ->set('current_password', 'wrong-one')->set('password', 'new-secret-9')->set('password_confirmation', 'new-secret-9')
            ->call('savePassword')->assertHasErrors('current_password');

        Livewire::actingAs($staff)->test(Menu::class)
            ->set('current_password', 'password')->set('password', 'new-secret-9')->set('password_confirmation', 'new-secret-9')
            ->call('savePassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('new-secret-9', $staff->fresh()->password));

        Livewire::actingAs($staff)->test(Menu::class)
            ->set('photo', UploadedFile::fake()->image('me.png', 300, 300))
            ->call('savePicture')->assertHasNoErrors();
        Storage::disk('local')->assertExists($staff->fresh()->avatar_path);

        Livewire::actingAs($staff->fresh())->test(Menu::class)->call('removePicture');
        $this->assertNull($staff->fresh()->avatar_path);

        auth()->logout();
        $this->post(route('staff.login'), ['email' => $staff->email, 'password' => 'new-secret-9'])->assertRedirect(route('dashboard'));
    }

    public function test_admins_sign_in_at_login_and_staff_at_staff(): void
    {
        $staff = User::factory()->staff(['clients.view'])->create();
        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null]);

        $this->get('/login')->assertOk()->assertSee('Admin sign in');
        $this->get('/staff')->assertOk()->assertSee('Staff sign in');
        $this->get('/staff/login')->assertRedirect('/staff');

        // Staff sign in at /staff, not on the admin page.
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/staff', ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
        // Signing out, or an expired session, returns them to the staff page.
        $this->post(route('logout'))->assertRedirect('/staff');
        $this->withCookie(LoginController::AREA_COOKIE, 'staff')->get(route('clients.index'))->assertRedirect(url('/staff'));

        // Super Admins and Sub Admins sign in at /login, not on the staff page.
        foreach ([$this->admin, $sub] as $user) {
            $this->post('/staff', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'));
        }

        // A wrong password says nothing about which page the account belongs to.
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'These credentials do not match an active account.']);

        $this->assertSame(url('/staff'), $staff->loginUrl());
        $this->assertSame(route('login'), $sub->loginUrl());
        $this->assertSame(route('login'), $this->admin->loginUrl());
    }

    public function test_poster_upload_is_validated(): void
    {
        $staff = User::factory()->staff(['posters.manage'])->create();

        Livewire::actingAs($staff)->test(PostersIndex::class)
            ->call('create')
            ->set('form.title', 'Bad file')
            ->set('image', UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors('image');

        Livewire::actingAs($staff)->test(PostersIndex::class)
            ->call('create')
            ->set('form.title', 'Festive offer')
            ->set('form.tags', '#diwali, offer')
            ->set('image', UploadedFile::fake()->image('offer.png', 800, 800))
            ->call('save')
            ->assertHasNoErrors();

        $poster = Poster::where('title', 'Festive offer')->firstOrFail();
        $this->assertSame('diwali, offer', $poster->tags);
        Storage::disk('local')->assertExists($poster->image_path);
        $this->actingAs($staff)->get(route('posters.download', $poster))->assertOk()->assertDownload('offer.png');
        auth()->logout();
        $this->get(route('posters.image', $poster))->assertRedirect(route('login'));
    }

    public function test_an_expense_can_be_linked_to_office_self_other_or_a_client(): void
    {
        $category = Lookup::firstOrCreate(['type' => 'expense_category', 'name' => 'Travel'])->id;
        $office = Lookup::where('type', 'expense_link')->where('name', 'Office')->value('id');
        $this->assertNotNull($office);
        $this->assertSame(['Office', 'Self', 'Other'], Lookup::ofType('expense_link')->pluck('name')->all());

        $form = ['form.expense_date' => '2026-11-12', 'form.amount' => '3000', 'form.category_id' => $category, 'form.payee' => 'Cab'];

        Livewire::actingAs($this->admin)->test(ExpensesIndex::class)
            ->call('create')->set($form)->set('form.linked_to', 'link:'.$office)->call('save')->assertHasNoErrors();
        $expense = Expense::where('payee', 'Cab')->firstOrFail();
        $this->assertSame([null, $office], [$expense->client_id, $expense->link_id]);
        $this->assertSame('Office', $expense->linkedToLabel());

        // Switching it to a client clears the option, and back to overhead clears both.
        Livewire::actingAs($this->admin)->test(ExpensesIndex::class)
            ->call('edit', $expense->id)->assertSet('form.linked_to', 'link:'.$office)
            ->set('form.linked_to', 'client:'.$this->client->id)->call('save')->assertHasNoErrors();
        $this->assertSame([$this->client->id, null], [$expense->refresh()->client_id, $expense->link_id]);
        $this->assertSame($this->client->name, $expense->linkedToLabel());

        Livewire::actingAs($this->admin)->test(ExpensesIndex::class)
            ->call('edit', $expense->id)->set('form.linked_to', 'link:999999')->call('save')->assertHasErrors('form.linked_to')
            ->set('form.linked_to', 'client:abc')->call('save')->assertHasErrors('form.linked_to')
            ->set('form.linked_to', '')->call('save')->assertHasNoErrors();
        $this->assertSame('General overhead', $expense->refresh()->linkedToLabel());

        $expense->update(['link_id' => $office]);
        $this->assertSame(1, ExpenseFilters::apply(Expense::query(), ['client' => 'link:'.$office])->count());
        $this->assertSame(0, ExpenseFilters::apply(Expense::query(), ['client' => 'overhead'])->where('payee', 'Cab')->count());
        $this->actingAs($this->admin)->get(route('expenses.export-pdf', ['client' => 'link:'.$office]))->assertOk();
    }
}
