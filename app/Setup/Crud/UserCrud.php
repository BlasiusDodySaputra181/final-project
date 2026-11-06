<?php

namespace App\Setup\Crud;

use App\Models\Setting;
use App\Models\User;
use App\Support\CatalogCache;
use App\Support\DataTables;
use App\Support\KeywordSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD Pengguna dan akses modul per akun mahasiswa.
 * Anak dari MembershipCrud. Controller: UserController.
 */
class UserCrud extends MembershipCrud
{
    // --- Daftar, simpan, dan hapus ---

    /**
     * Opsi akun Mahasiswa untuk select form keanggotaan.
     */
    public function listStudentOptions(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q', ''));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('ids', [])),
            static fn (int $id): bool => $id > 0
        )));
        $limit = min(50, max(1, (int) $request->input('limit', 30)));

        $query = User::query()
            ->select(['id', 'identity_number', 'name'])
            ->where('role', User::ROLE_STUDENT)
            ->orderBy('name');

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                if (preg_match('/^\d+$/', $keyword) === 1) {
                    $builder->where('identity_number', 'like', KeywordSearch::prefixPattern($keyword));

                    return;
                }

                $like = KeywordSearch::likePattern($keyword);
                $builder->where('name', 'like', $like)
                    ->orWhere('identity_number', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $rows = $query->limit($limit)->get();

        if ($ids !== []) {
            $missing = array_values(array_diff($ids, $rows->pluck('id')->all()));

            if ($missing !== []) {
                $extra = User::query()
                    ->select(['id', 'identity_number', 'name'])
                    ->where('role', User::ROLE_STUDENT)
                    ->whereIn('id', $missing)
                    ->get();

                $rows = $extra->concat($rows);
            }
        }

        return response()->json($rows->map(static fn (User $user): array => [
            'id' => $user->id,
            'value' => $user->id,
            'label' => trim(($user->identity_number ?: '-').' — '.($user->name ?: '-')),
        ])->values());
    }

    public function listUsers(Request $request): JsonResponse
    {
        $closed = array_fill_keys(Setting::closedSubmissionUserIds(), true);
        $closedMembership = array_fill_keys(Setting::closedMembershipUserIds(), true);

        return DataTables::make(
            $request,
            User::query()->select([
                'id',
                'identity_number',
                'name',
                'email',
                'role',
                'created_at',
                'updated_at',
            ]),
            function (User $user, int $number) use ($closed, $closedMembership) {
                $isStudent = $user->role === User::ROLE_STUDENT;

                return [
                    $number,
                    $user->id,
                    $this->dash($user->identity_number),
                    $user->name,
                    $user->email,
                    $this->dash($user->role),
                    $this->dateLabel($user->created_at),
                    $this->dateLabel($user->updated_at),
                    ($isStudent && isset($closed[(int) $user->id])) ? 0 : 1,
                    ($isStudent && isset($closedMembership[(int) $user->id])) ? 0 : 1,
                ];
            },
            [
                'id' => 'id',
                'identity_number' => 'identity_number',
                'name' => 'name',
                'email' => 'email',
                'role' => 'role',
            ],
            ['identity_number', 'name', 'email', 'role']
        );
    }

    public function saveUser($request): JsonResponse
    {
        $isCreate = ! $request->filled('userid');

        $request->validate([
            'userid' => ['nullable', 'integer', 'exists:users,id'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'name' => [
                'required',
                'string',
                'max:255',
                $isCreate
                    ? 'unique:users,name'
                    : Rule::unique('users', 'name')->ignore($request->integer('userid')),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                $isCreate
                    ? 'unique:users,email'
                    : 'unique:users,email,'.$request->integer('userid'),
            ],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:Petugas,Koordinator,Mahasiswa'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.unique' => 'Nama sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'role.required' => 'Peran wajib dipilih.',
            'role.in' => 'Peran tidak valid.',
        ]);

        $user = $this->updateOrCreateUser(
            $request->userid,
            $request->identity_number,
            $request->name,
            $request->email,
            $request->password,
            $request->role
        );

        if ($user) {
            if ($user->isStudent()) {
                Setting::setUserSubmissionAccessOpen((int) $user->id, false);
                Setting::setUserMembershipAccessOpen((int) $user->id, false);
            } else {
                Setting::forgetUserSubmissionAccess((int) $user->id);
                Setting::forgetUserMembershipAccess((int) $user->id);
            }
        }

        $this->userIdByName = null;
        CatalogCache::forgetSignatories();

        return response()->json('Data Pengguna Berhasil Disimpan');
    }

    public function destroyUser($id): JsonResponse
    {
        $id = (int) $id;

        if (Auth::id() === $id) {
            return response()->json('Tidak dapat menghapus akun yang sedang digunakan.', 422);
        }

        if (! $this->deleteUser($id)) {
            return response()->json('Hapus Gagal', 404);
        }

        Setting::forgetUserSubmissionAccess($id);
        Setting::forgetUserMembershipAccess($id);

        $this->userIdByName = null;
        CatalogCache::forgetSignatories();

        return response()->json('Hapus Pengguna Berhasil');
    }

    // --- Akses serah dan anggota per akun ---

    /**
     * Buka / tutup akses penyerahan per akun Mahasiswa — hanya Petugas.
     */
    public function toggleUserSubmissionAccess(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser?->isOfficer()) {
            return response()->json('Hanya petugas yang dapat mengatur akses penyerahan mahasiswa.', 403);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'open' => ['required', 'boolean'],
        ]);

        $user = $this->findUserById((int) $validated['id']);

        if (! $user?->isStudent()) {
            return response()->json('Akses penyerahan hanya dapat diatur untuk akun Mahasiswa.', 422);
        }

        Setting::setUserSubmissionAccessOpen($user->id, $request->boolean('open'));

        $open = Setting::isUserSubmissionAccessOpen($user->id);

        return response()->json([
            'open' => $open,
            'message' => $open
                ? 'Akses penyerahan untuk akun ini dibuka.'
                : 'Akses penyerahan untuk akun ini ditutup.',
        ]);
    }

    /**
     * Buka / tutup akses anggota per akun Mahasiswa — hanya Petugas.
     */
    public function toggleUserMembershipAccess(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser?->isOfficer()) {
            return response()->json('Hanya petugas yang dapat mengatur akses keanggotaan mahasiswa.', 403);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'open' => ['required', 'boolean'],
        ]);

        $user = $this->findUserById((int) $validated['id']);

        if (! $user?->isStudent()) {
            return response()->json('Akses keanggotaan hanya dapat diatur untuk akun Mahasiswa.', 422);
        }

        Setting::setUserMembershipAccessOpen($user->id, $request->boolean('open'));

        $open = Setting::isUserMembershipAccessOpen($user->id);

        return response()->json([
            'open' => $open,
            'message' => $open
                ? 'Akses keanggotaan untuk akun ini dibuka.'
                : 'Akses keanggotaan untuk akun ini ditutup.',
        ]);
    }
}
