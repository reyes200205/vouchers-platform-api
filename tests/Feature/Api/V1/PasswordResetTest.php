<?php

declare(strict_types=1);

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

describe('Forgot Password', function (): void {
    it('sends a reset link when the identifier matches the username', function (): void {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'username' => $user->username,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        Mail::assertSent(PasswordResetMail::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->person->email]);
    });

    it('sends a reset link when the identifier matches the person email', function (): void {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'username' => $user->person->email,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        Mail::assertSent(PasswordResetMail::class);
    });

    it('responds the same generic message for a non-existent identifier, without sending mail', function (): void {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'username' => 'no-existe',
        ]);

        // Mismo mensaje que el caso feliz: no debe revelar si el usuario existe.
        $response->assertOk()->assertJson(['success' => true]);
        Mail::assertNothingSent();
    });

    it('does not send mail for an inactive user', function (): void {
        Mail::fake();
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['username' => $user->username])
            ->assertOk();

        Mail::assertNothingSent();
    });

    it('respects the auth rate limiter (5 per minute)', function (): void {
        Mail::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['username' => $user->username])
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['username' => $user->username])
            ->assertStatus(429);
    });
});

describe('Reset Password', function (): void {
    it('resets the password with a valid token', function (): void {
        $user = User::factory()->create();
        $email = $user->person->email;
        $rawToken = 'a-valid-raw-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => $rawToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password_hash));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
    });

    it('revokes existing tokens on reset', function (): void {
        $user = User::factory()->create();
        $email = $user->person->email;
        $user->createToken('old-session');
        $rawToken = 'a-valid-raw-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => $rawToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    });

    it('fails with an invalid token', function (): void {
        $user = User::factory()->create();
        $email = $user->person->email;

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make('the-real-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => 'wrong-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
    });

    it('fails with an expired token', function (): void {
        $user = User::factory()->create();
        $email = $user->person->email;
        $rawToken = 'a-valid-raw-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($rawToken),
            'created_at' => now()->subMinutes((int) config('auth.passwords.users.expire') + 5),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => $rawToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
    });

    it('fails with mismatched password confirmation', function (): void {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->person->email,
            'token' => 'whatever',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
    });

    it('fails when there is no token for the given email', function (): void {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => 'some-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
    });
});
