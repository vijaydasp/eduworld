<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.users.edit', ['account' => new User(['is_active' => true, 'role' => 'admin'])]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['account' => $user]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->except('password_confirmation'));
        Audit::record('user.created', $user, [], ['role' => $user->role]);

        return redirect()->route('admin.users.index')->with('success', 'Team member created.');
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $admins = User::where('role', 'super_admin')->where('is_active', true)->lockForUpdate()->get();
            $data = $request->safe()->except('password_confirmation');
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if ($user->role === 'super_admin' && $user->is_active && ($data['role'] !== 'super_admin' || ! $data['is_active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active Super Admin.']);
            }
            $before = $user->only(['name', 'email', 'role', 'is_active']);
            $user->update($data);
            if (isset($data['password']) || ! $user->is_active || $before['role'] !== $user->role) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            Audit::record('user.updated', $user, $before, $user->only(['name', 'email', 'role', 'is_active']));
        });

        return redirect()->route('admin.users.index')->with('success','Team member updated.');
    }
}
