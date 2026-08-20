<?php

$u = \App\Models\User::where('username', 'mariadelaluzdelacruz')->first();

if (! $u) {
    echo "No se encontro el usuario mariadelaluzdelacruz\n";
} else {
    $u->password_hash = \Illuminate\Support\Facades\Hash::make('password');
    $u->is_active = true;
    $u->save();
    echo "Password actualizado correctamente para {$u->username} (id {$u->id})\n";
}
