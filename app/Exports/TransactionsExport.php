<?php

namespace App\Exports;

use App\Models\Transaction;
use App\Models\TransactionMultipayment;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Override;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * @implements WithMapping<Transaction>
 */
class TransactionsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return array<int, string>
     */
    #[Override]
    public function headings(): array
    {
        return [
            'Transaction ID',
            'Amount',
            'VAT on Sale',
            'Branch',
            'Type',
            'Status',
            'User',
            'Created At',
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    #[Override]
    public function collection(): Collection
    {
        return (new TransactionService)->index(request())->get();
    }

    /**
     * @param  Transaction  $transaction
     * @return array<mixed>
     */
    #[Override]
    public function map($transaction): array
    {
        return [
            $transaction->uid,
            $transaction->type == TRANSACTION_TYPE_MULTIPAYMENT ? $transaction->multipayments->map(fn (TransactionMultipayment $transactionMultipayment): string => getTransactionTypeText($transactionMultipayment->transaction_type).': '.$transactionMultipayment->amount) : $transaction->amount_charged,
            $transaction->tax,
            $transaction->branch->name,
            getTransactionTypeText($transaction->type),
            $transaction->referenceTransaction ? $transaction->referenceTransaction->uid : 'Paid',
            $transaction->user->name,
            // Date::dateTimeToExcel($transaction->created_at),
            $transaction->created_at->tz('Asia/Riyadh'),
        ];
    }
}
