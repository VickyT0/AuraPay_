<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(
    function () use ($input) {

        $user = User::create([

            'name'
                => $input['name'],

            'email'
                => $input['email'],

            'password'
                => $input['password'],
        ]);

        Wallet::create([

            'user_id'
                => $user->id,

            'balance'
                => 0,

            'currency'
                => 'EUR',

            'status'
                => 'active',
        ]);

        return $user;
    }
);
    }
}
