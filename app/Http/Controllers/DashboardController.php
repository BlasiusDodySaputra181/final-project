<?php

namespace App\Http\Controllers;

use App\Setup\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function page(Dashboard $dashboard): View
    {
        return $dashboard->page();
    }

    /**
     * Unduh PDF QR code akses aplikasi (logo UNJ di tengah).
     * Isi QR adalah tautan login dari host yang sedang membuka sistem.
     */
    public function accessQrPdf(Request $request, Dashboard $dashboard): Response
    {
        return $dashboard->accessQrPdf($request);
    }
}
