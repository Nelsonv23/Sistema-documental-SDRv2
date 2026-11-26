@extends('layouts.guest')

@section('title', 'Restablecer contraseña')
@section('auth-title', 'Restablecer contraseña')
@section('auth-subtitle', 'Define una contraseña segura para tu cuenta.')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email"
                   type="email"
                   name="email"
                   value="{{ old('email', $request->email) }}"
                   class="form-control @error('email') is-invalid @enderror"
                   required
                   autofocus
                   autocomplete="username">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input id="password"
                   type="password"
                   name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required
                   autocomplete="new-password">
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
            <input id="password_confirmation"
                   type="password"
                   name="password_confirmation"
                   class="form-control"
                   required
                   autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i data-lucide="key-round" class="ta-icon-sm"></i>
            <span>Restablecer contraseña</span>
        </button>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small fw-medium">
                Volver al inicio de sesión
            </a>
        </div>
    </form>
@endsection
