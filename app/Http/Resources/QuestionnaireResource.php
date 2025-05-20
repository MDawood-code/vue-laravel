<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class QuestionnaireResource extends JsonResource
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
            'learning_source' => [
                'id' => $this->learning_source_id,
                'source' => $this->learningSource->source,
            ],
            'other_learning_source' => $this->other_learning_source,
            'preferred_platform' => $this->preferred_platform,
            'new_or_existing' => $this->new_or_existing,
        ];
    }
}
