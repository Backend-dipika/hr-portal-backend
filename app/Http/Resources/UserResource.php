<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'salutation' => $this->salutation ?? '',
            'first_name' => $this->first_name ?? '',
            'middle_name' => $this->middle_name ?? '',
            'last_name' => $this->last_name ?? '',
            'profile_picture' => $this->profile_picture ?? '',
            'office_email' => $this->office_email ?? '',
            'phone_no' => $this->phone_no ?? '',
            'personal_email' => $this->personal_email ?? '',
            'alt_phone_no' => $this->alt_phone_no ?? '',
            'gender' => $this->gender ?? '',
            'date_of_birth' => $this->date_of_birth ?? '',
            'marital_status' => $this->marital_status ?? '',
            'blood_grp' => $this->blood_grp ?? '',
            'specially_abled' => $this->specially_abled ?? '',
            'about' => $this->about ?? '',
            'office_id' => $this->office_id ?? '',
            'date_of_joining' => $this->date_of_joining ?? '',
            'probation_end_date' => $this->probation_end_date ?? '',
            'sepration_status' => $this->sepration_status ?? '',
            'sepration_date' => $this->sepration_date ?? '',
            'current_location' => $this->current_location ?? '',
            'work_mode' => $this->work_mode ?? '',

            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department?->id,
                    'name' => $this->department?->name,
                ];
            }),


            'designation' => $this->whenLoaded('designation', function () {
                return [
                    'id' => $this->designation?->id,
                    'name' => $this->designation?->name,
                ];
            }),


            'addresses' => $this->whenLoaded('address', function () {
                return $this->address->map(function ($addr) {
                    return [
                        'id' => $addr->id,
                        'type' => $addr->type,
                        'address1' => $addr->address1,
                        'address2' => $addr->address2,
                        'city' => $addr->city,
                        'state' => $addr->state,
                        'pincode' => $addr->pincode,
                        'country' => $addr->country,
                    ];
                });
            }),

            'employee_type' => $this->whenLoaded('employeeType', function () {
                return [
                    'id' => $this->employeeType?->id,
                    'name' => $this->employeeType?->name,
                ];
            }),

            'reporting_manager' => $this->whenLoaded('reportingManager', function () {
                // if ($this->reportingManager->isEmpty()) {
                //     return '';
                // }
                return [
                    'id' => $this->reportingManager?->id,
                    'name' => trim(
                        ($this->reportingManager?->first_name ?? '') . ' ' .
                            ($this->reportingManager?->last_name ?? '')
                    ),
                    'email' => $this->reportingManager?->office_email,
                ];
            }),

            'team_members' => $this->whenLoaded('teamMembers', function () {
                // if ($this->teamMembers->isEmpty()) {
                //     return '';
                // }

                return $this->teamMembers->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'first_name' => $member->first_name ?? '',
                        'last_name' => $member->last_name ?? '',

                        'designation' => $member->designation ? [
                            'id' => $member->designation->id,
                            'name' => $member->designation->name,
                        ] : '',
                    ];
                });
            }),


            'created_at' => $this->created_at,
        ];
    }
}
