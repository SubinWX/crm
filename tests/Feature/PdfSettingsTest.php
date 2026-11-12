<?php

namespace Tests\Feature;

use App\Livewire\Settings\PdfSettings;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Support\InvoicePdf;
use App\Support\PdfDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PdfSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_pdf_shares_the_header_footer_and_style_set_in_pdf_settings(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        foreach (['company_name' => 'Acme Studio', 'company_address' => "12 Beach Road\nKozhikode", 'company_email' => 'hello@acme.example', 'company_phone' => '+91 98765 43210', 'invoice_footer' => 'Thank you for your business.'] as $key => $value) {
            Setting::put($key, $value);
        }
        $client = Client::create(['name' => 'Kerala Traders', 'billing_address' => 'Calicut']);
        $invoice = Invoice::create(['number' => 'INV-P-1', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-15', 'tax_rate' => 18, 'status' => 'sent']);
        $invoice->items()->create(['description' => 'Website design', 'quantity' => 1, 'unit_price' => 10000, 'amount' => 10000]);
        $invoice->recalculateTotals();
        $html = fn () => view('pdf.invoice', ['invoice' => $invoice->fresh()->load('client.contacts', 'items')])->render();

        // Defaults come from the company details: name, address and contact in the header, the invoice note in the footer, a page border and page numbers.
        $page = $html();
        foreach (['class="pdf-border"', 'class="pdf-header"', 'class="pdf-footer"', 'Acme Studio', '12 Beach Road, Kozhikode', 'hello@acme.example', 'Thank you for your business.', 'class="page"', 'Invoice INV-P-1', 'class="doc-title">INVOICE<', 'class="panel-head">Bill to<', 'Kerala Traders', 'Website design'] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        $this->assertSame(['12 Beach Road, Kozhikode', 'hello@acme.example  ·  +91 98765 43210'], PdfDesign::headerLines());
        $this->assertFalse(PdfDesign::hasOwnLogo());

        // The settings screen: limits that keep the header and footer inside their space, and safe files only.
        $settings = Livewire::actingAs($admin)->test(PdfSettings::class)->assertSee('PDF Settings')->assertSet('form.header_style', 'classic');
        $settings->set('form.header_details', "1\n2\n3\n4\n5")->set('form.footer_text', "a\nb\nc")->set('form.accent_color', 'blue')
            ->set('logo', UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'))->call('save')
            ->assertHasErrors(['form.header_details', 'form.footer_text', 'form.accent_color', 'logo']);
        $settings->set('logo', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'))->call('save')->assertHasErrors('logo');

        $settings->set('form.header_style', 'band')->set('form.header_title', 'Acme Studio Pvt Ltd')->set('form.header_tagline', 'Web Design · Marketing')
            ->set('form.header_details', "Hilite Business Park\nGSTIN 32AAACA1234A1Z5")->set('form.footer_text', "Payment due in 15 days.\nBank: Sample Bank 000111")
            ->set('form.footer_contact', 'www.acme.example')->set('form.page_numbers', '0')->set('form.page_border', '0')->set('form.table_style', 'lines')->set('form.accent_color', '#0055AA')
            ->set('logo', UploadedFile::fake()->image('pdf-logo.png', 400, 100))->call('save')->assertHasNoErrors();

        $this->assertTrue(PdfDesign::hasOwnLogo());
        $this->assertSame(['#0055aa', '#ffffff'], [PdfDesign::accent(), PdfDesign::onAccent()]);
        $logo = PdfDesign::logo(170, 50);
        $this->assertSame([170, 43], [$logo['width'], $logo['height']]); // scaled to fit, keeping its shape

        $page = $html();
        foreach (['Acme Studio Pvt Ltd', 'Web Design · Marketing', 'Hilite Business Park', 'Payment due in 15 days.', 'Bank: Sample Bank 000111', 'www.acme.example', 'background: #0055aa; color: #ffffff;', 'data:image/png;base64,'] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        foreach (['class="pdf-border"', 'class="page"', '12 Beach Road', 'Thank you for your business.'] as $gone) {
            $this->assertStringNotContainsString($gone, $page);
        }

        // Quotes and reports use the same frame.
        $report = view('pdf.report', ['title' => 'Expenses', 'criteria' => 'All dates', 'columns' => [['Date', ''], ['Amount', 'num']], 'rows' => [['01 Nov 2026', '₹100.00']], 'totals' => ['Total' => '₹100.00'], 'note' => null])->render();
        foreach (['class="pdf-header"', 'class="pdf-footer"', 'Acme Studio Pvt Ltd', 'Payment due in 15 days.', 'class="items"', '₹100.00'] as $text) {
            $this->assertStringContainsString($text, $report);
        }

        // Real PDFs are produced, and the sample opens for Super Admins only.
        $this->assertStringStartsWith('%PDF', InvoicePdf::make($invoice->fresh())->output());
        $this->actingAs($admin)->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('invoices.export', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $sample = $this->actingAs($admin)->get(route('settings.pdf.sample'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $sample->getContent());
        $this->assertSame(0, Invoice::where('number', 'SAMPLE-0001')->count()); // the sample is never saved

        // Removing the logo and clearing the fields go back to the defaults.
        $old = setting('pdf_logo_path');
        Livewire::actingAs($admin)->test(PdfSettings::class)->assertSet('form.header_title', 'Acme Studio Pvt Ltd')->call('removeLogo')
            ->set('form.header_title', '')->set('form.header_details', '')->set('form.footer_text', '')->set('form.accent_color', '')->set('form.page_border', '1')->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $page = $html();
        $this->assertStringContainsString('12 Beach Road, Kozhikode', $page);
        $this->assertStringContainsString('class="pdf-border"', $page);
        $this->assertStringNotContainsString('#0055aa', $page);

        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['finance.manage']]);
        $this->actingAs($staff)->get(route('settings.pdf'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.pdf.sample'))->assertForbidden();
        Livewire::actingAs($staff)->test(PdfSettings::class)->assertForbidden();
    }
}
