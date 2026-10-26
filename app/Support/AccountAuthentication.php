<?php

namespace App\Support;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AccountAuthentication
{
    /**
     * @param  array{email?: mixed, password?: mixed}  $data
     * @return array{email: string, password: string}
     */
    public function credentials(array $data): array
    {
        return Validator::make($data, [
            'email' => ['required', 'string', 'max:255', Rule::when(($data['email'] ?? null) !== config('owner.email'), 'email')],
            'password' => ['required', 'string'],
        ])->validate();
    }

    public function authenticate(string $email, string $password): User|StaffMember|null
    {
        $owner = User::query()->where('email', $email)->first();
        if ($owner && Hash::check($password, $owner->password)) {
            return $owner;
        }
        $staff = StaffMember::query()->where('email', $email)->where('active', true)->whereIn('position', ['Waiter', 'Barista'])->first();

        return $staff && $staff->password && Hash::check($password, $staff->password) ? $staff : null;
    }

    public function activeStaff(int $id, string $role): ?StaffMember
    {
        return StaffMember::query()->whereKey($id)->where('position', $role)->where('active', true)->first();
    }

    public function role(User|StaffMember $account): string
    {
        return $account instanceof User ? 'Owner' : $account->position;
    }
}
