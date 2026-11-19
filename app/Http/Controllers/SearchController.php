<?php

namespace App\Http\Controllers;

use App\Setup\Crud\SearchCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Student final project search. Data comes from final__projects.
     */
    public function publicSearch(SearchCrud $crud): View
    {
        return $crud->showPublicSearch();
    }

    /**
     * Server-side DataTables index for the Search module.
     */
    public function publicSearchIndex(Request $request, SearchCrud $crud): JsonResponse
    {
        return $crud->listPublicSearch($request);
    }
}
