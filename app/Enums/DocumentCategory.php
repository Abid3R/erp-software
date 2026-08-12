<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Invoice = 'invoice';
    case PurchaseOrder = 'purchase_order';
    case Quotation = 'quotation';
    case DeliveryChallan = 'delivery_challan';
    case Certificate = 'certificate';
    case LetterOfCredit = 'letter_of_credit';
    case Shipping = 'shipping';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LetterOfCredit => 'Letter of Credit (L/C)',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Contract, self::LetterOfCredit => 'warning',
            self::Invoice, self::PurchaseOrder, self::Quotation => 'info',
            self::Certificate => 'success',
            self::Shipping, self::DeliveryChallan => 'primary',
            self::Other => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
