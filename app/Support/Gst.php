<?php

namespace App\Support;

/**
 * Indian GST rules used on invoices and in the monthly GST summary.
 *
 * GST mode is on when the company's state is set in Settings → General. A sale to a client
 * in the same state is split into CGST and SGST (half each); a sale to another state, or
 * outside India, carries IGST for the whole amount.
 */
class Gst
{
    /** Outside India (exports). */
    public const FOREIGN = '96';

    /** GST state codes: the first two digits of every GSTIN. */
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
        '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
        '24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory', self::FOREIGN => 'Outside India (export)',
    ];

    public static function enabled(): bool
    {
        return self::companyState() !== null;
    }

    public static function companyState(): ?string
    {
        $state = (string) setting('company_state');

        return array_key_exists($state, self::STATES) ? $state : null;
    }

    public static function stateName(?string $code): string
    {
        return $code && isset(self::STATES[$code]) ? self::STATES[$code].($code === self::FOREIGN ? '' : " ({$code})") : '—';
    }

    /** 15 characters: state code, PAN, entity number, "Z", check character. */
    public static function validGstin(?string $value): bool
    {
        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper(trim((string) $value)));
    }

    /** The state a GSTIN was issued in, or null when it is not a GSTIN. */
    public static function stateFromGstin(?string $value): ?string
    {
        $code = substr(strtoupper(trim((string) $value)), 0, 2);

        return self::validGstin($value) && isset(self::STATES[$code]) ? $code : null;
    }

    /** intra (CGST + SGST), inter (IGST) or none (GST not set up, or no place of supply). */
    public static function kind(?string $supplierState, ?string $placeOfSupply): string
    {
        if (! $supplierState || ! $placeOfSupply) {
            return 'none';
        }

        return $supplierState === $placeOfSupply ? 'intra' : 'inter';
    }

    /**
     * Split a tax amount. CGST takes the rounded half and SGST the remainder, so the two
     * always add back to the tax exactly.
     *
     * @return array{cgst: float, sgst: float, igst: float}
     */
    public static function split(float $tax, ?string $supplierState, ?string $placeOfSupply): array
    {
        return match (self::kind($supplierState, $placeOfSupply)) {
            'intra' => ['cgst' => $half = round($tax / 2, 2), 'sgst' => round($tax - $half, 2), 'igst' => 0.0],
            'inter' => ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => round($tax, 2)],
            default => ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0],
        };
    }

    /** "Rupees Eleven Thousand Eight Hundred Only", in the Indian lakh/crore system. */
    public static function amountInWords(float $amount, string $currency = 'INR'): string
    {
        $amount = round($amount, 2);
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);
        [$major, $minor] = ['INR' => ['Rupees', 'Paise'], 'USD' => ['Dollars', 'Cents'], 'EUR' => ['Euros', 'Cents'], 'GBP' => ['Pounds', 'Pence'], 'AED' => ['Dirhams', 'Fils']][$currency] ?? [$currency, 'Hundredths'];

        return trim($major.' '.self::words($whole).($fraction ? ' and '.self::words($fraction).' '.$minor : '').' Only');
    }

    protected static function words(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $below100 = fn (int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        $below1000 = fn (int $n) => trim(($n >= 100 ? $ones[intdiv($n, 100)].' Hundred ' : '').$below100($n % 100));

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $name]) {
            if ($number >= $unit) {
                // Crores can run past 99, so they are spelled out in full themselves.
                $parts[] = ($unit === 10000000 ? self::words(intdiv($number, $unit)) : $below100(intdiv($number, $unit))).' '.$name;
                $number %= $unit;
            }
        }
        if ($number > 0) {
            $parts[] = $below1000($number);
        }

        return implode(' ', $parts);
    }
}
