<?php

namespace App\Http\Controllers;

use App\Setup\Crud\SearchCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Modul cari tugas akhir (role Mahasiswa) — data dari final__projects.
     */
    public function publicSearch(SearchCrud $crud): View
    {
        return $crud->showPublicSearch();
    }

    /**
     * DataTables server-side index untuk modul Cari.
     */
    public function publicSearchIndex(Request $request, SearchCrud $crud): JsonResponse
    {
        return $crud->listPublicSearch($request);
    }
}
