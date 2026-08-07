<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Services\Employees\ListEmployeesService;
use App\Services\Employees\StoreEmployeeService;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmployeesController extends ApiController
{
    public function index(Request $request, ListEmployeesService $service): JsonResponse
    {
        $user = Auth::user();

        $employees = $service->execute($user, [
            'branch_id' => $request->has('branch_id') ? $request->integer('branch_id') : null,
            'per_page' => $request->integer('per_page', 15),
        ])->appends($request->query());

        return $this->success(
            EmployeeResource::collection($employees)->response()->getData(true)
        );
    }

    public function store(StoreEmployeeRequest $request, StoreEmployeeService $service): JsonResponse
    {
        $employee = $service->execute($request->validated());

        return $this->success(
            new EmployeeResource($employee->load(['user', 'person', 'branch']))
        );
    }
}
