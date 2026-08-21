<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Http/Controllers/TaskController.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\LocalDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        return view('tasks.index', ['tasks' => $request->user()->tasks()->with('vessel')->orderBy('due_at')->get(), 'vessels' => $request->user()->vessels()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'due_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'due_time_mode' => ['nullable', 'required_with:due_at', 'in:local,utc'],
            'vessel_id' => ['nullable', 'string'],
        ]);

        $dueTimeMode = $data['due_time_mode'] ?? 'local';
        unset($data['due_time_mode']);

        if (! empty($data['due_at'])) {
            $timezone = $dueTimeMode === 'utc'
                ? 'UTC'
                : ($request->user()->timezone ?: config('app.timezone'));
            $resolution = LocalDateTime::resolve($data['due_at'], $timezone);

            if ($resolution['status'] !== LocalDateTime::VALID) {
                throw ValidationException::withMessages([
                    'due_at' => match ($resolution['status']) {
                        LocalDateTime::NONEXISTENT => "That local time does not exist in {$timezone} because the clock changes. Choose another local time or enter it as UTC.",
                        LocalDateTime::AMBIGUOUS => "That local time occurs more than once in {$timezone}. Enter the deadline as UTC so the intended instant is explicit.",
                        default => 'Enter a valid date and time.',
                    },
                ]);
            }

            $data['due_at'] = $resolution['utc'];
        }

        if (! empty($data['vessel_id'])) {
            abort_unless($request->user()->vessels()->whereKey($data['vessel_id'])->exists(), 403);
        }
        $request->user()->tasks()->create($data);

        return back()->with('status', 'Task created.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('complete', $task);
        $task->update(['status' => 'completed', 'completed_at' => now()]);

        return back()->with('status', 'Task completed.');
    }
}
