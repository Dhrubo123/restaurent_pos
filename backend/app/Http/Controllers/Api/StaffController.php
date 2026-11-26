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

        $users = User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->with('roles:id,key,name')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->presentUser($u));

        $roles = Role::query()
            ->orderBy('id')
            ->get(['id', 'key', 'name']);

        return response()->json([
            'data' => $users,
            'meta' => [
                'roles' => $roles,
                'can_assign_admin' => true,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authorizeStaff($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'role_key' => ['required', 'string', Rule::exists('roles', 'key')],
        ]);

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

        $staff->update(['active' => true]);
        Audit::record($request, 'staff.activated', $staff, ['active' => false], ['active' => true]);

        return response()->json(['data' => $this->presentUser($staff->fresh('roles'))]);
    }

    public function deactivate(Request $request, User $staff): JsonResponse
    {
        $actor = $this->authorizeStaff($request);
        abort_unless($staff->restaurant_id === $actor->restaurant_id, 404);

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

    private function authorizeStaff(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->roles()->where('key', 'admin')->exists(),
            403,
            'Only an administrator can access staff accounts.',
        );

        return $user;
    }
}
