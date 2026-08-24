<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Enums\BankAccountOwnerType;
use App\Http\Controllers\ApiController;
use App\Http\Resources\CutoffRelationResource;
use App\Models\BankAccount;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de cuenta de la distribuidora: sus propias relaciones de corte (lo
 * que le toca remitirle a la sucursal cada periodo -- referencia de pago,
 * quincenas incluidas, comisión que se queda, estado). La distribuidora
 * SIEMPRE se resuelve por el person_id del usuario autenticado, nunca por un
 * distributor_id que venga del request: así no hay forma de que una
 * distribuidora vea las relaciones (ni los datos de clientes) de otra.
 */
final class RelationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $relations = CutoffRelation::query()
            ->with(['items.customer.person', 'retroactiveReconciliation'])
            ->where('distributor_id', $distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('generated_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CutoffRelationResource::collection($relations)->response()->getData(true)
        );
    }

    public function show(Request $request, CutoffRelation $cutoffRelation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        // 404 en vez de 403: no le confirmamos a la distribuidora que la
        // relación existe (es de otra distribuidora), simplemente no la ve.
        if ($cutoffRelation->distributor_id !== $distributor->id) {
            abort(404);
        }

        $cutoffRelation->load(['items.customer.person', 'retroactiveReconciliation']);

        return $this->success(new CutoffRelationResource($cutoffRelation));
    }

    public function pdf(Request $request, CutoffRelation $cutoffRelation): Response
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        // 404 en vez de 403: mismo criterio que show(), no le confirmamos a la
        // distribuidora que la relación existe si es de otra.
        if ($cutoffRelation->distributor_id !== $distributor->id) {
            abort(404);
        }

        $cutoffRelation->load(['items.customer.person', 'distributor.person', 'distributor.branch']);

        $statusLabels = [
            'GENERADA' => 'Pendiente',
            'PAGADA' => 'Pagada',
            'PARCIAL' => 'Pago parcial',
            'VENCIDA' => 'Vencida',
            'CERRADA' => 'Cerrada',
        ];

        $distributorPerson = $cutoffRelation->distributor?->person;
        $distributorName = $distributorPerson
            ? trim(($distributorPerson->first_name ?? '').' '.($distributorPerson->last_name ?? ''))
            : 'Distribuidora';

        $addressParts = array_filter([
            $distributorPerson?->street,
            $distributorPerson?->external_number ? '#'.$distributorPerson->external_number : null,
            $distributorPerson?->neighborhood,
            $distributorPerson?->city,
            $distributorPerson?->state,
            $distributorPerson?->postal_code,
        ]);

        $brandName = $cutoffRelation->distributor?->branch?->name ?? config('app.name');

        // Cuenta(s) donde la distribuidora debe remitir el pago: las cuentas
        // bancarias de la empresa (no las de la propia distribuidora ni las
        // de una persona). Si todavía no hay ninguna cargada, la sección
        // simplemente no aparece en el PDF -- no se inventan datos.
        $bankAccounts = BankAccount::query()
            ->where('owner_type', BankAccountOwnerType::EMPRESA)
            ->orderByDesc('is_primary')
            ->get();

        $pdf = Pdf::loadView('pdf.distributor-relation', [
            'relation' => $cutoffRelation,
            'distributorName' => $distributorName,
            'distributorNumber' => $cutoffRelation->distributor?->distributor_number,
            'distributorAddress' => $addressParts === [] ? '—' : implode(', ', $addressParts),
            'brandName' => $brandName,
            'brandInitials' => mb_strtoupper(mb_substr((string) $brandName, 0, 3)),
            'bankAccounts' => $bankAccounts,
            'periodLabel' => sprintf(
                '%s – %s',
                $cutoffRelation->early_payment_start_date?->translatedFormat('d/m/Y') ?? '—',
                $cutoffRelation->early_payment_end_date?->translatedFormat('d/m/Y') ?? '—',
            ),
            'dueDateLabel' => $cutoffRelation->payment_due_date?->translatedFormat('d/m/Y') ?? '—',
            'generatedAtLabel' => $cutoffRelation->generated_at?->translatedFormat('d/m/Y H:i') ?? '—',
            'statusLabel' => $statusLabels[$cutoffRelation->status?->value ?? 'GENERADA'] ?? $cutoffRelation->status?->value,
        ])->setPaper('letter');

        return $pdf->download("estado-de-cuenta-{$cutoffRelation->relation_number}.pdf");
    }
}
