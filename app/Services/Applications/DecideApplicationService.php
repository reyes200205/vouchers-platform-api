<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\DistributorStatus;
use App\Enums\ManagerDecisionEventType;
use App\Models\Application;
use App\Models\Distributor;
use App\Models\ManagerDecisionLog;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DecideApplicationService
{
    /**
     * @param array{decision: string, credit_limit?: string|null, category_id?: int|null, coordinator_user_id?: int|null, rejection_reason?: string|null} $data
    * @return array{application: Application, distributor: Distributor|null, distributor_username: string|null, temporary_password: string|null}
     */
    public function execute(Application $application, User $manager, array $data): array
    {
        return DB::transaction(function () use ($application, $manager, $data): array {
            $application->refresh();

            if ($application->status !== ApplicationStatus::POSIBLE_DISTRIBUIDORA) {
                abort(422, 'The application must be a potential distributor before a final decision.');
            }

            if ($data['decision'] === 'REJECT') {
                $application->update([
                    'status' => ApplicationStatus::RECHAZADA,
                    'rejection_reason' => $data['rejection_reason'],
                    'decided_at' => now(),
                ]);

                ManagerDecisionLog::query()->create([
                    'manager_user_id' => $manager->id,
                    'application_id' => $application->id,
                    'event_type' => ManagerDecisionEventType::RECHAZO,
                ]);

                return ['application' => $application->fresh(), 'distributor' => null, 'distributor_username' => null, 'temporary_password' => null];
            }

            $creditLimit = $data['credit_limit'];
            $distributor = Distributor::query()->create([
                'person_id' => $application->applicant_person_id,
                'application_id' => $application->id,
                'branch_id' => $application->branch_id,
                'coordinator_user_id' => $data['coordinator_user_id'] ?? $application->coordinator_user_id,
                'category_id' => $data['category_id'],
                'distributor_number' => 'DIST-'.str_pad((string) $application->id, 8, '0', STR_PAD_LEFT),
                'status' => DistributorStatus::ACTIVA,
                'credit_limit' => $creditLimit,
                'available_credit' => $creditLimit,
                'can_issue_vouchers' => true,
                'activated_at' => now(),
            ]);

            // La contrasena inicial es el CURP del solicitante: lo trae consigo
            // (no hay que inventarle ni comunicarle un token aparte) y el
            // gerente lo ve en la misma pantalla de aprobacion. Si la solicitud
            // no capturo CURP (el campo es opcional en la captura inicial), se
            // cae a un token aleatorio como antes -- no puede quedar sin
            // contrasena. password_confirmed_at en null es lo que obliga al
            // modal de "deja esta contrasena o cambiala" en el primer login
            // (ver AuthController::confirmPassword/changePassword).
            $temporaryPassword = $application->applicant->curp ?: Str::random(48);
            $username = $this->uniqueUsername($application);
            $user = User::query()->firstOrCreate(
                ['person_id' => $application->applicant_person_id],
                [
                    'username' => $username,
                    'password_hash' => Hash::make($temporaryPassword),
                    'is_active' => true,
                    'password_confirmed_at' => null,
                ]
            );

            $role = Role::findOrCreate('distributor', 'web');
            $user->businessRoles()->attach($role, [
                'branch_id' => $application->branch_id,
                'assigned_at' => now(),
                'is_primary' => true,
            ]);

            $application->update(['status' => ApplicationStatus::APROBADA, 'decided_at' => now()]);

            ManagerDecisionLog::query()->create([
                'manager_user_id' => $manager->id,
                'application_id' => $application->id,
                'distributor_id' => $distributor->id,
                'event_type' => ManagerDecisionEventType::NUEVA_DISTRIBUIDORA,
                'new_amount' => $creditLimit,
            ]);

            return [
                'application' => $application->fresh(),
                'distributor' => $distributor,
                'distributor_username' => $user->username,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    private function uniqueUsername(Application $application): string
    {
        $base = Str::lower(Str::ascii($application->applicant->first_name.$application->applicant->last_name));
        $base = Str::limit(preg_replace('/[^a-z0-9]/', '', $base) ?: 'distributor', 64, '');
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = Str::limit($base, 70, '').$suffix++;
        }

        return $username;
    }
}
