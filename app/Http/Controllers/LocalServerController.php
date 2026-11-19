<?php

namespace App\Http\Controllers;

use App\Setup\LocalServer;
use App\Setup\PortableDatabase;
use App\Support\FinalProjectStorage;
use App\Support\LocalStack;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LocalServerController extends Controller
{
    /**
     * Installation page. This computer becomes the application server.
     */
    public function page(LocalServer $server): View
    {
        return view('admin.local-server.index', $server->page());
    }

    /**
     * Installation status for the page open on this computer.
     */
    public function status(): JsonResponse
    {
        return response()->json(LocalStack::snapshot());
    }

    /**
     * Install PHP, the database, cloudflared, and shortcuts from the application folder.
     */
    public function install(Request $request, LocalServer $server): JsonResponse
    {
        $placement = [
            'copy' => $request->boolean('copy_database'),
            'adopt' => $request->boolean('adopt_database'),
            'database_mode' => (string) $request->input('database_mode', 'keep'),
            'database_file' => (string) $request->input('database_file', ''),
            'archive_mode' => (string) $request->input('archive_mode', 'keep'),
            'archive_path' => (string) $request->input('archive_path', ''),
        ];
        $error = PortableDatabase::validatePlacement($placement);

        if ($error !== null) {
            return response()->json([
                'ok' => false,
                'message' => $error,
            ], 422);
        }

        $result = $server->install($placement);

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Download a database copy for another computer.
     */
    public function export(PortableDatabase $database): BinaryFileResponse|JsonResponse
    {
        try {
            $path = $database->exportSnapshot();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Basis data tidak dapat diekspor.',
            ], 500);
        }

        return response()->download($path, 'arsip-ta-basis-data-'.now()->format('Y-m-d').'.sqlite', [
            'Content-Type' => 'application/octet-stream',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Replace the application database with a SQLite file from another installation.
     */
    public function import(Request $request, PortableDatabase $database): JsonResponse
    {
        $file = $request->file('database');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'Pilih berkas basis data SQLite.',
            ], 422);
        }

        $path = $file->getRealPath();

        if (! is_string($path) || ! PortableDatabase::isSqliteFile($path)) {
            return response()->json([
                'ok' => false,
                'message' => 'Berkas yang dipilih bukan basis data SQLite.',
            ], 422);
        }

        $result = $database->importFile($path);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * List folders and SQLite files on this computer for the setup picker.
     */
    public function browse(Request $request): JsonResponse
    {
        $path = $request->query('path');

        if (is_array($path)) {
            $path = '';
        }

        try {
            return response()->json(FinalProjectStorage::browse(
                is_string($path) ? $path : '',
                $request->boolean('files')
            ));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first() ?: 'Folder tidak dapat dibuka.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Folder tidak dapat dibuka.',
            ], 422);
        }
    }

    /**
     * Start the standalone server.
     */
    public function start(LocalServer $server): JsonResponse
    {
        $result = $server->start();

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Stop the standalone server and the public link.
     */
    public function stop(LocalServer $server): JsonResponse
    {
        $result = $server->stop();

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Stop the standalone server and remove shortcuts, the bundled PHP, and cloudflared.
     */
    public function uninstall(LocalServer $server): JsonResponse
    {
        $result = $server->uninstall();

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Create an HTTPS link so clients can sign in while this computer is online and awake.
     */
    public function share(LocalServer $server): JsonResponse
    {
        $result = $server->share();

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Stop the public link. The application on this computer can keep running.
     */
    public function unshare(LocalServer $server): JsonResponse
    {
        $result = $server->unshare();

        return response()->json($result, $result['ok'] ? 200 : 500);
    }

    /**
     * Save the permanent Cloudflare link and tunnel key used by the access QR.
     */
    public function storeCloudflare(Request $request, LocalServer $server): RedirectResponse
    {
        $result = $server->saveCloudflare(
            (string) $request->input('link', ''),
            (string) $request->input('key', ''),
            $request->boolean('clear')
        );

        $redirect = redirect()->route('page-local-server');

        if (! $result['ok']) {
            return $redirect->withInput($request->only('link'))->with('error', $result['message']);
        }

        if (! app()->runningUnitTests()) {
            $snap = LocalStack::snapshot();

            if (($snap['listening'] ?? false) && ($snap['online'] ?? false)) {
                $server->share();
            }
        }

        return $redirect->with('success', $result['message']);
    }

    /**
     * Read-only database panel for this computer. It replaces phpMyAdmin.
     */
    public function database(Request $request, LocalServer $server): View
    {
        return view('admin.local-server.database', $server->database($request));
    }
}
