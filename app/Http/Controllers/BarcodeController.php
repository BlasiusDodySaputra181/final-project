<?php

namespace App\Http\Controllers;

use App\Setup\Crud\BarcodeCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarcodeController extends Controller
{
    /**
     * Display the barcode page.
     */
    public function page(): View
    {
        return view('admin.barcode.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, BarcodeCrud $crud): JsonResponse
    {
        return $crud->listBarcodes($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, BarcodeCrud $crud): JsonResponse
    {
        return $crud->saveBarcode($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, BarcodeCrud $crud): JsonResponse
    {
        return $crud->destroyBarcode($id);
    }

    /**
     * Barcode sticker print preview (Cetak button, 15 stickers per sheet).
     */
    public function pdf(BarcodeCrud $crud): View
    {
        return $crud->exportBarcodesPdf();
    }

    /**
     * Barcode scan page (signed-in student).
     */
    public function publicScan(BarcodeCrud $crud): View
    {
        return $crud->showPublicBarcodeScan();
    }

    /**
     * Look up a barcode from a public scan.
     */
    public function publicLookup(Request $request, BarcodeCrud $crud): JsonResponse|RedirectResponse
    {
        return $crud->lookupPublicBarcode($request);
    }

    /**
     * Metadata page for a barcode scan.
     */
    public function publicMetadata(string $barcode, BarcodeCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicBarcodeMetadata($barcode);
    }

    /**
     * Preview the approval sheet from barcode metadata (inline in a new tab).
     */
    public function publicApprovalSheet(string $barcode, BarcodeCrud $crud): View|RedirectResponse|StreamedResponse
    {
        return $crud->downloadPublicApprovalSheet($barcode);
    }

    /**
     * Public barcode scan access status.
     */
    public function accessStatus(BarcodeCrud $crud): JsonResponse
    {
        return $crud->barcodeAccessStatus();
    }

    /**
     * Toggle public barcode scanning (officer).
     */
    public function toggleAccess(Request $request, BarcodeCrud $crud): JsonResponse
    {
        return $crud->toggleBarcodeAccess($request);
    }
}
