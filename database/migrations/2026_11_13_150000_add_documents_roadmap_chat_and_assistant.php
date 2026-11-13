<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Office → Documents (files people upload), the Timeline & Change log (what is done and what is
 * planned), the internal chat, and the AI assistant's saved conversations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // whose document it is
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            // On = a company document everyone can open. Off = only the person, whoever added it and their managers.
            $table->boolean('shared')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'category']);
        });

        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('status', 20)->default('planned');   // planned | in_progress | done
            $table->string('area', 60)->nullable();
            $table->date('happened_on')->nullable();            // done: when it was released; otherwise the target date
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'happened_on']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete();   // empty = the team room
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'sender_id', 'id']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('team_chat_read_at')->nullable());

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);   // user | assistant
            $table->mediumText('content');
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        $now = now();
        $done = [
            ['2026-11-10', 'Clients', 'Clients, contacts, files, hosting and renewals', 'Client profiles with contacts, private files, hosting records and renewal reminders.'],
            ['2026-11-10', 'Finance', 'Invoices, payments and expenses', 'Invoices with PDF, payments against invoices, expenses with receipts.'],
            ['2026-11-10', 'Support', 'Support portal and tickets', 'Clients raise tickets at /support; the team answers them in the Support desk.'],
            ['2026-11-11', 'Sales', 'Leads, quotes and proposals', 'Lead pipeline with follow-ups, and quotes built from templates.'],
            ['2026-11-12', 'Careers', 'Careers page and applications', 'Public vacancies, application form and applicant tracking.'],
            ['2026-11-12', 'Finance', 'GST summary and GST invoices', 'CGST/SGST/IGST on invoices and a monthly GST summary.'],
            ['2026-11-12', 'Security', 'Two-step login', 'Authenticator-app codes and recovery codes.'],
            ['2026-11-12', 'Office', 'Email signatures and templates', 'Builders for email signatures and email templates.'],
            ['2026-11-13', 'Team', 'Timesheet, timer, leave and attendance', 'Time tracker per project, leave requests with approval, attendance sheet and holidays.'],
            ['2026-11-13', 'Projects', 'Tasks under projects', 'Tasks belong to projects and can have several people working on them.'],
            ['2026-11-13', 'Team', 'Leave types and balances', 'CL, EL, RH, ML and LOP with yearly allowances, automatic and custom balances, quarter days.'],
            ['2026-11-13', 'Team', 'Profile page', 'Personal details, emergency contact and allocated devices for each person.'],
            ['2026-11-13', 'Office', 'Documents, Rules & Policies, Meeting Notes', 'Document uploads for everyone, plus the company rules and meeting notes under Office.'],
            ['2026-11-13', 'Office', 'Internal chat, AI assistant and this timeline', 'Team room and direct messages, an AI assistant, and the Timeline & Change log.'],
        ];
        $planned = [
            ['Setup', 'Hosting, daily backups and the background scheduler', 'Move the CRM from one computer to a server, back up the database every day and switch on scheduled reminders.'],
            ['Finance', 'Payroll and salary slips', 'Monthly salary per person, with loss-of-pay days taken from leave and attendance.'],
            ['Finance', 'Recurring invoices', 'Automatic monthly invoices for retainers and maintenance clients.'],
            ['Finance', 'Online payment links', 'A Pay Now link on invoices that marks the invoice paid automatically.'],
            ['Finance', 'Reports', 'Profit and loss by month, revenue by client, outstanding dues by age, lead conversion.'],
            ['Finance', 'Vendors and purchases', 'Who the company pays and what is due to them.'],
            ['Team', 'Announcements', 'A notice board on the dashboard with birthdays and work anniversaries.'],
            ['Team', 'Performance reviews', 'Goals and appraisal ratings.'],
            ['Team', 'Onboarding and exit checklists', 'Accounts to create and devices to hand over or collect.'],
            ['Clients', 'Contracts and agreements', 'Templates, sign-off and expiry reminders.'],
            ['Clients', 'Client portal extras', 'Clients see their invoices, project progress and files.'],
            ['Clients', 'Password vault', 'Client logins stored encrypted, with access per person.'],
            ['Clients', 'Bulk email and WhatsApp', 'Greetings, offers and renewal campaigns to selected clients.'],
            ['General', 'Phone-friendly layout', 'Attendance check-in and leave from a mobile phone.'],
            ['General', 'Global search', 'One box that finds clients, invoices, projects and tickets.'],
        ];
        $rows = [];
        foreach ($done as [$date, $area, $title, $details]) {
            $rows[] = ['title' => $title, 'details' => $details, 'status' => 'done', 'area' => $area, 'happened_on' => $date, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($planned as $i => [$area, $title, $details]) {
            $rows[] = ['title' => $title, 'details' => $details, 'status' => 'planned', 'area' => $area, 'happened_on' => null, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('roadmap_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('team_chat_read_at'));
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('roadmap_items');
        Schema::dropIfExists('documents');
    }
};
