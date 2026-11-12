<?php

namespace App\Livewire\Concerns;

use App\Models\Quote;
use Illuminate\Validation\Rule;

/**
 * Section and line-item editing shared by the template editor and the quote editor:
 * add, remove, reorder and hide sections; add, remove and reorder line items.
 */
trait EditsQuoteContent
{
    /** @var array<int, array{id:?int, title:string, body:string, is_hidden:bool}> */
    public array $sections = [];

    /** @var array<int, array{id:?int, name:string, description:string, quantity:string, unit_price:string, billing_frequency:string}> */
    public array $items = [];

    public function addSection(string $title = ''): void
    {
        $this->sections[] = ['id' => null, 'title' => $title, 'body' => '', 'is_hidden' => false];
    }

    public function removeSection(int $index): void
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
    }

    public function moveSection(int $index, int $direction): void
    {
        $this->sections = $this->swap($this->sections, $index, $index + $direction);
    }

    public function addItem(): void
    {
        $this->items[] = ['id' => null, 'name' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '', 'billing_frequency' => 'one_time'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function moveItem(int $index, int $direction): void
    {
        $this->items = $this->swap($this->items, $index, $index + $direction);
    }

    protected function swap(array $list, int $a, int $b): array
    {
        if (isset($list[$a], $list[$b])) {
            [$list[$a], $list[$b]] = [$list[$b], $list[$a]];
        }

        return array_values($list);
    }

    protected function contentRules(): array
    {
        return [
            'sections' => ['array', 'max:40'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['nullable', 'string', 'max:40000'],
            'sections.*.is_hidden' => ['boolean'],
            'items' => ['array', 'max:60'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.billing_frequency' => ['required', Rule::in(array_keys(Quote::FREQUENCIES))],
        ];
    }

    protected function contentAttributes(): array
    {
        return [
            'sections.*.title' => 'section title', 'sections.*.body' => 'section text',
            'items.*.name' => 'item name', 'items.*.description' => 'item description', 'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price', 'items.*.billing_frequency' => 'billing frequency',
        ];
    }
}
