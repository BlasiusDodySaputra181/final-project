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
 * Profiles for students, officers, and coordinators.
 * ProfileController calls only this class.
 */
class Profile
{
    /**
     * Student account page (/akun).
     */
    public function publicPage(Request $request): View
    {
        return view('student.profile', [
            'user' => $this->student($request),
        ]);
    }

    /**
     * Save the student profile. The form returns to the page, not a JSON response.
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
     * Officer and coordinator profile (/profil).
     */
    public function page(Request $request): View
    {
        return view('admin.profile.index', [
            'user' => $this->admin($request),
        ]);
    }

    /**
     * Save the officer or coordinator profile.
     * AJAX requests return JSON. A normal form post returns to the profile page.
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
                'email' => 'Surel sudah terdaftar!',
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
            'email.required' => 'Surel harus diisi!',
            'email.email' => 'Format surel tidak valid!',
            'email.max' => 'Surel maksimal 255 karakter!',
            'email.unique' => 'Surel sudah terdaftar!',
            'password.min' => 'Kata sandi minimal 8 karakter!',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok!',
        ];
    }

    /**
     * Digits that match after spaces and punctuation are removed count as one identity.
     * Login uses the same rule.
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
