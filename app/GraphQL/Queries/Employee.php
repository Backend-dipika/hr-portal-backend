<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Error;
use Exception;

final readonly class Employee
{
    /** @param  array{}  $args */
    public function __invoke(null $_, array $args)
    {
        // TODO implement the resolver
    }
    public function showEmployeesList()
    {
        try {
            return User::with(['designation', 'department', 'employeeOfMonth'])->get();
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error occurred while sending employee details', 'error' => $e->getMessage()], 500);
        }
    }

    public function showEmployeeDetails($_, array $args)
    {
        try {
            $user = User::with(['designation', 'department', 'employeeOfMonth'])
                ->find($args['id']); 

            if (!$user) {
                throw new Error('Employee not found.');
            }
            return $user;

        } catch (Exception $e) {
            throw new Error('Error retrieving employee details: ' . $e->getMessage());
        }
    }
}
