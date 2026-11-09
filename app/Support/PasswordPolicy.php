<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public const DESCRIPTION = 'Use al menos 8 caracteres, una minúscula, una mayúscula, un número y un carácter especial.';

    public static function rules(bool $required, bool $confirmed = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:128',
            Password::min(self::MIN_LENGTH)->mixedCase()->numbers(),
            'regex:/[\p{P}\p{S}]/u',
            ...($confirmed ? ['confirmed'] : []),
        ];
    }
}
