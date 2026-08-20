<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Vouchers\StoreVoucherRequest;
use App\Http\Resources\VoucherRequestResource;
use App\Http\Resources\VoucherResource;
use App\Models\Distributor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Vouchers\RequestVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoucherController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $vouchers = Voucher::query()
            ->with(['customer.person', 'branch.setting'])
            ->where('distributor_id', $distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            VoucherResource::collection($vouchers)->response()->getData(true)
        );
    }

    /**
     * Solicitudes de vale de la distribuidora que aun no se materializan en un Voucher
     * (pendientes de que la cajera las apruebe o rechace). El "Mis vales" del frontend
     * combina esto con index() para que la solicitud se vea reflejada apenas se pide,
     * sin esperar a que exista el Voucher.
     */
    public function requests(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $voucherRequests = VoucherRequest::query()
            ->with(['customer.person'])
            ->where('distributor_id', $distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            VoucherRequestResource::collection($voucherRequests)->response()->getData(true)
        );
    }

    public function store(StoreVoucherRequest $request, RequestVoucherService $service, AuditLogger $audit): JsonResponse
    {
        $voucherRequest = $service->execute($request->user(), $request->validated());

        $audit->record(
            $request,
            'VOUCHER_REQUESTED',
            'vouchers',
            'Solicitud de vale creada por la distribuidora, pendiente de aprobacion.',
            $voucherRequest->branch_id,
            ['voucher_request_id' => $voucherRequest->id, 'is_pre_vale' => $voucherRequest->is_pre_vale]
        );

        return $this->created(
            new VoucherRequestResource($voucherRequest->load(['customer.person', 'financialProduct']))
        );
    }
}