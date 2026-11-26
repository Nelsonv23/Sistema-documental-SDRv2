@extends('layouts.guest')

@section('title', 'Recuperar contraseña')
@section('auth-title', 'Recuperar contraseña')
@section('auth-subtitle', 'Te enviaremos un enlace para restablecer tu acceso.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
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

        <button type="submit" class="btn btn-primary w-100">
            <i data-lucide="mail" class="ta-icon-sm"></i>
            <span>Enviar enlace de recuperación</span>
        </button>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small fw-medium d-inline-flex align-items-center gap-1">
                <i data-lucide="arrow-left" class="ta-icon-xs"></i>
                <span>Volver al inicio de sesión</span>
            </a>
        </div>
    </form>
@endsection
