<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\QuoteTemplate;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Starter data: default settings and dropdown values. Safe to run more than once.
 * Users are not seeded: create the first Super Admin with `php artisan crm:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public const LOOKUPS = [
        'work_type' => ['Web Development', 'Website Maintenance', 'Hosting', 'SEO', 'Digital Marketing', 'Social Media Management', 'Branding & Design'],
        'industry' => ['Retail & E-commerce', 'Healthcare', 'Education', 'Real Estate', 'Hospitality & Travel', 'Manufacturing', 'Professional Services', 'Technology', 'Non-profit', 'Other'],
        'acquisition_source' => ['Referral', 'Website Enquiry', 'Google Search', 'Social Media', 'Cold Outreach', 'Existing Client', 'Event / Networking', 'Other'],
        'referrer' => [],
        'server_type' => ['Paid', 'Self-managed'],
        'server_provider' => ['Hostinger — Business', 'Hostinger — Cloud Startup', 'GoDaddy — Deluxe', 'DigitalOcean — Droplet', 'AWS Lightsail', 'Bluehost — Plus', 'Client-owned server'],
        'payment_method' => ['Bank Transfer (NEFT/IMPS)', 'UPI', 'Cash', 'Cheque', 'Credit / Debit Card', 'Payment Gateway', 'Other'],
        'equipment_type' => ['Laptop', 'Desktop', 'Monitor', 'Mobile Phone', 'Tablet', 'Keyboard & Mouse', 'Headset', 'Camera', 'Furniture', 'Software Licence', 'Other'],
        'expense_category' => ['Hosting & Domains', 'Software Subscriptions', 'Advertising', 'Contractors', 'Office', 'Travel', 'Other'],
    ];

    public function run(): void
    {
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        Setting::flush();

        foreach (self::LOOKUPS as $type => $names) {
            foreach ($names as $index => $name) {
                Lookup::firstOrCreate(
                    ['type' => $type, 'name' => $name],
                    ['sort_order' => $index + 1, 'is_paid' => $type === 'server_type' && $name === 'Paid']
                );
            }
        }

        $this->seedStarterQuoteTemplates();
    }

    /**
     * Starter quote templates, added once on a fresh install. The wording is placeholder text,
     * clearly marked, for the agency to replace in Settings → Quote Templates.
     */
    protected function seedStarterQuoteTemplates(): void
    {
        if (QuoteTemplate::exists()) {
            return;
        }

        $templates = [
            ['Website design and development', 'Web Development', 30, [
                ['Website design and build', 'Custom responsive website, up to 8 pages', 1, 60000, 'one_time'],
                ['Content upload and launch support', null, 1, 8000, 'one_time'],
            ]],
            ['Managed hosting', 'Hosting', 30, [['Managed hosting plan', 'Hosting, SSL and daily backups', 1, 6500, 'yearly']]],
            ['Website maintenance', 'Website Maintenance', 30, [['Monthly maintenance', 'Updates, security checks and up to 2 hours of changes', 1, 3500, 'monthly']]],
            ['SEO retainer', 'SEO', 30, [
                ['SEO setup and audit', 'Technical audit, keyword plan and on-page fixes', 1, 15000, 'one_time'],
                ['Monthly SEO', 'Content, link building and reporting', 1, 12000, 'monthly'],
            ]],
            ['Social media management', 'Social Media Management', 30, [['Monthly social media management', '12 posts a month across 2 platforms, with posters', 1, 15000, 'monthly']]],
        ];

        foreach ($templates as [$name, $category, $validity, $items]) {
            $template = QuoteTemplate::create([
                'name' => $name, 'category' => $category, 'validity_days' => $validity,
                'description' => 'Starter template. Replace the placeholder wording and prices with your own.',
            ]);
            foreach (QuoteTemplate::STARTER_SECTIONS as $order => $title) {
                $template->sections()->create([
                    'title' => $title, 'sort_order' => $order,
                    'body' => "[Starter text — replace with your own {$title} wording for {$name}]",
                ]);
            }
            foreach ($items as $order => [$itemName, $description, $quantity, $price, $frequency]) {
                $template->items()->create(['name' => $itemName, 'description' => $description, 'quantity' => $quantity, 'unit_price' => $price, 'billing_frequency' => $frequency, 'sort_order' => $order]);
            }
        }
    }
}
