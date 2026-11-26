<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'request' => $request,
        ]));

        Fortify::authenticateUsing(function (Request $request) {
            $email = Str::lower((string) $request->input(Fortify::username()));
            $user = User::where('email', $email)->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if ($user->is_active === false) {
                app(AuditLogger::class)->log('login_failed', $user, [
                    'email' => $email,
                    'reason' => 'account_inactive',
                ], $user);

                throw ValidationException::withMessages([
                    Fortify::username() => ['Tu cuenta está desactivada. Contacta al administrador del sistema.'],
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill([
                    'last_login_at' => now(),
                ])->saveQuietly();

                app(AuditLogger::class)->log('login', $event->user, [], $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $actor = $event->user instanceof User ? $event->user : null;
            $attemptedEmail = isset($event->credentials['email'])
                ? Str::lower((string) $event->credentials['email'])
                : null;

            app(AuditLogger::class)->log('login_failed', $actor, array_filter([
                'email' => $attemptedEmail,
                'reason' => 'invalid_credentials',
            ]), $actor);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->log('logout', $event->user, [], $event->user);
            }
        });
    }
}
