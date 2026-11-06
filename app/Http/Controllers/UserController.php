<?php

namespace App\Http\Controllers;

use App\Setup\Crud\UserCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display the user page.
     */
    public function page(): View
    {
        return view('admin.user.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, UserCrud $crud): JsonResponse
    {
        return $crud->listUsers($request);
    }

    /**
     * Opsi akun Mahasiswa untuk select form keanggotaan.
     */
    public function options(Request $request, UserCrud $crud): JsonResponse
    {
        return $crud->listStudentOptions($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, UserCrud $crud): JsonResponse
    {
        return $crud->saveUser($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, UserCrud $crud): JsonResponse
    {
        return $crud->destroyUser($id);
    }

    /**
     * Buka / tutup akses penyerahan per akun Mahasiswa (Petugas).
     */
    public function toggleSubmissionAccess(Request $request, UserCrud $crud): JsonResponse
    {
        return $crud->toggleUserSubmissionAccess($request);
    }

    /**
     * Buka / tutup akses anggota per akun Mahasiswa (Petugas).
     */
    public function toggleMembershipAccess(Request $request, UserCrud $crud): JsonResponse
    {
        return $crud->toggleUserMembershipAccess($request);
    }
}
