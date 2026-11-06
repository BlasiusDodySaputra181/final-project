<?php

namespace App\Setup\Crud;

use App\Models\Setting;
use App\Models\StudyProgram;
use App\Models\Visitor;
use App\Support\CatalogCache;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * CRUD Pengunjung, formulir publik, dan akses kunjungan.
 * Anak dari FinalProjectTypeCrud. Controller: VisitorController.
 */
class VisitorCrud extends FinalProjectTypeCrud
{
    // --- Daftar admin dan formulir publik ---

    public function listVisitors(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            Visitor::query()->select([
                'id', 'nim', 'student_name', 'program_study', 'needs',
                'supervision', 'user_id', 'created_at', 'updated_at',
            ]),
            fn (Visitor $visitor, int $number) => [
                $number,
                $visitor->id,
                $visitor->nim,
                $visitor->student_name,
                $visitor->program_study,
                $visitor->needs,
                $visitor->supervision,
                $this->dateLabel($visitor->created_at),
                $this->dateLabel($visitor->updated_at),
                $visitor->user_id,
            ],
            [
                'id' => 'id',
                'nim' => 'nim',
                'student_name' => 'student_name',
                'program_study' => 'program_study',
                'needs' => 'needs',
                'supervision' => 'supervision',
            ],
            ['nim', 'student_name', 'program_study', 'needs', 'supervision']
        );
    }

    public function saveVisitor($request): JsonResponse
    {
        $authUser = $request->user();

        $request->validate([
            'visitorid' => ['nullable', 'integer', 'exists:visitors,id'],
            'nim' => ['required', 'string', 'max:50'],
            'student_name' => ['required', 'string', 'max:255'],
            'program_study' => ['required', 'string', 'max:255'],
            'needs' => ['required', 'string', 'max:65535'],
        ]);

        $this->updateOrCreateVisitor(
            $request->visitorid,
            $request->nim,
            $request->student_name,
            $request->program_study,
            $request->needs,
            $authUser->name,
            $authUser->id
        );

        return response()->json('Data Pengunjung Berhasil Disimpan');
    }

    /**
     * Toggle pengawasan pengunjung (checkbox datatable) — hanya Petugas.
     */
    public function superviseVisitors($request): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser?->isOfficer()) {
            return response()->json('Hanya Petugas yang dapat mengatur pengawasan pengunjung.', 403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:visitors,id'],
            'supervised' => ['required', 'boolean'],
        ]);

        $supervised = (bool) $validated['supervised'];

        Visitor::query()
            ->whereIn('id', $validated['ids'])
            ->update([
                'supervision' => $supervised ? $authUser->name : 'Mandiri',
                'user_id' => $supervised ? $authUser->id : null,
            ]);

        DataTables::touch();
        CatalogCache::forgetDashboard();

        return response()->json(
            $supervised
                ? 'Pengawasan pengunjung berhasil ditetapkan'
                : 'Pengawasan pengunjung dibatalkan'
        );
    }

    /**
     * Halaman formulir kunjungan publik (tanpa login).
     */
    public function showPublicVisitorForm(): View
    {
        if (! Setting::isVisitorAccessOpen()) {
            return view('student.access', [
                'errorMessage' => 'Akses formulir kunjungan sedang ditutup oleh petugas.',
            ]);
        }

        return view('student.visitor', [
            'studyPrograms' => CatalogCache::studyPrograms(),
            'visitorCountLabel' => format_count_label((int) Cache::remember(
                CatalogCache::VISITOR_COUNT,
                60,
                static fn () => Visitor::count()
            )),
            'studyProgramCount' => format_count_label((int) Cache::remember(
                CatalogCache::STUDY_PROGRAM_COUNT,
                300,
                static fn () => StudyProgram::count()
            )),
            'visitorAccessOpen' => Setting::isVisitorAccessOpen(),
        ]);
    }

    /**
     * Simpan daftar kunjungan dari formulir publik.
     */
    public function savePublicVisitor(Request $request): RedirectResponse
    {
        if (! Setting::isVisitorAccessOpen()) {
            return redirect()
                ->route('page-public-visitor')
                ->with('error', 'Akses formulir kunjungan sedang ditutup.');
        }

        $validated = $request->validate([
            'nim' => ['required', 'string', 'max:50'],
            'student_name' => ['required', 'string', 'min:3', 'max:255'],
            'program_study' => ['required', 'string', 'max:255', 'exists:study_programs,study_program'],
            'needs' => ['required', 'string', 'max:65535'],
        ], [
            'nim.required' => 'NIM / KTP harus diisi!',
            'nim.max' => 'NIM / KTP maksimal 50 karakter!',
            'student_name.required' => 'Nama mahasiswa harus diisi!',
            'student_name.min' => 'Nama mahasiswa minimal 3 karakter!',
            'student_name.max' => 'Nama mahasiswa maksimal 255 karakter!',
            'program_study.required' => 'Program studi harus dipilih!',
            'program_study.exists' => 'Program studi tidak valid!',
            'needs.required' => 'Keperluan harus diisi!',
            'needs.max' => 'Keperluan maksimal 65535 karakter!',
        ]);

        try {
            $this->updateOrCreateVisitor(
                null,
                $validated['nim'],
                $validated['student_name'],
                $validated['program_study'],
                $validated['needs'],
                'Mandiri'
            );
        } catch (\Throwable) {
            return redirect()
                ->route('page-public-visitor')
                ->withInput()
                ->with('error', 'Gagal menyimpan daftar kunjungan. Silakan coba lagi.');
        }

        return redirect()
            ->route('page-public-visitor')
            ->with('success', 'Daftar kunjungan berhasil disimpan.');
    }

    // --- Status akses dan hapus ---

    public function visitorAccessStatus(): JsonResponse
    {
        return $this->accessStatusResponse([Setting::class, 'isVisitorAccessOpen']);
    }

    /**
     * Buka / tutup akses formulir kunjungan publik.
     */
    public function toggleVisitorAccess(Request $request): JsonResponse
    {
        return $this->toggleAccessResponse(
            $request,
            [Setting::class, 'setVisitorAccessOpen'],
            [Setting::class, 'isVisitorAccessOpen'],
            'Akses formulir kunjungan dibuka.',
            'Akses formulir kunjungan ditutup.'
        );
    }

    public function destroyVisitor($id): JsonResponse
    {
        if ($this->deleteVisitor((int) $id) === 0) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Pengunjung Berhasil');
    }
}
