@php $u = $editing; @endphp
<form method="POST" action="{{ $u ? route('users.update', $u) : route('users.store') }}"
      class="grid grid-cols-1 gap-4 md:grid-cols-2"
      x-data="{ busy: false }"
      x-on:submit="if (busy) { $event.preventDefault(); return; } busy = true;">
    @csrf
    @if ($u) @method('PUT') @endif

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Full name *</label>
        <input name="name" value="{{ old('name', $u?->name) }}" type="text" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'name'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Username *</label>
        <input name="username" value="{{ old('username', $u?->username) }}" type="text" autocomplete="off" placeholder="e.g. jane" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'username'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Email</label>
        <input name="email" value="{{ old('email', $u?->email) }}" type="email" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'email'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Role *</label>
        <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @foreach ($roles as $r)
                <option value="{{ $r }}" @selected(old('role', $u?->role ?? 'cashier') === $r)>{{ ucfirst($r) }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'role'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Branch *</label>
        <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $u?->branch_id ?? auth()->user()->branch_id) === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'branch_id'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Password {{ $u ? '(leave blank to keep)' : '*' }}</label>
        <input name="password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'password'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">PIN (4-6 digits) {{ $u ? '(blank = keep)' : '' }}</label>
        <input name="pin" type="password" inputmode="numeric" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'pin'])
    </div>

    <label class="flex items-center gap-2 text-sm md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input name="is_active" value="1" type="checkbox" @checked(old('is_active', $u?->is_active ?? true)) class="rounded border-slate-300">
        Active (can log in)
    </label>
    @error('is_active') <p class="text-sm text-red-600 md:col-span-2">{{ $message }}</p> @enderror

    <div class="flex justify-end gap-2 md:col-span-2">
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Cancel</a>
        <button type="submit"
                x-bind:disabled="busy"
                class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed">
            <span x-show="!busy">Save user</span>
            <span x-show="busy" x-cloak>Saving...</span>
        </button>
    </div>
</form>