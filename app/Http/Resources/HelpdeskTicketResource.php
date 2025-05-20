<?php

namespace App\Http\Resources;

use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin HelpdeskTicket **/
class HelpdeskTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $response_array = [
            'id' => $this->id,
            'description' => $this->description,
            'attachment' => $this->attachment ? asset($this->attachment) : null,
            'status' => $this->status,
            'created_by' => new CompanyUserResource($this->customer),
            'manage_by' => new CompanyUserResource($this->manageBy),
            'reseller_agent' => new CompanyUserResource($this->resellerAgent),
        ];

        if (user_is_admin_or_staff() || user_is_super_admin() || user_is_reseller()) {
            $response_array['support_agent'] = new AdminStaffResource($this->supportAgent);
            $response_array['issue_type'] = new IssueTypeResource($this->issueType);
            $response_array['issue_comment'] = $this->issue_comment;
            $response_array['status_updated_at'] = $this->status_updated_at;
        }

        return $response_array;
    }
}
