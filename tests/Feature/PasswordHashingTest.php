<?php

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordHashingTest extends TestCase
{
    public function test_new_passwords_use_argon2id(): void
    {
        $user = $this->user();
        $this->assertSame('argon2id', password_get_info($user->password_hash)['algoName']);
        $this->assertTrue(Hash::check('ClavePrueba123!', $user->password_hash));
    }

    public function test_reset_requires_a_strong_password_and_accepts_eight_characters(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->user();
        $token = str_repeat('a', 64);
        $user->update([
            'token_recuperacion' => hash('sha256', $token),
            'token_expiracion' => now()->addMinutes(30),
        ]);
        $originalHash = $user->password_hash;

        foreach (['Demo1!a', 'demo123!', 'DEMO123!', 'Demoabc!', 'Demo1234', 'Demo12 3'] as $password) {
            $this->post('/restablecer', [
                'correo' => $user->correo,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
            $this->assertSame($originalHash, $user->fresh()->password_hash);
        }

        $this->post('/restablecer', [
            'correo' => $user->correo,
            'token' => $token,
            'password' => 'Demo12!a',
            'password_confirmation' => 'Demo12!a',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('Demo12!a', $user->fresh()->password_hash));
        $this->assertNull($user->fresh()->token_recuperacion);
    }

    public function test_bcrypt_login_migrates_hash_and_keeps_authenticated_session(): void
    {
        $user = $this->user();
        $oldHash = Hash::driver('bcrypt')->make('ClavePrueba123!');
        $user->update(['password_hash' => $oldHash]);

        $this->post('/login', ['correo' => $user->correo, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('correo');
        $this->assertSame($oldHash, $user->fresh()->password_hash);

        $this->post('/login', ['correo' => $user->correo, 'password' => 'ClavePrueba123!'])
            ->assertRedirect('/admin');
        $newHash = $user->fresh()->password_hash;
        $this->assertSame('argon2id', password_get_info($newHash)['algoName']);
        $this->assertTrue(Hash::check('ClavePrueba123!', $newHash));
        $this->get('/admin')->assertOk();
    }
}
