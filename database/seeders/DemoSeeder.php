<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Expense;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\Poster;
use App\Models\Task;
use App\Models\User;
use App\Services\ReminderService;
use App\Support\ImageThumb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Optional sample data for trying the CRM locally:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Demo sign-ins (password for all: "password"): admin@example.test, partner@example.test,
 * staff@example.test. Never run this against a production database.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder refuses to run in production.');

            return;
        }

        $this->call(DatabaseSeeder::class);
        $today = today_local();
        $id = fn (string $type, string $name) => Lookup::where('type', $type)->where('name', $name)->value('id');

        $admin = User::firstOrCreate(['email' => 'admin@example.test'], ['name' => 'Arjun Mehta', 'password' => 'password', 'role' => 'super_admin', 'phone' => '+91 98200 11111', 'alt_phone' => '+91 22 4000 1111']);
        $partner = User::firstOrCreate(['email' => 'partner@example.test'], ['name' => 'Neha Kapoor', 'password' => 'password', 'role' => 'super_admin', 'phone' => '+91 98200 22222']);
        $staff = User::firstOrCreate(['email' => 'staff@example.test'], ['name' => 'Rahul Verma', 'password' => 'password', 'role' => 'staff', 'phone' => '+91 98200 33333', 'permissions' => ['clients.manage', 'tasks.manage', 'posters.manage']]);
        auth()->setUser($admin);

        if (Client::exists()) {
            return;
        }

        foreach (['Priya Nair (Partner)', 'Kiran Web Studio'] as $i => $name) {
            Lookup::firstOrCreate(['type' => 'referrer', 'name' => $name], ['sort_order' => $i + 1]);
        }

        $rows = [
            // name, contact, location, industry, work types, source, months ago, [domain, provider, days until due, amount] | null
            ['Saffron Kitchen', 'Meera Iyer', 'Kochi, Kerala', 'Hospitality & Travel', ['Web Development', 'Hosting', 'Digital Marketing'], 'Referral', 5, ['saffronkitchen.in', 'Hostinger — Business', -6, 5400]],
            ['Bluepeak Realty', 'Vikram Shah', 'Pune, Maharashtra', 'Real Estate', ['Web Development', 'SEO', 'Hosting'], 'Google Search', 4, ['bluepeakrealty.com', 'DigitalOcean — Droplet', 4, 14400]],
            ['Lotus Dental Care', 'Dr. Anita Rao', 'Bengaluru, Karnataka', 'Healthcare', ['Website Maintenance', 'Hosting'], 'Website Enquiry', 3, ['lotusdental.in', 'Hostinger — Cloud Startup', 38, 8200]],
            ['Northwind Academy', 'Suresh Pillai', 'Chennai, Tamil Nadu', 'Education', ['Web Development', 'Digital Marketing'], 'Social Media', 2, ['northwindacademy.edu.in', 'AWS Lightsail', 190, 21000]],
            ['Terra Organics', 'Farah Khan', 'Jaipur, Rajasthan', 'Retail & E-commerce', ['SEO', 'Social Media Management'], 'Referral', 1, null],
            ['Kite Logistics', 'Rohan Desai', 'Ahmedabad, Gujarat', 'Manufacturing', ['Website Maintenance'], 'Cold Outreach', 0, ['kitelogistics.in', 'Client-owned server', 60, 0]],
            ['Maple Interiors', 'Divya Menon', 'Hyderabad, Telangana', 'Professional Services', ['Web Development', 'Branding & Design'], 'Event / Networking', 0, ['mapleinteriors.co.in', 'GoDaddy — Deluxe', 55, 6600]],
        ];

        $clients = [];
        foreach ($rows as $n => [$name, $contact, $location, $industry, $types, $source, $monthsAgo, $host]) {
            $created = $today->copy()->subMonthsNoOverflow($monthsAgo)->subDays($monthsAgo ? 3 : $n - 4)->setTime(11, 0);
            $client = Client::create([
                'name' => $name, 'contact_name' => $contact, 'location' => $location,
                'email' => 'hello@'.str($name)->slug('').'.test', 'phone1' => '+91 98765 4'.str_pad((string) ($n * 1111), 4, '0', STR_PAD_LEFT),
                'website' => $host ? 'https://'.$host[0] : null, 'billing_address' => "12 MG Road\n{$location}",
                'industry_id' => $id('industry', $industry), 'join_date' => $created->toDateString(), 'status' => $n === 4 ? 'on_hold' : 'active',
                'account_manager_id' => $n % 2 ? $staff->id : $partner->id, 'acquisition_source_id' => $id('acquisition_source', $source),
                'referrer_id' => $source === 'Referral' ? Lookup::where('type', 'referrer')->value('id') : null,
                'tax_id' => $n < 3 ? '27ABCDE'.(1000 + $n).'F1Z5' : null, 'preferred_channel' => ['email', 'whatsapp', 'phone'][$n % 3],
                'created_by' => $staff->id, 'notes' => $n === 0 ? 'Prefers WhatsApp for approvals. Festival campaigns every quarter.' : null,
            ]);
            $client->forceFill(['created_at' => $created->copy()->utc()])->saveQuietly();
            $client->workTypes()->sync(Lookup::where('type', 'work_type')->whereIn('name', $types)->pluck('id'));

            if ($host) {
                $due = $today->copy()->addDays($host[2]);
                $record = HostingRecord::create([
                    'client_id' => $client->id, 'domain' => $host[0],
                    'server_type_id' => $id('server_type', $host[3] > 0 ? 'Paid' : 'Self-managed'),
                    'server_provider_id' => $id('server_provider', $host[1]),
                    'billing_start' => $due->copy()->subYear()->toDateString(), 'billing_end' => $due->copy()->subDay()->toDateString(),
                    'next_payment_date' => $due->toDateString(), 'renewal_amount' => $host[3], 'billing_frequency' => 'yearly',
                ]);
                if ($n === 2) {
                    $record->renewals()->create([
                        'period_start' => $due->copy()->subYears(2), 'period_end' => $due->copy()->subYear()->subDay(), 'payment_due_date' => $due->copy()->subYear(),
                        'paid_on' => $due->copy()->subYear()->subDays(2), 'amount' => 7800, 'next_payment_date' => $due, 'recorded_by' => $admin->id,
                    ]);
                }
            }
            $clients[] = $client;
        }

        // Invoices: [client index, days ago, planned offset, status, items]
        $invoice = function (Client $client, int $daysAgo, ?int $plannedIn, bool $send, array $items, int $dueIn = 15) use ($today, $admin) {
            $date = $today->copy()->subDays($daysAgo);
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(), 'client_id' => $client->id, 'invoice_date' => $date->toDateString(),
                'planned_send_date' => $plannedIn === null ? $date->toDateString() : $today->copy()->addDays($plannedIn)->toDateString(),
                'due_date' => $date->copy()->addDays($dueIn)->toDateString(), 'currency' => 'INR', 'tax_rate' => 18, 'created_by' => $admin->id,
            ]);
            foreach ($items as $i => [$description, $qty, $price]) {
                $invoice->items()->create(['description' => $description, 'quantity' => $qty, 'unit_price' => $price, 'amount' => $qty * $price, 'sort_order' => $i]);
            }
            $invoice->recalculateTotals();
            if ($send) {
                $invoice->markSent($date->copy()->setTime(6, 0)->toDateTimeString());
            }

            return $invoice;
        };

        $a = $invoice($clients[0], 40, null, true, [['Restaurant website redesign — milestone 2', 1, 45000], ['Menu photography edits', 6, 1500]]);
        $b = $invoice($clients[1], 12, null, true, [['SEO retainer — October', 1, 18000], ['Landing page: Baner project', 1, 12000]]);
        $c = $invoice($clients[2], 5, null, true, [['Website maintenance — Q4', 1, 9000]]);
        $invoice($clients[3], 1, 0, false, [['LMS setup and theme customisation', 1, 85000], ['Google Ads setup', 1, 10000]]);
        $invoice($clients[6], 0, 6, false, [['Brand identity package', 1, 35000]]);
        $invoice($clients[4], 2, -1, false, [['Social media management — November', 1, 15000]]);

        $pay = function (Client $client, float $amount, int $daysAgo, string $method, User $to, ?string $ref, array $allocations = []) use ($today, $id, $admin) {
            $payment = Payment::create([
                'client_id' => $client->id, 'payment_method_id' => $id('payment_method', $method), 'amount' => $amount,
                'payment_date' => $today->copy()->subDays($daysAgo)->toDateString(), 'received_by' => $to->id, 'reference' => $ref, 'recorded_by' => $admin->id,
            ]);
            $payment->syncAllocations($allocations);
        };
        $pay($clients[0], 30000, 30, 'Bank Transfer (NEFT/IMPS)', $admin, 'NEFT-AXIS-883201', [$a->id => 30000]);
        $pay($clients[1], (float) $b->total, 6, 'UPI', $partner, 'UPI-4471209931', [$b->id => (float) $b->total]);
        $pay($clients[2], 5000, 2, 'UPI', $admin, 'UPI-5520018842', [$c->id => 3000]);

        $expenses = [
            [0, 1299, 'Software Subscriptions', 'Figma', 'Credit / Debit Card', 'Professional plan — 1 editor', null, 'monthly'],
            [1, 5400, 'Hosting & Domains', 'Hostinger', 'Credit / Debit Card', 'Business hosting renewal — saffronkitchen.in', 0, null],
            [2, 12500, 'Advertising', 'Meta Ads', 'Credit / Debit Card', 'Bluepeak lead campaign top-up', 1, null],
            [3, 8000, 'Contractors', 'Ishaan (freelance developer)', 'UPI', 'WooCommerce checkout fixes', 4, null],
            [5, 2360, 'Software Subscriptions', 'Google Workspace', 'Credit / Debit Card', '4 seats', null, 'monthly'],
            [6, 1850, 'Office', 'Urban Co-work', 'UPI', 'Meeting room — client workshop', null, null],
            [0, 640, 'Travel', 'Uber', 'UPI', 'Client visit — Lotus Dental', 2, null],
            [8, 18000, 'Office', 'Skyline Properties', 'Bank Transfer (NEFT/IMPS)', 'Office rent', null, 'monthly'],
            [35, 1299, 'Software Subscriptions', 'Figma', 'Credit / Debit Card', 'Professional plan — 1 editor', null, 'monthly'],
            [38, 18000, 'Office', 'Skyline Properties', 'Bank Transfer (NEFT/IMPS)', 'Office rent', null, 'monthly'],
            [64, 9900, 'Advertising', 'Google Ads', 'Credit / Debit Card', 'Agency brand campaign', null, null],
        ];
        foreach ($expenses as [$daysAgo, $amount, $category, $payee, $method, $description, $clientIndex, $frequency]) {
            Expense::create([
                'expense_date' => $today->copy()->subDays($daysAgo)->toDateString(), 'amount' => $amount, 'category_id' => $id('expense_category', $category),
                'payee' => $payee, 'payment_method_id' => $id('payment_method', $method), 'description' => $description,
                'client_id' => $clientIndex === null ? null : $clients[$clientIndex]->id,
                'is_recurring' => $frequency !== null, 'recurring_frequency' => $frequency, 'created_by' => $admin->id,
            ]);
        }

        $tasks = [
            [0, 'Send Diwali campaign creatives for approval', $staff, -3, 'in_progress'],
            [1, 'Publish Baner landing page', $staff, 2, 'todo'],
            [1, 'Monthly SEO report', $partner, 9, 'todo'],
            [2, 'Renew SSL certificate', $staff, -1, 'todo'],
            [3, 'Kick-off call and content checklist', $admin, 5, 'todo'],
            [6, 'Share logo concepts (3 routes)', $partner, 12, 'in_progress'],
            [0, 'Update menu prices on website', $staff, -10, 'done'],
        ];
        foreach ($tasks as [$clientIndex, $title, $assignee, $dueIn, $status]) {
            Task::create(['client_id' => $clients[$clientIndex]->id, 'title' => $title, 'assignee_id' => $assignee->id, 'due_date' => $today->copy()->addDays($dueIn)->toDateString(), 'status' => $status, 'created_by' => $admin->id]);
        }

        if (function_exists('imagecreatetruecolor')) {
            $posters = [
                ['Diwali Feast Offer', 'instagram', 0, 'Festive 2026', 'Celebrate with our Diwali thali — pre-book now.', 'diwali, offer, food', [196, 86, 28]],
                ['2BHK Launch — Baner', 'facebook', 1, 'Baner Launch', 'Possession-ready homes from ₹78L.', 'launch, realestate', [22, 78, 140]],
                ['Free Dental Check-up Camp', 'instagram', 2, null, 'Saturday, 10am–4pm. Walk in.', 'health, camp', [20, 130, 120]],
                ['Admissions Open 2027', 'linkedin', 3, 'Admissions', 'Apply before 15 January.', 'education, admissions', [92, 60, 150]],
                ['We are hiring: Designer', 'linkedin', null, 'Agency', 'Join our studio.', 'hiring, agency', [35, 45, 55]],
                ['Organic Honey — New Batch', 'whatsapp', 4, null, 'Fresh stock, limited jars.', 'organic, product', [176, 130, 20]],
            ];
            foreach ($posters as $n => [$title, $platform, $clientIndex, $campaign, $caption, $tags, $rgb]) {
                $image = imagecreatetruecolor(1080, 1080);
                imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
                $light = imagecolorallocatealpha($image, 255, 255, 255, 96);
                imagefilledellipse($image, 860, 220, 520, 520, $light);
                imagefilledrectangle($image, 90, 760, 990, 780, imagecolorallocate($image, 255, 255, 255));
                $white = imagecolorallocate($image, 255, 255, 255);
                foreach (str_split(strtoupper($title), 18) as $line => $text) {
                    imagestring($image, 5, 92, 800 + $line * 26, $text, $white);
                }
                ob_start();
                imagepng($image);
                $bytes = ob_get_clean();
                imagedestroy($image);

                $path = 'posters/demo-'.($n + 1).'.png';
                Storage::disk('local')->put($path, $bytes);
                $thumbPath = 'posters/thumbs/demo-'.($n + 1).'.jpg';
                Storage::disk('local')->put($thumbPath, ImageThumb::make(Storage::disk('local')->path($path)));

                Poster::create([
                    'title' => $title, 'platform' => $platform, 'client_id' => $clientIndex === null ? null : $clients[$clientIndex]->id,
                    'campaign' => $campaign, 'caption' => $caption, 'tags' => $tags, 'image_path' => $path, 'thumb_path' => $thumbPath,
                    'original_name' => str($title)->slug().'.png', 'mime' => 'image/png', 'size' => strlen($bytes), 'uploaded_by' => $staff->id,
                ]);
            }
        }

        // Produce the reminders that are already due so the notification center has content.
        app(ReminderService::class)->run();
    }
}
