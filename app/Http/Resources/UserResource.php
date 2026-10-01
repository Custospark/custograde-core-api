<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'account_type' => $this->account_type,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'institution_id' => $this->institution_id,
            'institution_name' => $this->whenLoaded('institution', fn () => $this->institution?->name),
            'institution' => $this->whenLoaded('institution', function () {
                return $this->institution ? [
                    'id' => $this->institution->id,
                    'name' => $this->institution->name,
                    'type' => $this->institution->type,
                    'status' => $this->institution->status,
                ] : null;
            }),
        ];
    }
}
