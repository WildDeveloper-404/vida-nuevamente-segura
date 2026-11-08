<?php

namespace Tests\Feature;

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
