<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Enums\ApplicationStatus;
use App\Enums\CreditIncreaseRequestStatus;
use App\Enums\PointRedemptionStatus;
use App\Http\Controllers\ApiController;
use App\Models\Application;
use App\Models\CreditIncreaseRequest;
use App\Models\PointRedemption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InboxController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tab = $request->string('tab', 'all')->value();
        $perPage = $request->integer('per_page', 10) ?: 10;
        $branchId = $request->integer('branch_id', 0) ?: null;

        $allowedBranchIds = $user->hasGlobalBusinessRole()
            ? null
            : $user->activeBusinessBranchIds();

        $branchIds = $branchId ? [$branchId] : $allowedBranchIds;

        $data = [];

        if ($tab === 'all' || $tab === 'applications') {
            $data['applications'] = $this->applications($branchIds, $perPage);
        }

        if ($tab === 'all' || $tab === 'credit') {
            $data['credit_increases'] = $this->creditIncreases($branchIds, $perPage);
        }

        if ($tab === 'all' || $tab === 'redemptions') {
            $data['redemptions'] = $this->redemptions($branchIds, $perPage);
        }

        return $this->success($data);
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function applications(?array $branchIds, int $perPage): array
    {
        $query = Application::query()
            ->with([
                'applicant',
                'branch',
                'coordinator.person',
                'assignedVerifier.person',
                'verification',
            ])
            ->where('status', ApplicationStatus::POSIBLE_DISTRIBUIDORA)
            ->orderByDesc('created_at');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => $paginator->map(fn (Application $application): array => [
                'id' => $application->id,
                'type' => 'application',
                'status' => $application->status->value,
                'branch_id' => $application->branch_id,
                'branch_name' => $application->branch?->name,
                'applicant_name' => $application->applicant
                    ? trim(($application->applicant->first_name ?? '').' '.($application->applicant->last_name ?? ''))
                    : null,
                'requested_credit_limit' => $application->requested_credit_limit,
                'initial_category_code' => $application->initial_category_code,
                'house_photos_complete' => $application->house_photos_complete,
                'prevale_approved' => $application->prevale_approved,
                'credit_bureau_result' => $application->credit_bureau_result,
                'coordinator_name' => $application->coordinator?->person
                    ? trim(($application->coordinator->person->first_name ?? '').' '.($application->coordinator->person->last_name ?? ''))
                    : null,
                'verifier_name' => $application->assignedVerifier?->person
                    ? trim(($application->assignedVerifier->person->first_name ?? '').' '.($application->assignedVerifier->person->last_name ?? ''))
                    : null,
                'verification' => $application->verification ? [
                    'result' => $application->verification->result,
                    'visit_date' => $application->verification->visit_date?->toIso8601String(),
                ] : null,
                'created_at' => $application->created_at?->toIso8601String(),
            ])->all(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function creditIncreases(?array $branchIds, int $perPage): array
    {
        $query = CreditIncreaseRequest::query()
            ->with(['distributor.person', 'branch', 'requestedBy.person', 'preAuthorizedBy.person'])
            ->where('status', CreditIncreaseRequestStatus::PRE_AUTORIZADO)
            ->orderByDesc('created_at');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => $paginator->map(fn (CreditIncreaseRequest $request): array => [
                'id' => $request->id,
                'type' => 'credit_increase',
                'status' => $request->status->value,
                'branch_id' => $request->branch_id,
                'branch_name' => $request->branch?->name,
                'distributor_id' => $request->distributor_id,
                'distributor_name' => $request->distributor?->person
                    ? trim(($request->distributor->person->first_name ?? '').' '.($request->distributor->person->last_name ?? ''))
                    : null,
                'distributor_number' => $request->distributor?->distributor_number,
                'requested_amount' => $request->requested_amount,
                'pre_authorized_amount' => $request->pre_authorized_amount,
                'reason' => $request->reason,
                'requested_by_name' => $request->requestedBy?->person
                    ? trim(($request->requestedBy->person->first_name ?? '').' '.($request->requestedBy->person->last_name ?? ''))
                    : null,
                'created_at' => $request->created_at?->toIso8601String(),
            ])->all(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function redemptions(?array $branchIds, int $perPage): array
    {
        $query = PointRedemption::query()
            ->with(['distributor.person', 'branch', 'requestedBy.person'])
            ->where('status', PointRedemptionStatus::PENDIENTE)
            ->orderByDesc('created_at');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => $paginator->map(fn (PointRedemption $redemption): array => [
                'id' => $redemption->id,
                'type' => 'redemption',
                'status' => $redemption->status->value,
                'branch_id' => $redemption->branch_id,
                'branch_name' => $redemption->branch?->name,
                'distributor_id' => $redemption->distributor_id,
                'distributor_name' => $redemption->distributor?->person
                    ? trim(($redemption->distributor->person->first_name ?? '').' '.($redemption->distributor->person->last_name ?? ''))
                    : null,
                'distributor_number' => $redemption->distributor?->distributor_number,
                'points' => $redemption->points,
                'point_value_snapshot' => $redemption->point_value_snapshot,
                'amount_mxn' => $redemption->amount_mxn,
                'requested_by_name' => $redemption->requestedBy?->person
                    ? trim(($redemption->requestedBy->person->first_name ?? '').' '.($redemption->requestedBy->person->last_name ?? ''))
                    : null,
                'created_at' => $redemption->created_at?->toIso8601String(),
            ])->all(),
            'total' => $paginator->total(),
        ];
    }
}