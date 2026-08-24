<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Resources\DistributorResource;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DistributorController extends ApiController
{
    /**
     * Listado de distribuidoras de la sucursal, usado para elegir a quien
     * pedirle un aumento de linea de credito (ver CreditIncreaseController::store).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->activeBusinessBranchIds();

        $distributors = Distributor::query()
            ->with(['person', 'branch', 'coordinator.person'])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();

                $query->where(function ($query) use ($search) {
                    $query->where('distributor_number', 'like', "%{$search}%")
                        ->orWhereHas('person', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->orderBy('distributor_number')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            DistributorResource::collection($distributors)->response()->getData(true)
        );
    }
}
