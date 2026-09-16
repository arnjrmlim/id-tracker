<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', User::class);

        $users = User::orderBy('name')->paginate(20);
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

        $data = [
            'name'     => $request->validated('name'),
            'username' => $request->validated('username'),
            'email'    => $request->validated('email') ?: null,
            'role'     => $request->validated('role'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);

        Log::info("User updated: #{$user->id} {$user->username} by " . auth()->id());

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} updated successfully.");
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();
        Log::info("User deleted: #{$user->id} {$user->username} by " . auth()->id());

        return redirect()->route('users.index')
            ->with('success', 'User deleted.');
    }

    public function activate(User $user)
    {
        Gate::authorize('update', $user);

        $user->update(['is_active' => true]);
        Log::info("User activated: #{$user->id} by " . auth()->id());

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

        return back()->with('success', "Password for {$user->username} reset successfully.");
    }
}
