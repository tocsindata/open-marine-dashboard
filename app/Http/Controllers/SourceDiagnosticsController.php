<?php

namespace App\Http\Controllers;

use App\Models\SourceImportRun;
use App\Models\SourceImportStep;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceDiagnosticsController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('admin.source-diagnostics', [
            'steps' => SourceImportStep::query()->with('user:id,email')->orderBy('provider_key')->orderBy('scope_key')->get(),
            'runs' => SourceImportRun::query()->latest('started_at')->limit(100)->get(),
        ]);
    }
}
