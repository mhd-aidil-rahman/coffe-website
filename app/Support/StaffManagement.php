<?php

namespace App\Support;

use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffManagement
{
    /** @return Builder<StaffMember> */
    public function query(): Builder
    {
        return StaffMember::query()->orderBy('position')->orderBy('name')->orderBy('id');
    }

    /** @param array{name?: string, email?: string, position?: string, password?: ?string} $data */
    public function save(array $data, ?int $id = null): StaffMember
    {
        $staff = $id === null ? new StaffMember : StaffMember::query()->findOrFail($id);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff_members', 'email')->ignore($id)],
            'position' => ['required', 'string', Rule::in(['Barista', 'Waiter'])],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($staff, $validated, $id): StaffMember {
            $revokeTokens = $id !== null && (! empty($validated['password']) || $staff->position !== $validated['position']);
            $staff->fill(['name' => $validated['name'], 'email' => $validated['email'], 'position' => $validated['position']]);
            if (! empty($validated['password'])) {
                $staff->password = Hash::make($validated['password']);
            }
            if ($id === null) {
                $staff->active = true;
            }
            $staff->save();
            if ($revokeTokens) {
                $staff->tokens()->delete();
            }

            return $staff->refresh();
        });
    }

    public function setActive(int $id, bool $active): StaffMember
    {
        return DB::transaction(function () use ($id, $active): StaffMember {
            $staff = StaffMember::query()->findOrFail($id);
            $staff->active = $active;
            $staff->save();
            if (! $active) {
                $staff->tokens()->delete();
            }

            return $staff->refresh();
        });
    }

    public function toggleActive(int $id): StaffMember
    {
        return $this->setActive($id, ! StaffMember::query()->findOrFail($id)->active);
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $staff = StaffMember::query()->findOrFail($id);
            $staff->tokens()->delete();
            $staff->delete();
        });
    }
}
