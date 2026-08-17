<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateAdministratorCommand extends Command
{
    protected $signature = 'app:create-admin {username} {--password=}';

    protected $description = 'Crea (o reactiva) el usuario administrador inicial del sistema';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = $this->option('password') ?: Str::random(16);

        $role = Role::query()->firstOrCreate(
            ['code' => 'administrator'],
            ['name' => 'Administrador', 'is_active' => true]
        );

        $existing = User::query()->where('username', $username)->first();

        if ($existing) {
            $this->error("Ya existe un usuario con username '{$username}'.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($username, $password, $role): void {
            $person = Person::query()->create([
                'first_name' => 'Administrador',
                'last_name' => 'Sistema',
            ]);

            User::query()->create([
                'person_id' => $person->id,
                'username' => $username,
                'password_hash' => Hash::make($password),
                'role_id' => $role->id,
                'branch_id' => null,
                'is_active' => true,
            ]);
        });

        $this->info("Administrador '{$username}' creado.");

        if (! $this->option('password')) {
            $this->warn("Password generado (guardalo, no se volvera a mostrar): {$password}");
        }

        return self::SUCCESS;
    }
}
