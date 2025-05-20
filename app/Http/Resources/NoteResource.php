<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class NoteResource extends JsonResource
{
    public function __construct(public $resource, public bool $shouldReturnComments = false) {}

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
            'description' => $this->description,
            'company_id' => $this->company_id,
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->createdByUser?->name,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'comments_count' => $this->comments_count,
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
