<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $users = User::with('branch')
            ->when($search, function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        // ?create=1 opens the add form, ?edit=ID opens the edit form
        $editing = $request->query('edit') ? User::find($request->query('edit')) : null;

        if ($editing) {
            abort_unless($this->canManage($editing), 403);
        }

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'roles' => $this->allowedRoles(),
            'showForm' => $request->boolean('create') || $editing,
            'editing' => $editing,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = new User();
        $this->fill($user, $data);

        return redirect()->route('users.index')->with('status', 'Added ' . $user->name . '.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($this->canManage($user), 403);

        $data = $this->validated($request, $user);
        $active = (bool) ($data['is_active'] ?? false);

        if ($user->id === $request->user()->id && ! $active) {
            return back()->withInput()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        if ($this->isLastActiveAdmin($user) && ($data['role'] !== 'admin' || ! $active)) {
            return back()->withInput()->withErrors(['role' => 'This is the last active admin. Make someone else an admin first.']);
        }

        $this->fill($user, $data);

        return redirect()->route('users.index')->with('status', 'Updated ' . $user->name . '.');
    }

    public function toggle(Request $request, User $user)
    {
        abort_unless($this->canManage($user), 403);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['general' => 'You cannot deactivate your own account.']);
        }

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->withErrors(['general' => 'This is the last active admin and cannot be deactivated.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->name . ($user->is_active ? ' activated.' : ' deactivated.'));
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        return $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in($this->allowedRoles())],
            'branch_id' => 'required|exists:branches,id',
            'password' => $user ? 'nullable|string|min:6' : 'required|string|min:6',
            'pin' => 'nullable|digits_between:4,6',
            'is_active' => 'nullable|boolean',
        ], [
            'username.alpha_dash' => 'Use only letters, numbers, dashes and underscores (no spaces).',
            'pin.digits_between' => 'The PIN must be 4 to 6 digits.',
        ]);
    }

    protected function fill(User $user, array $data): void
    {
        $user->fill([
            'name' => trim($data['name']),
            'username' => $data['username'],
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'role' => $data['role'],
            'branch_id' => $data['branch_id'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (! empty($data['pin'])) {
            $user->pin = $data['pin'];
        }

        $user->save();
    }

    protected function allowedRoles(): array
    {
        return auth()->user()->isAdmin() ? ['admin', 'manager', 'cashier'] : ['cashier'];
    }

    protected function canManage(User $user): bool
    {
        return auth()->user()->isAdmin() || $user->role === 'cashier';
    }

    protected function isLastActiveAdmin(User $user): bool
    {
        return $user->role === 'admin'
            && User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }
}
