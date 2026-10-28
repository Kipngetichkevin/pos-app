@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="grid min-h-screen lg:grid-cols-2">
    {{-- Brand panel --}}
    <div class="relative hidden overflow-hidden bg-shell lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-indigo-600/30 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-900/40">
                @include('partials.icon', ['name' => 'building-storefront', 'class' => 'h-6 w-6'])
            </span>
            <span class="text-lg font-bold text-white">{{ config('app.name') }}</span>
        </div>

        <div class="relative max-w-md">
            <h2 class="text-4xl font-bold leading-tight tracking-tight text-white">Sell faster.<br>Know your numbers.</h2>
            <p class="mt-4 text-lg text-slate-400">Works with or without internet. M-Pesa, credit (deni), stock and KRA eTIMS in one place.</p>

            <ul class="mt-8 space-y-3 text-sm text-slate-300">
                @foreach (['Keeps selling when the internet is down', 'M-Pesa prompts and till payments matched automatically', 'Stock, deliveries and shift cash-ups that add up'] as $point)
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-300">
                            @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-4 w-4'])
                        </span>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-xs text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>

    {{-- Form --}}
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
                    @include('partials.icon', ['name' => 'building-storefront', 'class' => 'h-6 w-6'])
                </span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
            <p class="mb-8 mt-1 text-sm text-slate-500">Sign in to open the till.</p>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5" x-data="{ busy: false }" x-on:submit="busy = true">
                @csrf
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
                    <input name="username" id="username" type="text" value="{{ old('username') }}" autofocus autocomplete="username"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3">
                    @include('partials.error', ['field' => 'username'])
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <input name="password" id="password" type="password" autocomplete="current-password"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3">
                    @include('partials.error', ['field' => 'password'])
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input name="remember" value="1" type="checkbox">
                    Keep me signed in on this till
                </label>

                <button type="submit" x-bind:disabled="busy"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white shadow-lg shadow-indigo-600/25 hover:bg-indigo-700 disabled:opacity-60">
                    <span x-text="busy ? 'Signing in…' : 'Sign in'">Sign in</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
