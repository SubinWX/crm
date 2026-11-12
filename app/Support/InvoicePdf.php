<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    /**
     * A made-up invoice, never saved, for "View sample PDF" in PDF Settings. It shows the
     * header, footer, border and table style with realistic content.
     */
    public static function sample(): Invoice
    {
        $gst = Gst::enabled();
        $client = new Client(['name' => 'Sample Client Pvt Ltd', 'contact_name' => 'Anita Menon', 'billing_address' => "2nd Floor, Trade Centre\nMG Road, Kochi 682016", 'tax_id' => $gst ? '32AAACS1234A1Z5' : null]);
        $client->setRelation('contacts', collect());

        $invoice = new Invoice([
            'number' => 'SAMPLE-0001', 'invoice_date' => today_local()->toDateString(), 'due_date' => today_local()->addDays(15)->toDateString(),
            'currency' => setting('currency', 'INR'), 'status' => 'sent', 'tax_rate' => 18, 'discount' => 2000,
            'supplier_state' => Gst::companyState(), 'place_of_supply' => Gst::companyState(), 'client_gstin' => $gst ? '32AAACS1234A1Z5' : null,
            'notes' => '<ul><li>Payment within 15 days by bank transfer or UPI.</li><li>This is a sample to preview your PDF settings.</li></ul>',
        ]);
        $lines = [['Website design and development', '998314', 1, 45000], ['Annual hosting and maintenance', '998315', 1, 9000], ['Social media management (monthly)', '998361', 3, 6000]];
        $items = collect($lines)->map(fn ($l, $i) => new InvoiceItem(['description' => $l[0], 'hsn_sac' => $l[1], 'quantity' => $l[2], 'unit_price' => $l[3], 'amount' => $l[2] * $l[3], 'sort_order' => $i]));
        $subtotal = (float) $items->sum('amount');
        $tax = round(($subtotal - 2000) * 0.18, 2);
        $split = Gst::split($tax, $invoice->supplier_state, $invoice->place_of_supply);
        $invoice->forceFill(['subtotal' => $subtotal, 'tax_amount' => $tax, 'cgst_amount' => $split['cgst'], 'sgst_amount' => $split['sgst'], 'igst_amount' => $split['igst'], 'total' => $subtotal - 2000 + $tax, 'amount_paid' => 0]);
        $invoice->setRelation('client', $client)->setRelation('items', $items);

        return $invoice;
    }

    public static function make(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['client', 'items']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->setPaper('a4')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);
    }
}
