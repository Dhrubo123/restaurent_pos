<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

enum PosDocumentNumber: string
{
    case KitchenTicket = 'KOT';
    case Invoice = 'INV';
    case Refund = 'REF';

    public function next(int $restaurantId): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A document number must be allocated in the document transaction.');
        }

        $key = ['restaurant_id' => $restaurantId, 'prefix' => $this->value];
        $counter = DB::table('pos_document_counters')->where($key);

        // The upsert serializes concurrent first use and preserves any existing counter.
        DB::table('pos_document_counters')->upsert(
            [$key + ['last_number' => 0]],
            ['restaurant_id', 'prefix'],
            ['prefix'],
        );
        $last = (int) $counter->lockForUpdate()->value('last_number');
        $next = ($last === 0 ? $this->highestExistingNumber($restaurantId) : $last) + 1;
        $counter->update(['last_number' => $next]);

        return sprintf('%s-%04d', $this->value, $next);
    }

    private function highestExistingNumber(int $restaurantId): int
    {
        [$table, $column] = match ($this) {
            self::KitchenTicket => ['kots', 'kot_number'],
            self::Invoice => ['invoices', 'invoice_number'],
            self::Refund => ['refunds', 'refund_number'],
        };

        $prefix = $this->value.'-';
        $highest = 0;
        $documents = DB::table($table)
            ->where('restaurant_id', $restaurantId)
            ->whereLike($column, $prefix.'%')
            ->select(['id', $column])
            ->lazyById();

        foreach ($documents as $document) {
            $suffix = substr($document->{$column}, strlen($prefix));
            if (ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest;
    }
}
