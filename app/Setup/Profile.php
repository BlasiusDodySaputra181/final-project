<?php

namespace App\Setup;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Profil mahasiswa dan petugas/koordinator.
 * ProfileController hanya memanggil kelas ini.
 */
class Profile
{
    /**
     * Halaman akun mahasiswa (/akun).
     */
    public function publicPage(Request $request): View
    {
        return view('student.profile', [
            'user' => $this->student($request),
        ]);
    }

    /**
     * Simpan profil mahasiswa. Formulir kembali ke halaman, bukan JSON.
     */
    public function publicStore(Request $request): RedirectResponse
    {
        try {
            $this->persist($request, $this->student($request), true);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('page-public-profile')
                ->with('error', 'Profil gagal disimpan. Coba lagi.');
        }

        return redirect()
            ->route('page-public-profile')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Profil Petugas / Koordinator (/profil).
     */
    public function page(Request $request): View
    {
        return view('admin.profile.index', [
            'user' => $this->admin($request),
        ]);
    }

    /**
     * Simpan profil Petugas / Koordinator.
     * Permintaan AJAX membalas JSON; kiriman form biasa kembali ke halaman profil.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $user = $this->persist($request, $this->admin($request), false);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $message = 'Profil gagal disimpan. Coba lagi.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()
                ->route('page-profile')
                ->with('error', $message);
        }

        $message = 'Profil berhasil diperbarui.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'identity_number' => $user->identity_number,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);
        }

        return redirect()
            ->route('page-profile')
            ->with('success', $message);
    }

    private function student(Request $request): User
    {
        $user = $request->user('student');

        if (! $user instanceof User || ! $user->isStudent()) {
            abort(403);
        }

        return $user;
    }

    private function admin(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }

        return $user;
    }

    private function persist(Request $request, User $user, bool $identityRequired): User
    {
        $identity = trim((string) $request->input('identity_number', ''));

        $request->merge([
            'identity_number' => $identity === '' ? null : $identity,
            'name' => trim((string) $request->input('name', '')),
            'email' => trim((string) $request->input('email', '')),
        ]);

        $validated = $request->validate(
            $this->rules($user, $identityRequired),
            $this->messages($identityRequired),
        );

        $attributes = [
            'identity_number' => $validated['identity_number'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        $password = $validated['password'] ?? null;

        if (is_string($password) && $password !== '') {
            $attributes['password'] = $password;
        }

        try {
            $user->fill($attributes)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah terdaftar!',
            ]);
        }

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $user, bool $identityRequired): array
    {
        $label = $identityRequired ? 'NIM' : 'NIP';

        return [
            'identity_number' => [
                $identityRequired ? 'required' : 'nullable',
                'string',
                'max:50',
                'regex:/^[0-9\s.\/-]+$/',
                function (string $attribute, mixed $value, Closure $fail) use ($user, $label): void {
                    if ($this->identityTaken($user, is_string($value) ? $value : null)) {
                        $fail($label.' sudah digunakan akun lain!');
                    }
                },
            ],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(bool $identityRequired): array
    {
        $label = $identityRequired ? 'NIM' : 'NIP';

        return [
            'identity_number.required' => $label.' harus diisi!',
            'identity_number.max' => $label.' maksimal 50 karakter!',
            'identity_number.regex' => $label.' hanya boleh berisi angka, spasi, titik, garis miring, atau tanda hubung!',
            'name.required' => 'Nama harus diisi!',
            'name.min' => 'Nama minimal 3 karakter!',
            'name.max' => 'Nama maksimal 255 karakter!',
            'email.required' => 'Email harus diisi!',
            'email.email' => 'Format email tidak valid!',
            'email.max' => 'Email maksimal 255 karakter!',
            'email.unique' => 'Email sudah terdaftar!',
            'password.min' => 'Kata sandi minimal 8 karakter!',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok!',
        ];
    }

    /**
     * Angka yang sama setelah spasi dan tanda baca dihapus dianggap satu identitas,
     * sama seperti pencocokan login.
     */
    private function identityTaken(User $user, ?string $identity): bool
    {
        if ($identity === null || $identity === '') {
            return false;
        }

        $digits = Account::identityDigits($identity) ?? '';
        $query = User::query()->whereKeyNot($user->id);

        if (strlen($digits) < 8) {
            return $query->where('identity_number', $identity)->exists();
        }

        return $query->where(function ($builder) use ($identity, $digits): void {
            $builder->where('identity_number', $identity)
                ->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(identity_number, ''), ' ', ''), '.', ''), '/', ''), '-', ''), ',', '') = ?",
                    [$digits]
                );
        })->exists();
    }
}
