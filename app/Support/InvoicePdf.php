<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public static function make(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['client', 'items']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->setPaper('a4')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);
    }
}
