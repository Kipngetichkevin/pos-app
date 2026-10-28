<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Username</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Branch</th>
                <th class="px-4 py-3">PIN</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($users as $user)
                @php
                    $roleColors = ['admin' => 'bg-purple-100 text-purple-800', 'manager' => 'bg-blue-100 text-blue-800', 'cashier' => 'bg-slate-100 text-slate-700'];
                    $canManage = auth()->user()->isAdmin() || $user->role === 'cashier';
                @endphp
                <tr class="{{ $user->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3 font-medium">
                        {{ $user->name }}
                        @if ($user->id === auth()->id()) <span class="text-xs text-slate-400">(you)</span> @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $user->username }}</td>
                    <td class="px-4 py-3"><span class="rounded px-2 py-0.5 text-xs uppercase {{ $roleColors[$user->role] ?? '' }}">{{ $user->role }}</span></td>
                    <td class="px-4 py-3">{{ $user->branch?->name }}</td>
                    <td class="px-4 py-3 text-xs">{{ $user->pin ? 'Set' : '-' }}</td>
                    <td class="px-4 py-3">
                        @if ($user->is_active)
                            <span class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">Active</span>
                        @else
                            <span class="rounded bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Inactive</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($canManage)
                            <a href="{{ route('users.index', ['edit' => $user->id]) }}" class="text-blue-600 hover:underline">Edit</a>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.toggle', $user) }}" class="inline"
                                      onsubmit="return confirm(@js($user->is_active ? 'Deactivate ' . $user->name . '? They will be logged out immediately.' : 'Activate ' . $user->name . '?'))">
                                    @csrf @method('PATCH')
                                    <button class="ml-3 text-slate-500 hover:underline">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
