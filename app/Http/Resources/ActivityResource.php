<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ActivityResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'activity_type' => new ActivityTypeResource($this->activityType),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'reminder' => $this->reminder,
            'status' => $this->status,
            'company' => [
                'id' => $this->company_id,
                'name' => $this->company?->name,
            ],
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->createdByUser?->name,
            ],
            'assigned_to' => [
                'id' => $this->assigned_to,
                'name' => $this->assignedToUser?->name,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'comments_count' => $this->comments_count,
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
