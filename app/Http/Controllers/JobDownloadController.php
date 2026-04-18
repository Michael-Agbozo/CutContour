<?php

namespace App\Http\Controllers;

use App\Models\CutJob;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobDownloadController extends Controller
{
    public function download(CutJob $cutJob): StreamedResponse
    {
        Gate::authorize('download', $cutJob);

        abort_unless($cutJob->status === 'completed' && $cutJob->output_path, 404);

        return Storage::download($cutJob->output_path, $cutJob->downloadFilename());
    }
}
