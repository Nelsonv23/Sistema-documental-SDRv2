@extends('layouts.guest')

@section('title', 'Iniciar sesión')
@section('auth-title', 'Iniciar sesión')
@section('auth-subtitle', 'Ingresa tus credenciales corporativas para acceder.')

@section('content')
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="usuario@empresa.cl"
                   required
                   autofocus
                   autocomplete="username">
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="password" class="form-label mb-0">Contraseña</label>
                @if (\Illuminate\Support\Facades\Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="small fw-medium">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <input id="password"
                   type="password"
                   name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="••••••••"
                   required
                   autocomplete="current-password">
        </div>

        <div class="mb-4 form-check">
            <input id="remember"
                   type="checkbox"
                   name="remember"
                   class="form-check-input"
                   {{ old('remember') ? 'checked' : '' }}>
            <label for="remember" class="form-check-label small" style="color: var(--ta-text-muted);">
                Mantener sesión iniciada
            </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i data-lucide="log-in" class="ta-icon-sm"></i>
            <span>Ingresar al sistema</span>
        </button>
    </form>
@endsection
