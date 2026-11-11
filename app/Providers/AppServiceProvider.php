<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ClientFile;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PortalUser;
use App\Models\Poster;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\Renewal;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'client' => Client::class,
            'client_file' => ClientFile::class,
            'hosting' => HostingRecord::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'expense' => Expense::class,
            'poster' => Poster::class,
            'task' => Task::class,
            'equipment' => Equipment::class,
            'lead' => Lead::class,
            'quote' => Quote::class,
            'quote_template' => QuoteTemplate::class,
            'project' => Project::class,
            'renewal' => Renewal::class,
            'support_ticket' => SupportTicket::class,
            'portal_user' => PortalUser::class,
        ]);

        // The app name set in Settings → Branding also names outgoing emails.
        $this->app->booted(function () {
            try {
                $name = Branding::name();
                config(['app.name' => $name, 'mail.from.name' => $name]);
            } catch (\Throwable) {
                // settings table not migrated yet
            }
        });

        $this->registerGates();
        $this->registerAuthActivity();
    }

    protected function registerGates(): void
    {
        // Deactivated accounts lose every ability immediately.
        Gate::before(fn (User $user) => $user->is_active ? null : false);

        // Super Admin only: users, settings, activity log and everything financial
        // (invoices, payments, expenses and their attachments).
        Gate::define('admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('finance', fn (User $user) => $user->isSuperAdmin());

        foreach (array_keys(User::STAFF_PERMISSIONS) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Adding a client: Super Admins, Staff who manage clients, and Sub Admins.
        Gate::define('clients.create', fn (User $user) => $user->isSubAdmin() || $user->hasPermission('clients.manage'));

        // Opening one particular client. Sub Admins are limited to clients they added.
        Gate::define('client.access', fn (User $user, Client $client) => $user->hasPermission('clients.view')
            && (! $user->isSubAdmin() || (int) $client->created_by === $user->id));

        // Changing a client's details, hosting records and files. Staff need clients.manage;
        // Sub Admins may do it for the clients they added. Archiving, deleting and renewals stay with clients.manage.
        Gate::define('client.update', fn (User $user, Client $client) => $user->hasPermission('clients.manage')
            || ($user->isSubAdmin() && (int) $client->created_by === $user->id));

        // Support portal accounts: Super Admins manage all of them; Sub Admins add and manage their own.
        Gate::define('portal-users.manage', fn (User $user) => $user->isSuperAdmin() || $user->isSubAdmin());

        // Shared team areas (tasks, posters, the hosting overview): everyone except Sub Admins.
        Gate::define('workspace', fn (User $user) => ! $user->isSubAdmin());
        Gate::define('tasks.view', fn (User $user) => ! $user->isSubAdmin());
        Gate::define('posters.view', fn (User $user) => ! $user->isSubAdmin());
    }

    protected function registerAuthActivity(): void
    {
        // Only team sign-ins (the "web" guard) go in the activity log. Support portal
        // accounts use the "portal" guard and are tracked on the Portal Users screen instead.
        Event::listen(function (Login $event) {
            if ($event->guard !== 'web' || ! $event->user instanceof User) {
                return;
            }
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            activity('login', $event->user->name.' signed in', $event->user, [], $event->user->id);
        });

        Event::listen(function (Logout $event) {
            if ($event->guard === 'web' && $event->user instanceof User) {
                activity('logout', $event->user->name.' signed out', $event->user, [], $event->user->id);
            }
        });

        Event::listen(function (Failed $event) {
            if ($event->guard !== 'web') {
                return;
            }
            $user = $event->user instanceof User ? $event->user : null;
            activity('login_failed', 'Failed sign-in attempt for '.($event->credentials['email'] ?? 'unknown'), $user, [], $user?->id);
        });

        Event::listen(function (PasswordReset $event) {
            if ($event->user instanceof User) {
                activity('password_reset', $event->user->name.' reset their password', $event->user, [], $event->user->id);
            }
        });
    }
}
