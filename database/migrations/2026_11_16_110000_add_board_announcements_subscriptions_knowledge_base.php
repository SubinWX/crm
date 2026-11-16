<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Announcements (the notice board), the software subscriptions tracker and the knowledge base; plus the new items on the Timeline. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('pinned')->default(false);
            $table->date('expires_on')->nullable();   // after this day it leaves the dashboard
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('software_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('billing_cycle', 20)->default('monthly');   // monthly | quarterly | yearly
            $table->date('renews_on')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('seats')->nullable();
            $table->string('status', 20)->default('active');           // active | cancelled
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'renews_on']);
        });

        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 60)->nullable();
            $table->mediumText('body')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('category');
        });

        if (! Schema::hasTable('roadmap_items')) {
            return;
        }
        $now = now();
        $done = [
            ['Projects', 'Kanban board for tasks', 'Drag tasks between To do, In progress and Done.'],
            ['Team', 'Announcements', 'A notice board on the dashboard with birthdays and work anniversaries.'],
            ['Finance', 'Software subscriptions tracker', 'Every tool the company pays for, with its cost, renewal date and owner.'],
            ['Office', 'Knowledge base', 'How-to articles and process documents the team can search.'],
            ['General', 'Guided tour for new people', 'A step-by-step tour of the screen on first sign-in.'],
        ];
        $planned = [
            ['Finance', 'Bill tracked hours', 'Turn the hours logged on a project into invoice lines.'],
            ['Finance', 'Budget and runway', 'Monthly budget against actual spend, and how many months of cash are left.'],
            ['Team', 'Expense claims', 'Staff submit a bill with a photo, a manager approves, it joins expenses.'],
            ['Team', 'Goals (OKRs)', 'Company and personal goals for the quarter with progress.'],
            ['Projects', 'Recurring tasks', 'Tasks that create themselves every week or month.'],
            ['Projects', 'Project templates', 'A new project starts with its standard task list.'],
            ['Clients', 'Feedback after a project', 'A short rating form for clients, with scores per client.'],
            ['General', 'Google Calendar sync', 'Leave, deadlines and renewals appear in everyone’s calendar.'],
            ['General', 'Automation rules', 'For example: when an invoice is 7 days overdue, create a task for the account manager.'],
        ];
        DB::table('roadmap_items')->where('title', 'Announcements')->where('status', '!=', 'done')->delete();
        $existing = DB::table('roadmap_items')->pluck('title')->all();
        $next = (int) DB::table('roadmap_items')->max('sort_order');
        $rows = [];
        foreach ($done as [$area, $title, $details]) {
            if (! in_array($title, $existing, true)) {
                $rows[] = ['title' => $title, 'details' => $details, 'status' => 'done', 'area' => $area, 'happened_on' => '2026-11-16', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach ($planned as [$area, $title, $details]) {
            if (! in_array($title, $existing, true)) {
                $rows[] = ['title' => $title, 'details' => $details, 'status' => 'planned', 'area' => $area, 'happened_on' => null, 'sort_order' => ++$next, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('roadmap_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('software_subscriptions');
        Schema::dropIfExists('announcements');
    }
};
