<?php

namespace App\Services\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Users\RoleAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolePersistenceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role
    {
        if (! RoleAuthorization::canCreate()) {
            abort(403);
        }

        $data = $this->validate($data);

        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $data['permission_keys'] ?? []);

            return $role->fresh('permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        if (! RoleAuthorization::canEdit()) {
            abort(403);
        }

        if ($role->is_system) {
            abort(422, 'System roles cannot be edited.');
        }

        $data = $this->validate($data, $role);

        return DB::transaction(function () use ($role, $data): Role {
            $role->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);

            $this->syncPermissions($role, $data['permission_keys'] ?? []);

            return $role->fresh('permissions');
        });
    }

    public function delete(Role $role): void
    {
        if (! RoleAuthorization::canDelete()) {
            abort(403);
        }

        if ($role->is_system) {
            abort(422, 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            abort(422, 'This role is assigned to users and cannot be deleted.');
        }

        $role->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validate(array $data, ?Role $role = null): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::notIn([Role::SLUG_SUPER_ADMIN]),
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_keys' => ['nullable', 'array'],
            'permission_keys.*' => ['string', Rule::exists('permissions', 'key')],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    protected function syncPermissions(Role $role, array $permissionKeys): void
    {
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }
}
