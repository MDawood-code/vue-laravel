<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use App\Http\Resources\Customer\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Transaction **/
class SaleInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'items' => TransactionItemResource::collection($this->items),
            'branch' => $this->branch->name ?? '-',
            'company_id' => $this->company_id,
            'invoice_number' => $this->invoice_number ?? null,
            'amount_charged' => $this->amount_charged ?? null,
            'cash_collected' => $this->cash_collected ?? null,
            'tax' => $this->tax ?? null,
            'invoice_due_date' => $this->invoice_due_date ? $this->invoice_due_date->toISOString() : null,
            'create_invoice_date' => $this->create_invoice_date ? $this->create_invoice_date->toISOString() : null,
            'sale_invoice_status' => $this->sale_invoice_status ?? null,
            'created_at' => $this->created_at,
            // 'create_invoice_date' => $this->create_invoice_date,
            'balance' => $this->calculateBalance(),
            'customer' => $this->customer ? new CustomerResource($this->customer) : null,
        ];
    
        // Conditionally add fields based on sale_invoice_status
        if ($this->sale_invoice_status == SALE_INVOICE_STATUS_PAID) {
            $data['uid'] = $this->uid;
            $data['refund_transactions'] = TransactionResourceWithoutReference::collection($this->refundTransactions);
            $data['reference_transaction'] =$this->referenceTransaction
                ? new TransactionResourceWithoutReference($this->referenceTransaction) : null;

        }
    
        return $data;
    }

    
    
}
