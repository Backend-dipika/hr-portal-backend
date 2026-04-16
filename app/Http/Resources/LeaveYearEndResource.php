<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveYearEndResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id ?? "",
            'user_id' => $this->user_id ?? "",
            'year' => $this->year ?? "",
            'action_type' => $this->action_type ?? "",
            'days' => $this->days ?? "",
            'processed_on' => $this->processed_on ?? "",
            'remarks' => $this->remarks ?? "",
            'status' => $this->status ?? "",
            'approver_id' => $this->approver_id ?? "",
            'approval_date' => $this->approval_date ?? "",
            'is_closed' => $this->is_closed ?? "",
            'created_at' => $this->created_at ?? "",
            'updated_at' => $this->updated_at ?? "",

            // ✅ User relation (handle null safely)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id ?? "",
                    'first_name' => $this->user->first_name ?? "",
                    'last_name' => $this->user->last_name ?? "",

                    'department' => [
                        'id' => $this->user->department->id ?? "",
                        'name' => $this->user->department->name ?? "",
                    ],

                    'designation' => [
                        'id' => $this->user->designation->id ?? "",
                        'name' => $this->user->designation->name ?? "",
                    ],
                ];
            }),
        ];
    }
    // public function toArray(Request $request): array
    // {
    //     return parent::toArray($request);
    // }
}
