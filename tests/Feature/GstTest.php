<?php

namespace Tests\Feature;

use App\Livewire\Clients\Form as ClientForm;
use App\Livewire\Finance\GstSummary;
use App\Livewire\Invoices\Form as InvoiceForm;
use App\Livewire\Settings\Index as Settings;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Support\Gst;
use App\Support\GstReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GstTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00');
        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function invoice(Client $client, array $lines, array $form = []): Invoice
    {
        $page = Livewire::actingAs($this->admin)->test(InvoiceForm::class)->set('form.client_id', (string) $client->id);
        foreach ($form as $key => $value) {
            $page->set("form.{$key}", $value);
        }
        foreach ($lines as $index => [$description, $hsn, $quantity, $price]) {
            if ($index > 0) {
                $page->call('addItem');
            }
            $page->set("items.{$index}.description", $description)->set("items.{$index}.hsn_sac", $hsn)->set("items.{$index}.quantity", (string) $quantity)->set("items.{$index}.unit_price", (string) $price);
        }
        $page->call('save')->assertHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_gst_rules(): void
    {
        $this->assertTrue(Gst::validGstin('32aaaca1234a1z5'));
        $this->assertFalse(Gst::validGstin('32AAACA1234A1X5'));
        $this->assertFalse(Gst::validGstin('ABC123'));
        $this->assertSame('32', Gst::stateFromGstin('32AAACA1234A1Z5'));
        $this->assertNull(Gst::stateFromGstin('99AAACA1234A1Z5'));

        $this->assertSame(['cgst' => 900.0, 'sgst' => 900.0, 'igst' => 0.0], Gst::split(1800, '32', '32'));
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 1800.0], Gst::split(1800, '32', '29'));
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 1800.0], Gst::split(1800, '32', Gst::FOREIGN));
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0], Gst::split(1800, null, '32'));
        // An odd paisa goes to CGST so the two halves always add back to the tax.
        $this->assertSame(['cgst' => 9.01, 'sgst' => 9.0, 'igst' => 0.0], Gst::split(18.01, '32', '32'));

        $this->assertSame('Rupees Eleven Thousand Eight Hundred Only', Gst::amountInWords(11800));
        $this->assertSame('Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven and Fifty Paise Only', Gst::amountInWords(1234567.50));
        $this->assertSame('Rupees Three Crore Five Only', Gst::amountInWords(30000005));
        $this->assertSame('Rupees Zero Only', Gst::amountInWords(0));
        $this->assertSame('Dollars Nineteen and Ninety Nine Cents Only', Gst::amountInWords(19.99, 'USD'));
    }

    public function test_invoices_split_tax_by_place_of_supply_and_feed_the_monthly_summary(): void
    {
        // Until the company's state is chosen, invoices behave exactly as before.
        $plainClient = Client::create(['name' => 'Before GST Co']);
        $plain = $this->invoice($plainClient, [['Old work', '', 1, 1000]]);
        $this->assertSame(['none', null, '180.00', '0.00'], [$plain->gstKind(), $plain->place_of_supply, $plain->tax_amount, $plain->cgst_amount]);
        $this->assertSame(['Tax (18%)' => 180.0], $plain->taxLines());
        $plain->update(['status' => 'sent']);

        // Switching GST on: the GSTIN must be a real one from the chosen state.
        $settings = Livewire::actingAs($this->admin)->test(Settings::class)->set('general.company_state', '32');
        $settings->set('general.company_tax_id', 'NOT-A-GSTIN')->call('saveGeneral')->assertHasErrors('general.company_tax_id');
        $settings->set('general.company_tax_id', '29AAACA1234A1Z5')->call('saveGeneral')->assertHasErrors('general.company_tax_id');
        $settings->set('general.company_tax_id', '32aaaca1234a1z5')->set('general.invoice_default_hsn', '99')->call('saveGeneral')->assertHasErrors('general.invoice_default_hsn');
        $settings->set('general.invoice_default_hsn', '998314')->call('saveGeneral')->assertHasNoErrors();
        Setting::flush();
        $this->assertSame(['32AAACA1234A1Z5', '32', true], [setting('company_tax_id'), Gst::companyState(), Gst::enabled()]);

        // Clients: a registered one in the same state, an unregistered one in another state, and one abroad.
        Livewire::actingAs($this->admin)->test(ClientForm::class)->set('form.name', 'Kerala Traders')->set('form.tax_id', '32BBBCB5678B1Z3')->call('save')->assertHasNoErrors();
        $kerala = Client::where('name', 'Kerala Traders')->firstOrFail();
        Livewire::actingAs($this->admin)->test(ClientForm::class)->set('form.name', 'Bengaluru Cafe')->set('form.state_code', '29')->call('save')->assertHasNoErrors();
        $karnataka = Client::where('name', 'Bengaluru Cafe')->firstOrFail();
        $this->assertSame('29', $karnataka->state_code);
        $abroad = Client::create(['name' => 'Dubai LLC', 'state_code' => Gst::FOREIGN]);

        // Same state: the place of supply comes from the GSTIN, new lines get the default SAC, and tax splits into CGST + SGST.
        $form = Livewire::actingAs($this->admin)->test(InvoiceForm::class)->assertSee('Place of supply')->assertSet('items.0.hsn_sac', '998314')
            ->set('form.client_id', (string) $kerala->id)->assertSet('form.place_of_supply', '32');
        $form->set('items.0.description', 'Website')->set('items.0.unit_price', '10000')->assertSee('CGST (9%)')->assertSee('SGST (9%)')
            ->set('form.place_of_supply', '29')->assertSee('IGST (18%)')->assertDontSee('CGST (9%)')
            ->set('form.place_of_supply', '')->call('save')->assertHasErrors('form.place_of_supply');
        $form->set('items.0.hsn_sac', '12')->set('form.place_of_supply', '32')->call('save')->assertHasErrors('items.0.hsn_sac');

        $intra = $this->invoice($kerala, [['Website design', '998314', 1, 10000], ['Hosting', '998315', 2, 1000]], ['discount' => '600']);
        $this->assertSame(['intra', '32', '32', '32BBBCB5678B1Z3'], [$intra->gstKind(), $intra->supplier_state, $intra->place_of_supply, $intra->client_gstin]);
        $this->assertSame(['11400.00', '2052.00', '1026.00', '1026.00', '0.00', '13452.00'], [number_format($intra->taxable_value, 2, '.', ''), $intra->tax_amount, $intra->cgst_amount, $intra->sgst_amount, $intra->igst_amount, $intra->total]);
        $this->assertSame(['CGST (9%)' => 1026.0, 'SGST (9%)' => 1026.0], $intra->taxLines());

        // Another state: IGST. Outside India: IGST too (the rate is the user's to set). An odd paisa stays consistent.
        $inter = $this->invoice($karnataka, [['SEO retainer', '998361', 1, 5000]]);
        $this->assertSame(['inter', '29', null, '900.00', '0.00'], [$inter->gstKind(), $inter->place_of_supply, $inter->client_gstin, $inter->igst_amount, $inter->cgst_amount]);
        $export = $this->invoice($abroad, [['App design', '998314', 1, 2000]], ['tax_rate' => '0']);
        $this->assertSame(['inter', Gst::FOREIGN, '0.00', '2000.00'], [$export->gstKind(), $export->place_of_supply, $export->igst_amount, $export->total]);
        $odd = $this->invoice($kerala, [['Small job', '998314', 1, 100.05]]);
        $this->assertSame(['18.01', '9.01', '9.00', '118.06'], [$odd->tax_amount, $odd->cgst_amount, $odd->sgst_amount, $odd->total]);

        // An invoice made without the form (for example from a quote) picks up the same details.
        $direct = Invoice::create(['number' => 'INV-DIRECT-1', 'client_id' => $karnataka->id, 'invoice_date' => '2026-11-10', 'tax_rate' => 18]);
        $direct->items()->create(['description' => 'From quote', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]);
        $direct->recalculateTotals();
        $this->assertSame(['32', '29', '180.00'], [$direct->supplier_state, $direct->place_of_supply, $direct->fresh()->igst_amount]);

        // The invoice page and the PDF show the split; a GST invoice is titled "Tax Invoice".
        $this->actingAs($this->admin)->get(route('invoices.show', $intra))->assertOk()->assertSee('CGST (9%)')->assertSee('Kerala (32)')->assertSee('32BBBCB5678B1Z3')->assertSee('998315');
        $this->actingAs($this->admin)->get(route('invoices.show', $inter))->assertOk()->assertSee('IGST (18%)')->assertSee('Unregistered (B2C)');
        $html = view('pdf.invoice', ['invoice' => $intra->load('client', 'items')])->render();
        foreach (['TAX INVOICE', 'Place of supply: Kerala (32)', 'GSTIN: 32BBBCB5678B1Z3', 'CGST (9%)', 'SGST (9%)', 'Taxable value', '998315', 'Rupees Thirteen Thousand Four Hundred Fifty Two Only'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('class="doc-title">INVOICE</h1>', view('pdf.invoice', ['invoice' => $plain->load('client', 'items')])->render());
        $this->actingAs($this->admin)->get(route('invoices.pdf', $intra))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Changing the company or client details later does not rewrite an invoice that already exists.
        $kerala->update(['tax_id' => null, 'state_code' => '27']);
        $this->assertSame(['32', '32BBBCB5678B1Z3'], [$intra->fresh()->place_of_supply, $intra->fresh()->client_gstin]);

        // Monthly summary: only issued invoices dated in the month. Drafts, voided and other months are left out.
        foreach ([$intra, $inter, $export, $odd] as $invoice) {
            $invoice->update(['status' => 'sent']);
        }
        $direct->update(['status' => 'void']);
        $lastMonth = $this->invoice($karnataka, [['October work', '998314', 1, 7000]], ['invoice_date' => '2026-10-20', 'due_date' => '2026-10-30']);
        $lastMonth->update(['status' => 'paid']);
        $draft = $this->invoice($karnataka, [['Not sent yet', '998314', 1, 9999]]);

        $report = new GstReport('2026-11');
        $this->assertSame([$plain->number, $intra->number, $inter->number, $export->number, $odd->number], $report->invoices->sortBy('id')->pluck('number')->all());
        $this->assertSame(['count' => 5, 'taxable' => 19500.05, 'cgst' => 1035.01, 'sgst' => 1035.0, 'igst' => 900.0, 'tax' => 3150.01, 'total' => 22650.06], $report->totals());
        $buyers = $report->byBuyerType();
        $this->assertSame([2, 11500.05, 1035.01], [$buyers['b2b']['count'], $buyers['b2b']['taxable'], $buyers['b2b']['cgst']]);
        $this->assertSame([3, 8000.0, 900.0], [$buyers['b2c']['count'], $buyers['b2c']['taxable'], $buyers['b2c']['igst']]);
        $this->assertSame([[0.0, 1, 2000.0], [18.0, 4, 17500.05]], $report->byRate()->map(fn ($r) => [$r['rate'], $r['count'], $r['taxable']])->all());

        // HSN rows add up to the invoice totals exactly, with the discount spread over the lines (10000:2000 of 600 = 500:100).
        $hsn = $report->byHsn();
        $this->assertSame(19500.05, round($hsn->sum('taxable'), 2));
        $this->assertSame(3150.01 - 180.0, round($hsn->sum('tax'), 2)); // the pre-GST invoice has tax but no split
        $this->assertSame(['hsn' => '998315', 'rate' => 18.0, 'quantity' => 2.0, 'taxable' => 1900.0, 'cgst' => 171.0, 'sgst' => 171.0, 'igst' => 0.0, 'tax' => 342.0], $hsn->firstWhere('hsn', '998315'));
        $design18 = $hsn->first(fn ($h) => $h['hsn'] === '998314' && $h['rate'] === 18.0);
        $this->assertSame([9600.05, 864.01, 864.0], [$design18['taxable'], $design18['cgst'], $design18['sgst']]);

        // The screen and the downloads.
        $page = Livewire::actingAs($this->admin)->withQueryParams(['month' => '2026-11'])->test(GstSummary::class);
        $page->assertSee('November 2026')->assertSee($intra->number)->assertSee('32BBBCB5678B1Z3')->assertSee('Karnataka (29)')->assertSee('₹1,035.01')
            ->assertSee('1 invoice has tax but no CGST/SGST/IGST split')->assertDontSee($draft->number)->assertDontSee('INV-DIRECT-1')->assertDontSee($lastMonth->number);
        $page->call('shift', -1)->assertSet('month', '2026-10')->assertSee($lastMonth->number)->assertDontSee($intra->number);
        Livewire::actingAs($this->admin)->withQueryParams(['month' => ''])->test(GstSummary::class)->assertSet('month', '2026-10'); // opens on the month just ended
        Livewire::actingAs($this->admin)->withQueryParams(['month' => 'nonsense'])->test(GstSummary::class)->assertSet('month', '2026-10');

        $csv = $this->actingAs($this->admin)->get(route('gst.export', ['month' => '2026-11', 'type' => 'invoices']))->assertOk()->streamedContent();
        $this->assertStringContainsString('gst-invoice-register-2026-11.csv', $this->actingAs($this->admin)->get(route('gst.export', ['month' => '2026-11']))->headers->get('content-disposition'));
        $this->assertStringContainsString("{$intra->number},\"Kerala Traders\",32BBBCB5678B1Z3,B2B,32-Kerala,18,11400.00,1026.00,1026.00,0.00,2052.00,13452.00,\"998314; 998315\"", $csv);
        $this->assertStringContainsString("{$inter->number},\"Bengaluru Cafe\",,B2C,29-Karnataka,18,5000.00,0.00,0.00,900.00,900.00,5900.00,998361", $csv);
        $this->assertStringNotContainsString($draft->number, $csv);
        $hsnCsv = $this->actingAs($this->admin)->get(route('gst.export', ['month' => '2026-11', 'type' => 'hsn']))->assertOk()->streamedContent();
        $this->assertStringContainsString('998315,18,2,1900.00,171.00,171.00,0.00,342.00', $hsnCsv);
        $this->actingAs($this->admin)->get(route('gst.export', ['month' => '2026-13']))->assertNotFound();

        // Finance only.
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['clients.manage']]);
        $this->actingAs($staff)->get(route('gst.summary'))->assertForbidden();
        $this->actingAs($staff)->get(route('gst.export', ['month' => '2026-11']))->assertForbidden();
        $this->actingAs($this->admin)->get(route('gst.summary'))->assertOk()->assertSee('GST Summary');
    }
}
