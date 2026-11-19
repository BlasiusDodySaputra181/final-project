<?php

namespace App\Setup;

use App\Models\FinalProject;
use App\Models\Visitor;
use App\Support\CatalogCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard figures and the access QR PDF.
 * DashboardController only calls this class.
 */
class Dashboard
{
    public function page(): View
    {
        $year = (int) now()->year;

        $counts = Cache::remember(CatalogCache::DASHBOARD_COUNTS, 30, function () use ($year) {
            try {
                [$minYear, $maxYear] = FinalProject::validityYearBounds($year);

                $stats = FinalProject::query()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw(
                        'SUM(CASE WHEN final_project_year BETWEEN ? AND ? THEN 1 ELSE 0 END) as valid_count',
                        [$minYear, $maxYear]
                    )
                    ->first();

                $total = (int) ($stats->total ?? 0);
                $valid = (int) ($stats->valid_count ?? 0);

                return [
                    'visitors' => (int) Visitor::query()->count(),
                    'final_projects' => $total,
                    'valid' => $valid,
                    'expired' => max(0, $total - $valid),
                ];
            } catch (\Throwable $e) {
                report($e);

                return [
                    'visitors' => 0,
                    'final_projects' => 0,
                    'valid' => 0,
                    'expired' => 0,
                ];
            }
        });

        $totalVisitors = $counts['visitors'];
        $totalFinalProjects = $counts['final_projects'];
        $underTenYears = $counts['valid'];
        $overTenYears = $counts['expired'];

        $onlineLogin = app(OnlineAccessQr::class)->url() ?? '';

        return view('admin.dashboard.index', compact(
            'totalVisitors',
            'totalFinalProjects',
            'underTenYears',
            'overTenYears',
            'onlineLogin'
        ));
    }

    /**
     * Access QR PDF. The code is the public HTTPS link, not a local address.
     */
    public function accessQrPdf(): Response
    {
        $appUrl = app(OnlineAccessQr::class)->url();

        if ($appUrl === null) {
            return response()->json([
                'message' => 'Kode QR belum siap. Simpan satu tautan Worker di peladen lokal. Tautan itu tidak berubah setelah tersimpan.',
            ], 422);
        }

        return $this->qrResponse($appUrl, 'pdf');
    }

    /**
     * QR image of the Cloudflare link stored in the database.
     */
    public function accessQrPng(): Response
    {
        $appUrl = app(OnlineAccessQr::class)->url();

        if ($appUrl === null) {
            abort(404, 'Kode QR belum tersedia. Simpan satu tautan Worker di peladen lokal.');
        }

        return $this->qrResponse($appUrl, 'png');
    }

    private function qrResponse(string $appUrl, string $kind): Response
    {
        $logoPath = public_path('images/logo.png');
        $logoPath = is_file($logoPath) ? $logoPath : '';

        try {
            $body = $kind === 'png'
                ? (new AccessQrPdf)->png($appUrl, $logoPath)
                : (new AccessQrPdf)->build($appUrl, $logoPath);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Gagal membuat kode QR akses.');
        }

        if ($kind === 'png') {
            return response($body, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="kode-qr-akses-daring.png"',
                'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            ]);
        }

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kode-qr-akses-arsip-ta.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
        ]);
    }
}
