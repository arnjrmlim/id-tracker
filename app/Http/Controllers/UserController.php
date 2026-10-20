<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', User::class);

        $users = User::orderBy('name')->paginate(25);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        $roles = UserRole::options();
        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        Gate::authorize('create', User::class);

        $user = User::create([
            'name'      => $request->validated('name'),
            'username'  => $request->validated('username'),
            'email'     => $request->validated('email') ?: null,
            'password'  => Hash::make($request->validated('password')),
            'role'      => $request->validated('role'),
            'is_active' => true,
        ]);

        Log::info("User created: #{$user->id} {$user->username} by " . auth()->id());

        AuditLogService::log(
            action: 'USER_CREATED',
            module: 'Users',
            description: "Created user \"{$user->username}\" with role \"{$user->role->label()}\".",
            target: $user,
            newValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->label(),
                'is_active' => $user->is_active ? 'Active' : 'Inactive',
            ]
        );

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} created successfully.");
    }

    public function show(User $user)
    {
        Gate::authorize('view', $user);

        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        Gate::authorize('update', $user);

        $roles = UserRole::options();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        // Capture old values before update
        $oldValues = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->label(),
            'is_active' => $user->is_active ? 'Active' : 'Inactive',
        ];

        $data = [
            'name'     => $request->validated('name'),
            'username' => $request->validated('username'),
            'email'    => $request->validated('email') ?: null,
            'role'     => $request->validated('role'),
        ];

        $passwordChanged = false;
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
            $passwordChanged = true;
        }

        $user->update($data);

        // Capture new values after update
        $newValues = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->label(),
            'is_active' => $user->is_active ? 'Active' : 'Inactive',
        ];

        Log::info("User updated: #{$user->id} {$user->username} by " . auth()->id());

        // Determine which fields actually changed
        $changedFields = [];
        $filteredOldValues = [];
        $filteredNewValues = [];

        foreach ($oldValues as $field => $oldValue) {
            if ($oldValue !== $newValues[$field]) {
                $changedFields[] = $field;
                $filteredOldValues[$field] = $oldValue;
                $filteredNewValues[$field] = $newValues[$field];
            }
        }

        // Log password change separately without storing the password
        if ($passwordChanged) {
            AuditLogService::log(
                action: 'USER_PASSWORD_CHANGED',
                module: 'Users',
                description: "Changed password for user \"{$user->username}\".",
                target: $user
            );
        }

        // Only log audit entry if fields actually changed
        if (!empty($changedFields)) {
            // Generate description based on changed fields
            $fieldDescriptions = [
                'name' => 'name',
                'username' => 'username',
                'email' => 'email',
                'role' => 'role',
                'is_active' => 'status',
            ];

            $changedFieldNames = array_map(fn($f) => $fieldDescriptions[$f] ?? $f, $changedFields);
            $description = count($changedFieldNames) === 1
                ? "Updated user \"{$user->username}\" {$changedFieldNames[0]}."
                : "Updated user \"{$user->username}\" (" . implode(', ', $changedFieldNames) . ").";

            AuditLogService::log(
                action: 'USER_UPDATED',
                module: 'Users',
                description: $description,
                target: $user,
                oldValues: $filteredOldValues,
                newValues: $filteredNewValues
            );
        }

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} updated successfully.");
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $userData = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->label(),
        ];

        $user->delete();
        Log::info("User deleted: #{$user->id} {$user->username} by " . auth()->id());

        AuditLogService::log(
            action: 'USER_DELETED',
            module: 'Users',
            description: "Deleted user \"{$userData['username']}\" ({$userData['name']}).",
            target: null,
            oldValues: $userData,
            newValues: null
        );

        return redirect()->route('users.index')
            ->with('success', 'User deleted.');
    }

    public function activate(User $user)
    {
        Gate::authorize('update', $user);

        $user->update(['is_active' => true]);
        Log::info("User activated: #{$user->id} by " . auth()->id());

        AuditLogService::log(
            action: 'USER_ACTIVATED',
            module: 'Users',
            description: "Activated user \"{$user->username}\".",
            target: $user,
            oldValues: ['is_active' => 'Inactive'],
            newValues: ['is_active' => 'Active']
        );

        return back()->with('success', "User {$user->username} activated.");
    }

    public function deactivate(User $user)
    {
        Gate::authorize('update', $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => false]);
        Log::info("User deactivated: #{$user->id} by " . auth()->id());

        AuditLogService::log(
            action: 'USER_DEACTIVATED',
            module: 'Users',
            description: "Deactivated user \"{$user->username}\".",
            target: $user,
            oldValues: ['is_active' => 'Active'],
            newValues: ['is_active' => 'Inactive']
        );

        return back()->with('success', "User {$user->username} deactivated.");
    }

    public function resetPassword(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);
        Log::info("Password reset for user #{$user->id} by " . auth()->id());

        AuditLogService::log(
            action: 'USER_PASSWORD_CHANGED',
            module: 'Users',
            description: "Reset password for user \"{$user->username}\".",
            target: $user
        );

        return back()->with('success', "Password for {$user->username} reset successfully.");
    }
}
