<?php

namespace App\Support;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountAuthentication
{
    public function authenticate(string $email, string $password): User|StaffMember|null
    {
        $ownerEmail = (string) config('owner.email');
        $ownerPassword = config('owner.password');
        if (is_string($ownerPassword) && $ownerPassword !== '' && hash_equals($ownerEmail, $email) && hash_equals($ownerPassword, $password)) {
            return User::query()->firstOrCreate(['email' => $ownerEmail], ['name' => 'Owner Gotham', 'password' => $password]);
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
