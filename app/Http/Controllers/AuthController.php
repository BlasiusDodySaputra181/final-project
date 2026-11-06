<?php

namespace App\Http\Controllers;

use App\Setup\SignIn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Halaman login. Sesi peran yang sudah aktif tidak dihapus
     * agar peran lain bisa masuk di browser yang sama.
     */
    public function showLogin(SignIn $signIn): View
    {
        return $signIn->showLogin();
    }

    /**
     * Halaman daftar akun — hanya untuk bootstrap (belum ada petugas/koordinator).
     */
    public function showRegister(SignIn $signIn): View|RedirectResponse
    {
        return $signIn->showRegister();
    }

    /**
     * Simpan akun bootstrap pertama (selalu Petugas). Akun berikutnya lewat menu Pengguna.
     */
    public function register(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->register($request);
    }

    /**
     * Autentikasi name + password ke slot guard sesuai peran.
     * Guard peran lain di browser yang sama tetap aktif.
     */
    public function login(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->login($request);
    }

    /**
     * Pindah ke beranda peran yang sudah aktif di peramban ini.
     */
    public function activate(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->activate($request);
    }

    /**
     * Keluar dari satu peran, atau semua jika guard=all / tidak valid.
     */
    public function logout(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->logout($request);
    }
}
