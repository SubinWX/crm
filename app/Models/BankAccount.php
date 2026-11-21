<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One of the company's bank accounts. The account number is encrypted in the database. */
class BankAccount extends Model
{
    public const TYPES = ['current' => 'Current', 'savings' => 'Savings', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['account_number' => 'encrypted', 'is_default' => 'boolean'];

    /** @return array<string, string> the details in the order they are shown and copied; empty ones are left out */
    public function details(): array
    {
        return array_filter([
            'Account name' => $this->account_name,
            'Bank' => $this->bank_name,
            'Account number' => $this->account_number,
            'IFSC code' => $this->ifsc,
            'Branch' => $this->branch,
            'Account type' => self::TYPES[$this->account_type] ?? null,
            'SWIFT code' => $this->swift,
            'UPI ID' => $this->upi_id,
        ], fn ($value) => filled($value));
    }

    /** Everything as plain text, one detail per line, ready to paste into an email or WhatsApp message. */
    public function asText(): string
    {
        return collect($this->details())->map(fn ($value, $label) => $label.': '.$value)->implode("\n");
    }
}
