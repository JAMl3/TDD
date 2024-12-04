<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'budget' => $this->budget,
            'deadline' => $this->deadline,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'skills' => $this->skills->map(fn($skill) => [
                'id' => $skill->id,
                'name' => $skill->name
            ]),
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->name
            ],
            'application_count' => $this->applications_count
        ];
    }
} 