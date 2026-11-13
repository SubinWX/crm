<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Portal\AuthController as PortalAuth;
use App\Http\Controllers\Portal\GoogleController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupportAttachmentController;
use App\Livewire;
use App\Support\InvoicePdf;
use Illuminate\Support\Facades\Route;

// Logo and favicon (public: shown on sign-in pages and the support portal)
Route::get('/branding/{kind}', BrandingController::class)->name('branding.asset');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    // Staff sign in at /staff. (/login is for Super Admins and Sub Admins.)
    Route::get('/staff', [LoginController::class, 'create'])->defaults('area', 'staff')->name('staff.login');
    Route::post('/staff', [LoginController::class, 'store'])->defaults('area', 'staff')->middleware('throttle:20,1');
    Route::redirect('/staff/login', '/staff');
    // Second step for accounts with two-step login: the 6-digit code.
    Route::get('/two-step', [TwoFactorController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-step', [TwoFactorController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitation.accept');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');

// Team side. Pinned to the "web" guard so a support portal session can never satisfy it.
Route::middleware(['auth:web', 'active', 'two-factor.setup'])->group(function () {
    Route::get('/', Livewire\Dashboard::class)->name('dashboard');
    Route::get('/account/two-step', Livewire\Account\TwoFactor::class)->name('two-factor.setup');
    Route::get('/notifications', Livewire\Notifications\Center::class)->name('notifications');
    Route::view('/help', 'help')->name('help');
    Route::get('/calendar', Livewire\Calendar\Index::class)->name('calendar');
    // Headshots appear next to names across the app, so any signed-in user may load them.
    Route::get('/users/{user}/avatar', [FileController::class, 'userAvatar'])->name('users.avatar');

    // Clients, hosting and client files
    Route::middleware('can:clients.view')->group(function () {
        Route::get('/clients', Livewire\Clients\Index::class)->name('clients.index');
        Route::get('/clients/create', Livewire\Clients\Form::class)->middleware('can:clients.create')->name('clients.create');
        Route::get('/clients/{client}', Livewire\Clients\Show::class)->middleware('can:client.access,client')->name('clients.show');
        Route::get('/clients/{client}/logo', [FileController::class, 'clientLogo'])->middleware('can:client.access,client')->name('clients.logo');
        Route::get('/clients/{client}/edit', Livewire\Clients\Form::class)->middleware('can:client.update,client')->name('clients.edit');
        // Hosting now lives inside Hosting & Renewals; the old address still works.
        Route::redirect('/hosting', '/renewals?type=hosting')->name('hosting.index');
        Route::get('/client-files/{clientFile}', [FileController::class, 'clientFile'])->name('files.client');
    });

    Route::get('/equipment', Livewire\Equipment\Index::class)->name('equipment.index');

    // Profile: everyone has their own; Super Admins, Admins and leave managers can open the people they look after.
    Route::get('/profile', Livewire\Account\Profile::class)->name('profile');
    Route::get('/profile/{user}', Livewire\Account\Profile::class)->name('profile.show');

    // Office: the company rules, meeting notes and documents; the timeline; the chat and the AI assistant. Open to everyone signed in.
    Route::get('/policies', Livewire\Office\Policies::class)->name('policies');
    Route::get('/meeting-notes', Livewire\Office\MeetingNotes::class)->name('meeting-notes');
    Route::get('/documents', Livewire\Office\Documents::class)->name('documents');
    Route::get('/documents/{document}/download', [FileController::class, 'document'])->name('documents.download');
    Route::get('/timeline', Livewire\Office\Roadmap::class)->name('roadmap');
    Route::get('/chat', Livewire\Chat\Index::class)->name('chat');
    Route::get('/assistant', Livewire\Assistant\Index::class)->name('assistant');

    // Email Tools: signature and template builders, open to everyone on the team.
    Route::get('/email-tools/signature', Livewire\EmailTools\Signature::class)->name('email-tools.signature');
    Route::get('/email-tools/templates', Livewire\EmailTools\Template::class)->name('email-tools.templates');
    Route::get('/email-tools/saved-signatures', Livewire\EmailTools\SavedSignatures::class)->name('email-tools.saved-signatures');
    Route::get('/email-tools/saved-templates', Livewire\EmailTools\SavedTemplates::class)->name('email-tools.saved-templates');

    // Careers (team side): vacancies and the applications received. Resumes are private files.
    Route::middleware('can:careers.manage')->group(function () {
        Route::get('/recruitment', Livewire\Careers\Manage::class)->name('careers.manage');
        Route::get('/recruitment/applications', Livewire\Careers\Manage::class)->defaults('tab', 'applications')->name('careers.applications');
        Route::get('/recruitment/vacancies', Livewire\Careers\Manage::class)->defaults('tab', 'vacancies')->name('careers.vacancies');
        Route::get('/recruitment/page-settings', Livewire\PublicPages\PageSettings::class)->defaults('page', 'careers')->name('careers.settings');
        Route::get('/recruitment/export', [ExportController::class, 'applications'])->name('careers.export');
        Route::get('/recruitment/applications/{application}/resume', [FileController::class, 'resume'])->name('careers.resume');
        Route::get('/recruitment/applications/{application}/resume/view', [FileController::class, 'resumeView'])->name('careers.resume.view');
    });

    // Shared team areas: everyone except Sub Admins (uploads need posters.manage)
    Route::middleware('can:workspace')->group(function () {
        Route::get('/tasks', Livewire\Tasks\Index::class)->name('tasks.index');
        Route::get('/posters', Livewire\Posters\Index::class)->name('posters.index');
        Route::get('/posters/{poster}/image', [FileController::class, 'posterImage'])->name('posters.image');
        Route::get('/posters/{poster}/thumb', [FileController::class, 'posterThumb'])->name('posters.thumb');
        Route::get('/posters/{poster}/download', [FileController::class, 'posterDownload'])->name('posters.download');
    });

    // Finance: Super Admins only
    Route::middleware('can:finance')->group(function () {
        Route::get('/gst-summary', Livewire\Finance\GstSummary::class)->name('gst.summary');
        Route::get('/gst-summary/export', [ExportController::class, 'gst'])->name('gst.export');
        Route::get('/invoices', Livewire\Invoices\Index::class)->name('invoices.index');
        Route::get('/invoices/export-pdf', [ReportController::class, 'invoices'])->name('invoices.export');
        Route::get('/invoices/create', Livewire\Invoices\Form::class)->name('invoices.create');
        Route::get('/invoices/{invoice}', Livewire\Invoices\Show::class)->name('invoices.show');
        Route::get('/invoices/{invoice}/edit', Livewire\Invoices\Form::class)->name('invoices.edit');
        Route::get('/invoices/{invoice}/pdf', [FileController::class, 'invoicePdf'])->name('invoices.pdf');
        Route::get('/invoices/{invoice}/uploaded-copy', [FileController::class, 'invoiceUpload'])->name('invoices.uploaded');
        Route::get('/payments', Livewire\Payments\Index::class)->name('payments.index');
        Route::get('/payments/export-pdf', [ReportController::class, 'payments'])->name('payments.export');
        Route::get('/payments/create', Livewire\Payments\Form::class)->name('payments.create');
        Route::get('/payments/{payment}', Livewire\Payments\Form::class)->name('payments.edit');
        Route::get('/expenses', Livewire\Expenses\Index::class)->name('expenses.index');
        Route::get('/expenses/export', [FileController::class, 'expensesCsv'])->name('expenses.export');
        Route::get('/expenses/export-pdf', [ReportController::class, 'expenses'])->name('expenses.export-pdf');
        Route::get('/expenses/{expense}/receipt', [FileController::class, 'expenseReceipt'])->name('expenses.receipt');
    });

    // Sales: quotes & proposals (leads and follow-ups are added below)
    Route::middleware('can:sales.manage')->group(function () {
        Route::get('/quotes', Livewire\Quotes\Index::class)->name('quotes.index');
        Route::get('/quotes/create', Livewire\Quotes\Form::class)->name('quotes.create');
        Route::get('/quotes/{quote}', Livewire\Quotes\Show::class)->name('quotes.show');
        Route::get('/quotes/{quote}/edit', Livewire\Quotes\Form::class)->name('quotes.edit');
        Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
        Route::get('/quotes/{quote}/revisions/{revision}/pdf', [QuoteController::class, 'revision'])->name('quotes.revision');
        Route::get('/quote-files/{file}', [QuoteController::class, 'file'])->name('quotes.file');
        Route::get('/leads', Livewire\Leads\Index::class)->name('leads.index');
        Route::get('/leads/export', [ExportController::class, 'leads'])->name('leads.export');
        Route::get('/leads/{lead}', Livewire\Leads\Show::class)->name('leads.show');
    });

    // Team work areas: everyone except Sub Admins; each screen checks its own permission as well
    Route::middleware('can:workspace')->group(function () {
        Route::get('/import', Livewire\Data\Import::class)->name('import');

        // Team: timesheets, leave and attendance. Each screen shows your own; "team.manage" adds the whole team.
        Route::get('/timesheet', Livewire\Team\Timesheet::class)->name('timesheet');
        Route::get('/timesheet/export', [ExportController::class, 'timesheet'])->name('timesheet.export');
        Route::get('/leave', Livewire\Team\Leave::class)->name('leave');
        Route::get('/attendance', Livewire\Team\Attendance::class)->name('attendance');
        Route::get('/attendance/export', [ExportController::class, 'attendance'])->middleware('can:team.manage')->name('attendance.export');
        Route::middleware('can:clients.view')->group(function () {
            Route::get('/projects', Livewire\Work\Projects::class)->name('projects.index');
            Route::get('/projects/{project}', Livewire\Work\ProjectShow::class)->name('projects.show');
            Route::get('/renewals', Livewire\Work\Renewals::class)->name('renewals.index');
        });
        Route::get('/clients-export', [ExportController::class, 'clients'])->middleware('can:clients.manage')->name('clients.export');
    });

    // Support desk (team side of the support portal)
    Route::middleware('can:support.manage')->group(function () {
        Route::get('/support-desk/tickets', Livewire\Support\Tickets::class)->name('support.tickets.index');
        Route::get('/support-desk/tickets/{ticket}', Livewire\Support\TicketShow::class)->name('support.tickets.show');
        Route::get('/support-desk/attachments/{attachment}', [SupportAttachmentController::class, 'staff'])->name('support.attachments');
        Route::get('/support-desk/page-settings', Livewire\PublicPages\PageSettings::class)->defaults('page', 'support')->name('support.settings');
    });
    Route::get('/support-desk/portal-users', Livewire\Support\PortalUsers::class)->middleware('can:portal-users.manage')->name('support.portal-users');

    // Staff accounts and what each one can use: Super Admins and Admins.
    Route::get('/staff-members', Livewire\Users\Index::class)->defaults('scope', 'staff')->middleware('can:staff.manage')->name('staff.index');

    // Administration: Super Admins only
    Route::middleware('can:admin')->group(function () {
        Route::get('/users', Livewire\Users\Index::class)->name('users.index');
        Route::get('/settings', Livewire\Settings\Index::class)->name('settings.index');
        Route::get('/settings/email-whatsapp', Livewire\Settings\Messaging::class)->name('settings.messaging');
        Route::get('/settings/public-pages', Livewire\Settings\PublicPages::class)->name('settings.public-pages');
        Route::get('/settings/pdf', Livewire\Settings\PdfSettings::class)->name('settings.pdf');
        Route::get('/settings/pdf/sample', fn () => InvoicePdf::make(InvoicePdf::sample())->stream('sample-invoice.pdf'))->name('settings.pdf.sample');
        Route::get('/settings/quote-templates', Livewire\Quotes\Templates::class)->name('quote-templates.index');
        Route::get('/settings/quote-templates/create', Livewire\Quotes\TemplateForm::class)->name('quote-templates.create');
        Route::get('/settings/quote-templates/{template}/edit', Livewire\Quotes\TemplateForm::class)->name('quote-templates.edit');
        Route::get('/activity', Livewire\Activity\Index::class)->name('activity.index');
    });
});

/*
|--------------------------------------------------------------------------
| Public careers pages: /careers
|--------------------------------------------------------------------------
| No sign-in. Lists open vacancies; each has its own page with an application form.
*/
Route::get('/careers', Livewire\Careers\Listing::class)->name('careers.index');
// Headshots uploaded for email signatures. Public on purpose: the people who receive the email must be able to load them.
// The 40-character random name is the only way to reach a file.
Route::get('/e/{file}', [BrandingController::class, 'emailAsset'])->where('file', '[A-Za-z0-9]{40}\.jpg')->name('email.asset');

// Logo and banner images of the public pages (public: they are part of the page). Set in Public Pages and each Page Settings.
Route::get('/site/logo', [BrandingController::class, 'siteLogo'])->name('site.logo');
Route::get('/site/banner/{page}', [BrandingController::class, 'siteBanner'])->name('site.banner');
Route::get('/careers/general-application', Livewire\Careers\Apply::class)->name('careers.general');
Route::get('/careers/{slug}', Livewire\Careers\Apply::class)->name('careers.show');

/*
|--------------------------------------------------------------------------
| Public support portal: /support
|--------------------------------------------------------------------------
| Separate accounts (the "portal" guard). Link to /support from your website footer.
*/
Route::prefix('support')->name('portal.')->group(function () {
    Route::get('/', [PortalAuth::class, 'home'])->name('home');

    Route::middleware('guest:portal')->group(function () {
        Route::get('/login', [PortalAuth::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuth::class, 'login'])->middleware('throttle:20,1');
        Route::get('/register', [PortalAuth::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuth::class, 'register'])->middleware('throttle:10,1');
        Route::get('/forgot-password', [PortalAuth::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [PortalAuth::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
        Route::get('/reset-password/{token}', [PortalAuth::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [PortalAuth::class, 'reset'])->name('password.update');
        Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google');
        Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
    });

    Route::middleware(['auth:portal', 'portal.active'])->group(function () {
        Route::post('/logout', [PortalAuth::class, 'logout'])->name('logout');
        Route::get('/verify-email', [PortalAuth::class, 'verifyNotice'])->name('verify.notice');
        Route::post('/verify-email', [PortalAuth::class, 'resendVerification'])->middleware('throttle:6,1')->name('verify.resend');

        // Tickets need a confirmed email address.
        Route::middleware('portal.verified')->group(function () {
            Route::get('/tickets', Livewire\Portal\Tickets::class)->name('tickets.index');
            Route::get('/tickets/new', Livewire\Portal\TicketCreate::class)->name('tickets.create');
            Route::get('/tickets/{number}', Livewire\Portal\TicketShow::class)->name('tickets.show');
            Route::get('/attachments/{attachment}', [SupportAttachmentController::class, 'portal'])->name('attachments');
        });
    });

    Route::get('/verify-email/{id}/{hash}', [PortalAuth::class, 'verify'])->middleware(['signed', 'throttle:12,1'])->name('verify');
});
