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
     * Pratinjau cetak stiker barcode (tombol Cetak, 15 stiker per lembar).
     */
    public function pdf(BarcodeCrud $crud): View
    {
        return $crud->exportBarcodesPdf();
    }

    /**
     * Halaman pindai kode batang (login role Mahasiswa).
     */
    public function publicScan(BarcodeCrud $crud): View
    {
        return $crud->showPublicBarcodeScan();
    }

    /**
     * Lookup barcode dari hasil pemindaian publik.
     */
    public function publicLookup(Request $request, BarcodeCrud $crud): JsonResponse|RedirectResponse
    {
        return $crud->lookupPublicBarcode($request);
    }

    /**
     * Halaman metadata hasil pindai kode batang.
     */
    public function publicMetadata(string $barcode, BarcodeCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicBarcodeMetadata($barcode);
    }

    /**
     * Pra-unduh lembar persetujuan dari metadata barcode (inline di tab baru).
     */
    public function publicApprovalSheet(string $barcode, BarcodeCrud $crud): View|RedirectResponse|StreamedResponse
    {
        return $crud->downloadPublicApprovalSheet($barcode);
    }

    /**
     * Status akses pemindaian kode batang publik.
     */
    public function accessStatus(BarcodeCrud $crud): JsonResponse
    {
        return $crud->barcodeAccessStatus();
    }

    /**
     * Toggle buka/tutup akses pemindaian kode batang (Petugas).
     */
    public function toggleAccess(Request $request, BarcodeCrud $crud): JsonResponse
    {
        return $crud->toggleBarcodeAccess($request);
    }
}
