<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use App\Http\Resources\Customer\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Transaction **/
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'order_id' => $this->order_id,
            'items' => TransactionItemResource::collection($this->items),
            'cash_collected' => $this->cash_collected,
            'amount_charged' => $this->amount_charged,
            'type' => $this->type,
            'payment_source' => $this->payment_source,
            'tax' => $this->tax,
            'tip' => $this->tip,
            'discount_amount' => $this->discount_amount,
            'discount' => $this->discount_id ? new DiscountResource($this->discount) : null,
            'is_refunded' => $this->is_refunded,
            'status' => new EnumResource($this->status),
            'order_source' => new EnumResource($this->order_source),
            'dining_table' => new DiningTableResource($this->diningTable),
            'branch' => $this->branch->name ?? '-',
            'reference' => $this->reference,
            'payment_channel' => $this->payment_channel,
            'buyer_company_name' => $this->buyer_company_name,
            'buyer_company_vat' => $this->buyer_company_vat,
            'customer_name' => $this->customer_name,
            'vehicle_number' => $this->vehicle_number,
            'vehicle_color' => $this->vehicle_color,
            'refund_transactions' => TransactionResourceWithoutReference::collection($this->refundTransactions),
            'reference_transaction' => $this->referenceTransaction
                ? new TransactionResourceWithoutReference($this->referenceTransaction) : null,
            'company_id' => $this->company_id,
            'user' => $this->user?->name,
            'created_at' => $this->created_at,
            'is_multipayment' => $this->type == TRANSACTION_TYPE_MULTIPAYMENT ? BOOLEAN_TRUE : BOOLEAN_FALSE,
            'multipayments' => TransactionMultipaymentResource::collection($this->multipayments),
            'odoo_reference_id' => $this->odoo_reference_number,
            'waiter' => new UserNameResource($this->waiter),
            // 'odoo_status' => $this->created_at->isBefore($this->company->OdooIntegration()->created_at) ? null : $this->odoo_reference_number,
            // 'customer' => new CustomerResource($this->customer),
            'customer' => $this->customer ? new CustomerResource($this->customer) : null,

        ];
    }
}
