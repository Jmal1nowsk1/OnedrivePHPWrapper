<?php

namespace App\Http\Controllers;

use App\Services\OneDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OneDriveController extends Controller
{
    public function __construct(private readonly OneDriveService $oneDrive) {}

    /**
     * POST /api/mkdir
     * Body JSON: { "directory": "..." }
     */
    public function mkdir(Request $request): JsonResponse
    {
        $data = $request->validate([
            'directory' => 'required|string',
        ]);

        $dir = $data['directory'];

        if ($this->oneDrive->directoryExists($dir)) {
            return response()->json(['success' => false, 'error' => 'Katalog już istnieje na OD.']);
        }

        return response()->json($this->oneDrive->mkdir($dir));
    }

    /**
     * GET /api/exists?directory=...
     */
    public function exists(Request $request): JsonResponse
    {
        $request->validate([
            'directory' => 'required|string',
        ]);

        $result = $this->oneDrive->directoryExists($request->query('directory'));

        return response()->json($result);
    }

    /**
     * GET /api/list?directory=...&depth=infinity
     */
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'directory' => 'required|string',
            'depth'     => 'sometimes|string',
        ]);

        $result = $this->oneDrive->listDirectories(
            $request->query('directory'),
            $request->query('depth', 'infinity')
        );

        return response()->json($result);
    }

    /**
     * POST /api/upload
     * Multipart: file (binary), directory (string)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file',
            'directory' => 'sometimes|string',
        ]);

        $file      = $request->file('file');
        $directory = $request->input('directory', '');
        $result    = $this->oneDrive->saveFile($file->getRealPath(), $directory, $file->getClientOriginalName());

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Plik zapisany']);
        }

        return response()->json($result, 500);
    }

    /**
     * DELETE /api/delete
     * Body JSON: { "path": "..." }
     */
    public function delete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'required|string',
        ]);

        return response()->json($this->oneDrive->deleteFile($data['path']));
    }
}

