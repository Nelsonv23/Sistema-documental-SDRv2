<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->loadMissing('roles'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->forceFill([
            'name' => trim($validated['name']),
        ])->save();

        $this->audit->log('profile.updated', $request->user(), [
            'name' => $request->user()->name,
        ], $request->user());

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Datos personales actualizados correctamente.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $file = $request->file('avatar');
        if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
            return back()->withErrors([
                'avatar' => 'No se pudo procesar el archivo seleccionado. Verifica que la imagen no esté vacía o dañada.',
            ]);
        }

        $realPath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath) || ! is_readable($realPath) || (int) @filesize($realPath) <= 0) {
            return back()->withErrors([
                'avatar' => 'No se pudo procesar el archivo seleccionado. Verifica que la imagen no esté vacía o dañada.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $newPath = 'avatars/'.$filename;

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            return back()->withErrors([
                'avatar' => 'No se pudo procesar el archivo seleccionado. Verifica que la imagen no esté vacía o dañada.',
            ]);
        }

        try {
            $written = Storage::disk('local')->put($newPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written || ! Storage::disk('local')->exists($newPath)) {
            Storage::disk('local')->delete($newPath);

            return back()->with('error', 'No se pudo guardar la foto de perfil. Intenta nuevamente.');
        }

        $user = $request->user();
        $previousPath = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $newPath,
        ])->save();

        if ($previousPath && $previousPath !== $newPath && Storage::disk('local')->exists($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        $this->audit->log('profile.avatar_updated', $user, ['action_type' => 'uploaded'], $user);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Foto de perfil actualizada.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $previousPath = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => null,
        ])->save();

        if ($previousPath && Storage::disk('local')->exists($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        $this->audit->log('profile.avatar_updated', $user, ['action_type' => 'deleted'], $user);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Foto de perfil eliminada.');
    }

    public function updatePassword(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update(
            $request->user(),
            $request->only(['current_password', 'password', 'password_confirmation'])
        );

        $this->audit->log('profile.password_changed', $request->user(), [], $request->user());

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Contraseña actualizada correctamente.');
    }

    public function showAvatar(Request $request, User $user): Response|StreamedResponse
    {
        // Liberar bloqueo de sesión antes de servir archivos binarios o SVG.
        $request->session()->save();

        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            return Storage::disk('local')->response($user->avatar_path, null, [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $initials = e($user->initials());
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128" role="img" aria-label="{$initials}">
  <rect width="128" height="128" rx="64" fill="#0049A4"/>
  <text x="50%" y="52%" dominant-baseline="central" text-anchor="middle" fill="#FFFFFF" font-family="Arial, Helvetica, sans-serif" font-size="46" font-weight="600">{$initials}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
