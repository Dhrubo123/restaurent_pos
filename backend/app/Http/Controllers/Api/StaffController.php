<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        $isAdmin = $actor->can('manage-users') || $actor->can('manage-security');

        $users = User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->with('roles:id,key,name')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->presentUser($u));

        $roles = Role::query()
            ->when(! $isAdmin, fn ($q) => $q->where('key', '!=', 'admin'))
            ->orderBy('id')
            ->get(['id', 'key', 'name']);

        return response()->json([
            'data' => $users,
            'meta' => [
                'roles' => $roles,
                'can_assign_admin' => $isAdmin,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        $isAdmin = $actor->can('manage-users') || $actor->can('manage-security');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'role_key' => ['required', 'string', Rule::exists('roles', 'key')],
        ]);

        if ($validated['role_key'] === 'admin' && ! $isAdmin) {
            abort(403, 'Only an administrator can assign the Admin role.');
        }

        return DB::transaction(function () use ($request, $actor, $validated) {
            $role = Role::query()->where('key', $validated['role_key'])->firstOrFail();

            $staff = User::query()->create([
                'restaurant_id' => $actor->restaurant_id,
                'name' => trim($validated['name']),
                'email' => Str::lower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'active' => true,
            ]);

            $staff->roles()->sync([$role->id]);

            Audit::record($request, 'staff.created', $staff, after: [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $role->key,
            ]);

            return response()->json([
                'data' => $this->presentUser($staff->fresh('roles')),
            ], 201);
        });
    }

    public function update(Request $request, User $staff): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        abort_unless($staff->restaurant_id === $actor->restaurant_id, 404);
        $this->assertTargetEditable($actor, $staff);

        $isAdmin = $actor->can('manage-users') || $actor->can('manage-security');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:160',
                Rule::unique('users', 'email')->ignore($staff->id),
            ],
            'role_key' => ['sometimes', 'required', 'string', Rule::exists('roles', 'key')],
        ]);

        if (isset($validated['role_key']) && $validated['role_key'] === 'admin' && ! $isAdmin) {
            abort(403, 'Only an administrator can assign the Admin role.');
        }

        return DB::transaction(function () use ($request, $staff, $validated) {
            $before = [
                'name' => $staff->name,
                'email' => $staff->email,
                'roles' => $staff->roles->pluck('key')->all(),
            ];

            $staff->update([
                'name' => isset($validated['name']) ? trim($validated['name']) : $staff->name,
                'email' => isset($validated['email']) ? Str::lower(trim($validated['email'])) : $staff->email,
            ]);

            if (isset($validated['role_key'])) {
                $role = Role::query()->where('key', $validated['role_key'])->firstOrFail();
                $staff->roles()->sync([$role->id]);
            }

            $fresh = $staff->fresh('roles');

            Audit::record($request, 'staff.updated', $fresh, $before, [
                'name' => $fresh->name,
                'email' => $fresh->email,
                'roles' => $fresh->roles->pluck('key')->all(),
            ]);

            return response()->json(['data' => $this->presentUser($fresh)]);
        });
    }

    public function resetPassword(Request $request, User $staff): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        abort_unless($staff->restaurant_id === $actor->restaurant_id, 404);
        $this->assertTargetEditable($actor, $staff);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $staff, $validated) {
            $staff->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $staff->id)->delete();
            }

            Audit::record(
                $request,
                'staff.password_reset',
                $staff,
                null,
                ['staff_id' => $staff->id, 'email' => $staff->email],
                trim($validated['reason']),
            );
        });

        return response()->json(['data' => $this->presentUser($staff->fresh('roles'))]);
    }

    public function activate(Request $request, User $staff): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        abort_unless($staff->restaurant_id === $actor->restaurant_id, 404);
        $this->assertTargetEditable($actor, $staff);

        $staff->update(['active' => true]);
        Audit::record($request, 'staff.activated', $staff, ['active' => false], ['active' => true]);

        return response()->json(['data' => $this->presentUser($staff->fresh('roles'))]);
    }

    public function deactivate(Request $request, User $staff): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        abort_unless($staff->restaurant_id === $actor->restaurant_id, 404);
        $this->assertTargetEditable($actor, $staff);

        abort_if($staff->id === $actor->id, 409, 'You cannot deactivate your own currently signed-in account.');

        DB::transaction(function () use ($request, $staff) {
            $staff->forceFill([
                'active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $staff->id)->delete();
            }

            Audit::record($request, 'staff.deactivated', $staff, ['active' => true], ['active' => false]);
        });

        return response()->json(['data' => $this->presentUser($staff->fresh('roles'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->active,
            'roles' => $user->roles->map(fn (Role $r) => [
                'key' => $r->key,
                'name' => $r->name,
            ])->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function assertTargetEditable(User $actor, User $target): void
    {
        $isAdmin = $actor->can('manage-users') || $actor->can('manage-security');
        $targetIsAdmin = $target->roles()->where('key', 'admin')->exists();

        if ($targetIsAdmin && ! $isAdmin) {
            abort(403, 'Only an administrator can modify an Admin account.');
        }
    }

    private function authorizeStaff(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->can('manage-users') || $user->can('manage-floor') || $user->can('manage-menu'),
            403,
            'Manager or Administrator permission is required to manage staff accounts.',
        );

        return $user;
    }
}
