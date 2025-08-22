<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Exception;

final readonly class Employee
{
    /** @param  array{}  $args */
    public function __invoke(null $_, array $args)
    {
        // TODO implement the resolver
    }
    public function showEmployeeDetails()
    {
        try {
          return User::with(['designation', 'department', 'employeeOfMonth'])->get();
            // return response()->json(['users' => $user, 'status' => true, 'message' => 'Employee details sent successfully',]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error occurred while sending employee details', 'error' => $e->getMessage()], 500);
        }
    }
}
