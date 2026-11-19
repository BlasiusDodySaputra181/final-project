<?php

namespace App\Http\Controllers;

use App\Setup\Dashboard;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function page(Dashboard $dashboard): View
    {
        return $dashboard->page();
    }

    /**
     * Download the QR PDF. The code is an HTTPS link so a phone off the local network can sign in.
     */
    public function accessQrPdf(Dashboard $dashboard): Response
    {
        return $dashboard->accessQrPdf();
    }

    /**
     * Public QR image. The address inside the image is the application's internet link.
     */
    public function accessQrPng(Dashboard $dashboard): Response
    {
        return $dashboard->accessQrPng();
    }
}
