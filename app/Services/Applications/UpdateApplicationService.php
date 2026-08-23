<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de datos mal capturados por el coordinador, hecha por el
 * verificador durante o antes de su visita. Solo procede mientras la
 * solicitud siga EN_REVISION y no se haya registrado ya una verificación
 * (una vez verificada, la corrección debe pasar por una autorización
 * distinta, no por esta vía — ver applications.update en
 * config/business-authorization.php).
 */
final class UpdateApplicationService
{
    /**
     * Etiquetas legibles para el diff que se muestra en el detalle de la
     * solicitud (gerencia necesita ver QUÉ corrigió el verificador antes de
     * decidir, no solo que "algo cambió").
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'first_name' => 'Nombre',
        'middle_name' => 'Segundo nombre',
        'last_name' => 'Apellido paterno',
        'second_last_name' => 'Apellido materno',
        'gender' => 'Género',
        'birth_date' => 'Fecha de nacimiento',
        'curp' => 'CURP',
        'rfc' => 'RFC',
        'home_phone' => 'Teléfono de casa',
        'mobile_phone' => 'Celular',
        'email' => 'Correo',
        'street' => 'Calle',
        'external_number' => 'Número exterior',
        'neighborhood' => 'Colonia',
        'city' => 'Ciudad',
        'state' => 'Estado',
        'postal_code' => 'C.P.',
        'street_references' => 'Referencias del domicilio',
        'notes' => 'Notas',
        'requested_credit_limit' => 'Crédito solicitado',
        'family_data' => 'Datos familiares, ocupación y vivienda',
        'vehicles' => 'Vehículos',
    ];

    /**
     * @param  array{person?: array<string, mixed>, family_data?: array<string, mixed>, vehicles?: array<int, mixed>, requested_credit_limit?: string}  $data
     */
    public function execute(User $actor, Application $application, array $data): Application
    {
        abort_unless(
            $application->status === ApplicationStatus::EN_REVISION,
            422,
            'La solicitud ya no está pendiente de verificación; no se puede editar.'
        );

        abort_unless(
            $application->assigned_verifier_id === $actor->id,
            403,
            'Solo el verificador asignado a esta solicitud puede editarla.'
        );

        DB::transaction(function () use ($actor, $application, $data): void {
            $changes = [];

            if (array_key_exists('person', $data) && $data['person'] !== [] && $application->applicant) {
                $person = $application->applicant;
                foreach ($data['person'] as $field => $newValue) {
                    $oldValue = $person->{$field};
                    $oldScalar = $oldValue instanceof \BackedEnum ? $oldValue->value : $oldValue;
                    $newScalar = $newValue instanceof \BackedEnum ? $newValue->value : $newValue;
                    if ((string) ($oldScalar ?? '') !== (string) ($newScalar ?? '')) {
                        $changes[] = $this->changeEntry($field, $oldScalar, $newScalar);
                    }
                }
                $person->update($data['person']);
            }

            $applicationData = [];
            if (array_key_exists('family_data', $data)) {
                // Estructura anidada (familiares, ocupación, vivienda): no vale
                // la pena diffear campo por campo aquí, pero gerencia sí debe
                // saber que el verificador tocó esta sección antes de decidir.
                if (json_encode($data['family_data']) !== json_encode($application->family_data_json)) {
                    $changes[] = $this->changeEntry('family_data', 'Datos originales del coordinador', 'Corregidos por el verificador');
                }
                $applicationData['family_data_json'] = $data['family_data'];
            }
            if (array_key_exists('vehicles', $data)) {
                if (json_encode($data['vehicles']) !== json_encode($application->vehicles_json)) {
                    $changes[] = $this->changeEntry('vehicles', 'Datos originales del coordinador', 'Corregidos por el verificador');
                }
                $applicationData['vehicles_json'] = $data['vehicles'];
            }
            if (array_key_exists('requested_credit_limit', $data)) {
                $oldLimit = (string) $application->requested_credit_limit;
                $newLimit = (string) $data['requested_credit_limit'];
                if ($oldLimit !== $newLimit) {
                    $changes[] = $this->changeEntry('requested_credit_limit', $oldLimit, $newLimit);
                }
                $applicationData['requested_credit_limit'] = $data['requested_credit_limit'];
            }

            if ($changes !== []) {
                $history = $application->verifier_corrections_json ?? [];
                $history[] = [
                    'corrected_at' => now()->toIso8601String(),
                    'corrected_by_user_id' => $actor->id,
                    'corrected_by_name' => trim(($actor->person?->first_name ?? '').' '.($actor->person?->last_name ?? '')) ?: $actor->username,
                    'changes' => $changes,
                ];
                $applicationData['verifier_corrections_json'] = $history;
            }

            if ($applicationData !== []) {
                $application->update($applicationData);
            }
        });

        return $application->fresh(['applicant', 'branch', 'assignedVerifier.person']);
    }

    /**
     * @return array{field: string, label: string, old_value: mixed, new_value: mixed}
     */
    private function changeEntry(string $field, mixed $oldValue, mixed $newValue): array
    {
        return [
            'field' => $field,
            'label' => self::FIELD_LABELS[$field] ?? $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ];
    }
}
