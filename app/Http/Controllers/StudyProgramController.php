<?php

namespace App\Http\Controllers;

use App\Setup\Crud\StudyProgramCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudyProgramController extends Controller
{
    /**
     * Display the study program page.
     */
    public function page(): View
    {
        return view('admin.studyprogram.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, StudyProgramCrud $crud): JsonResponse
    {
        return $crud->listStudyPrograms($request);
    }

    /**
     * List study programs as select options for other forms.
     */
    public function options(StudyProgramCrud $crud): JsonResponse
    {
        return $crud->listStudyProgramOptions();
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, StudyProgramCrud $crud): JsonResponse
    {
        return $crud->saveStudyProgram($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, StudyProgramCrud $crud): JsonResponse
    {
        return $crud->destroyStudyProgram($id);
    }
}
