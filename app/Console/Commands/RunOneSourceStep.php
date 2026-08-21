<?php

namespace App\Console\Commands;

use App\Models\SourceImportRun;
use App\Models\SourceImportStep;
use App\Services\MarineSourceImporter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RunOneSourceStep extends Command
{
    protected $signature = 'marine:sources:tick {--provider= : Limit the tick to one provider} {--scope= : Limit the tick to one scope key} {--force : Run the selected step even when it is not due}';

    protected $description = 'Run at most one due marine source import step';

    public function handle(MarineSourceImporter $importer): int
    {
        $lock = Cache::lock('marine:sources:tick', 55);

        if (! $lock->get()) {
            $this->line('Another source tick is already running.');

            return self::SUCCESS;
        }

        try {
            $step = SourceImportStep::query()
                ->where('enabled', true)
                ->when($this->option('provider'), fn ($query, $provider) => $query->where('provider_key', $provider))
                ->when($this->option('scope'), fn ($query, $scope) => $query->where('scope_key', $scope))
                ->when(! $this->option('force'), fn ($query) => $query->where(fn ($due) => $due->whereNull('next_due_at')->orWhere('next_due_at', '<=', now())))
                ->where(fn ($query) => $query->whereNull('locked_at')->orWhere('locked_at', '<', now()->subMinutes(10)))
                ->orderByRaw('next_due_at IS NOT NULL')
                ->orderBy('next_due_at')
                ->first();

            if (! $step) {
                $this->line('No source step is due.');

                return self::SUCCESS;
            }

            $step->update(['locked_at' => now(), 'last_started_at' => now()]);
            $run = SourceImportRun::create([
                'source_import_step_id' => $step->id,
                'provider_key' => $step->provider_key,
                'user_id' => $step->user_id,
                'result' => 'running',
                'started_at' => now(),
            ]);

            try {
                $result = $importer->import($step);
                $run->update($result + ['result' => 'success', 'completed_at' => now()]);
                $step->update([
                    'locked_at' => null,
                    'last_completed_at' => now(),
                    'last_result' => 'success',
                    'last_message' => "{$result['records_written']} records written.",
                    'consecutive_failures' => 0,
                    'next_due_at' => now()->addMinutes($step->interval_minutes),
                ]);
                $this->info("Completed {$step->provider_key}; {$result['records_written']} records written.");

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $failures = $step->consecutive_failures + 1;
                $message = $this->safeFailureMessage($exception);
                report(new RuntimeException("Marine source {$step->provider_key}: {$message}"));
                $run->update(['result' => 'failed', 'message' => $message, 'completed_at' => now()]);
                $step->update([
                    'locked_at' => null,
                    'last_completed_at' => now(),
                    'last_result' => 'failed',
                    'last_message' => $message,
                    'consecutive_failures' => $failures,
                    'next_due_at' => now()->addMinutes(min(360, max($step->interval_minutes, 2 ** min($failures, 8)))),
                ]);
                $this->error("{$step->provider_key} failed: {$message}");

                return self::FAILURE;
            }
        } finally {
            $lock->release();
        }
    }

    private function safeFailureMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            $status = $exception->response?->status();

            return $status ? "Provider request failed (HTTP {$status})." : 'Provider request failed.';
        }

        return 'Provider import failed ('.class_basename($exception).').';
    }
}
