<?php

namespace App\Setup;

use App\Models\FinalProject;
use App\Models\Visitor;
use App\Support\AccessUrl;
use App\Support\CatalogCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Angka dasbor dan PDF QR akses.
 * DashboardController hanya memanggil kelas ini.
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

        return view('admin.dashboard.index', compact(
            'totalVisitors',
            'totalFinalProjects',
            'underTenYears',
            'overTenYears'
        ));
    }

    /**
     * Unduh PDF QR code akses aplikasi (logo UNJ di tengah).
     * Isi QR adalah tautan login dari host yang sedang membuka sistem.
     */
    public function accessQrPdf(Request $request): Response
    {
        $appUrl = AccessUrl::login($request);
        $logoPath = public_path('images/logo.png');
        try {
            $pdf = (new AccessQrPdf)->build($appUrl, is_file($logoPath) ? $logoPath : '');
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Gagal membuat PDF QR code akses.');
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="qrcode-akses-arsip-ta.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Vary' => 'Host, X-Forwarded-Host, X-Forwarded-Proto',
        ]);
    }
}
