<?php

namespace App\Http\Controllers;

use App\Setup\Preference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Pengaturan global folder berkas TA dan akses mahasiswa.
     */
    public function page(Preference $preference): View
    {
        return $preference->page();
    }

    /**
     * Simpan lokasi folder penyimpanan berkas TA & lembar persetujuan.
     */
    public function store(Request $request, Preference $preference): JsonResponse
    {
        return $preference->store($request);
    }

    /**
     * Progres real-time pemindahan folder saat lokasi berkas disimpan.
     */
    public function moveProgress(Request $request, Preference $preference): JsonResponse
    {
        return $preference->moveProgress($request);
    }

    /**
     * Daftar subfolder di server untuk pemilih lokasi.
     */
    public function browse(Request $request, Preference $preference): JsonResponse
    {
        return $preference->browse($request);
    }
}
