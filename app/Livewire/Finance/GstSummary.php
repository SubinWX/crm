<?php

namespace App\Livewire\Finance;

use App\Support\Gst;
use App\Support\GstReport;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Finance → GST Summary: one month's invoiced sales with the CGST/SGST/IGST split, for the accountant. */
#[Title('GST Summary')]
class GstSummary extends Component
{
    #[Url]
    public string $month = '';

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function mount(): void
    {
        if (! GstReport::validMonth($this->month)) {
            // Returns are filed for the month just ended, so that is the one to open with.
            $this->month = today_local()->subMonthNoOverflow()->format('Y-m');
        }
    }

    public function updatedMonth(): void
    {
        if (! GstReport::validMonth($this->month)) {
            $this->month = today_local()->format('Y-m');
        }
    }

    public function shift(int $months): void
    {
        $this->month = (new GstReport($this->month))->start->addMonthsNoOverflow($months)->format('Y-m');
    }

    public function render()
    {
        $report = new GstReport($this->month);

        return view('livewire.finance.gst-summary', [
            'report' => $report,
            'totals' => $report->totals(),
            'buyers' => $report->byBuyerType(),
            'rates' => $report->byRate(),
            'hsn' => $report->byHsn(),
            'gstOn' => Gst::enabled(),
            'unsplit' => $report->invoices->filter(fn ($i) => (float) $i->tax_amount > 0 && $i->gstKind() === 'none')->count(),
        ]);
    }
}
