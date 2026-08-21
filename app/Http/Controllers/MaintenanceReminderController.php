<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MaintenanceReminderOccurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MaintenanceReminderController extends Controller
{
    private const REFERENCE_TYPES = [
        'calendar_days' => 'Calendar-day',
        'annual_date' => 'Annual-date',
        'meter_hours' => 'Meter-hour',
    ];

    private const HISTORY_STATES = [
        'open' => 'Open',
        'acknowledged' => 'Acknowledged',
        'dismissed' => 'Dismissed',
        'suppressed' => 'Suppressed',
        'superseded' => 'Superseded',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $selectedVesselId = $this->nullableQueryString($request, 'vessel');
        $selectedReferenceType = $this->nullableQueryString($request, 'reference_type');

        abort_if(
            $selectedReferenceType !== null && ! array_key_exists($selectedReferenceType, self::REFERENCE_TYPES),
            404,
        );

        $activeVessels = $user->vessels()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedVessel = $selectedVesselId === null
            ? null
            : $activeVessels->firstWhere('id', $selectedVesselId);

        abort_if($selectedVesselId !== null && $selectedVessel === null, 404);

        $openOccurrences = MaintenanceReminderOccurrence::query()
            ->ownedBy($user)
            ->whereHas(
                'schedule.vessel',
                fn (Builder $vessels): Builder => $vessels->where('status', 'active'),
            )
            ->where('status', 'open');
        $openReminderCountsByType = [];
        foreach (array_keys(self::REFERENCE_TYPES) as $referenceType) {
            $openReminderCountsByType[$referenceType] = (clone $openOccurrences)
                ->where('reference_type', $referenceType)
                ->count();
        }

        $filteredOccurrences = clone $openOccurrences;

        if ($selectedVessel !== null) {
            $filteredOccurrences->whereHas(
                'schedule',
                fn (Builder $schedules): Builder => $schedules->where('vessel_id', $selectedVessel->id),
            );
        }

        if ($selectedReferenceType !== null) {
            $filteredOccurrences->where('reference_type', $selectedReferenceType);
        }

        return view('maintenance.reminder-inbox', [
            'activeVessels' => $activeVessels,
            'referenceTypes' => self::REFERENCE_TYPES,
            'selectedVessel' => $selectedVessel,
            'selectedReferenceType' => $selectedReferenceType,
            'openReminderCount' => (clone $openOccurrences)->count(),
            'openReminderCountsByType' => $openReminderCountsByType,
            'filteredOpenReminderCount' => (clone $filteredOccurrences)->count(),
            'occurrences' => $filteredOccurrences
                ->with([
                    'schedule' => fn ($schedules) => $schedules->select('id', 'vessel_id', 'name'),
                    'schedule.vessel' => fn ($vessels) => $vessels->select('id', 'name'),
                ])
                ->orderBy('opened_at')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function history(Request $request, string $vessel): View
    {
        $ownedVessel = $request->user()
            ->vessels()
            ->whereKey($vessel)
            ->firstOrFail();
        Gate::authorize('view', $ownedVessel);

        $selectedState = $this->nullableQueryString($request, 'state');
        $selectedReferenceType = $this->nullableQueryString($request, 'reference_type');

        abort_if(
            $selectedState !== null && ! array_key_exists($selectedState, self::HISTORY_STATES),
            404,
        );
        abort_if(
            $selectedReferenceType !== null && ! array_key_exists($selectedReferenceType, self::REFERENCE_TYPES),
            404,
        );

        $vesselOccurrences = MaintenanceReminderOccurrence::query()
            ->ownedBy($request->user())
            ->whereHas(
                'schedule',
                fn (Builder $schedules): Builder => $schedules->where('vessel_id', $ownedVessel->id),
            );

        $storedStateCounts = (clone $vesselOccurrences)
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $historyCountsByState = [];
        foreach (array_keys(self::HISTORY_STATES) as $state) {
            $historyCountsByState[$state] = (int) $storedStateCounts->get($state, 0);
        }

        $storedReferenceTypeCounts = (clone $vesselOccurrences)
            ->select('reference_type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('reference_type')
            ->pluck('aggregate', 'reference_type');
        $historyCountsByReferenceType = [];
        foreach (array_keys(self::REFERENCE_TYPES) as $referenceType) {
            $historyCountsByReferenceType[$referenceType] = (int) $storedReferenceTypeCounts->get($referenceType, 0);
        }

        $occurrences = clone $vesselOccurrences;

        if ($selectedState !== null) {
            $occurrences->where('status', $selectedState);
        }

        if ($selectedReferenceType !== null) {
            $occurrences->where('reference_type', $selectedReferenceType);
        }

        return view('maintenance.reminder-history', [
            'vessel' => $ownedVessel,
            'historyStates' => self::HISTORY_STATES,
            'referenceTypes' => self::REFERENCE_TYPES,
            'historyCountsByState' => $historyCountsByState,
            'historyCountsByReferenceType' => $historyCountsByReferenceType,
            'selectedState' => $selectedState,
            'selectedReferenceType' => $selectedReferenceType,
            'occurrences' => $occurrences
                ->with('schedule:id,vessel_id,name')
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function acknowledge(Request $request, string $occurrence): RedirectResponse
    {
        return $this->transition(
            $request,
            $occurrence,
            'acknowledge',
            'acknowledged',
            'acknowledged_at',
            'Maintenance reminder acknowledged.',
            'Maintenance reminder was already inactive.',
        );
    }

    public function dismiss(Request $request, string $occurrence): RedirectResponse
    {
        return $this->transition(
            $request,
            $occurrence,
            'dismiss',
            'dismissed',
            'dismissed_at',
            'Maintenance reminder dismissed. No completion was recorded.',
            'Maintenance reminder was already inactive.',
        );
    }

    private function transition(
        Request $request,
        string $occurrence,
        string $ability,
        string $status,
        string $timestamp,
        string $successMessage,
        string $inactiveMessage,
    ): RedirectResponse {
        $reminder = $this->ownedOccurrence($request, $occurrence);
        Gate::authorize($ability, $reminder);

        $transitioned = DB::transaction(function () use ($request, $reminder, $status, $timestamp): bool {
            // Lock and re-apply the owner scope at the write boundary so concurrent
            // requests cannot duplicate the state transition or its audit event.
            $locked = MaintenanceReminderOccurrence::query()
                ->ownedBy($request->user())
                ->whereKey($reminder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'open') {
                return false;
            }

            $now = now();
            $locked->update([
                'status' => $status,
                $timestamp => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'channel' => 'web',
                'action' => "maintenance_reminder.{$status}",
                'target_type' => MaintenanceReminderOccurrence::class,
                'target_id' => $locked->id,
                'occurred_at' => $now,
            ]);

            return true;
        });

        if ($request->input('return_to') === 'inbox') {
            $parameters = [];
            $returnVessel = $request->input('return_vessel');
            $returnReferenceType = $request->input('return_reference_type');

            if (is_string($returnVessel) && $returnVessel === $reminder->schedule->vessel_id) {
                $parameters['vessel'] = $returnVessel;
            }

            if (is_string($returnReferenceType) && array_key_exists($returnReferenceType, self::REFERENCE_TYPES)) {
                $parameters['reference_type'] = $returnReferenceType;
            }

            $redirect = redirect()->route('maintenance.reminders.index', $parameters);
        } else {
            $redirect = redirect()->route('vessels.maintenance.index', $reminder->schedule->vessel_id);
        }

        return $redirect->with('status', $transitioned ? $successMessage : $inactiveMessage);
    }

    private function nullableQueryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        abort_if(! is_null($value) && ! is_string($value), 404);

        return $value === '' ? null : $value;
    }

    private function ownedOccurrence(Request $request, string $occurrence): MaintenanceReminderOccurrence
    {
        return MaintenanceReminderOccurrence::query()
            ->ownedBy($request->user())
            ->with('schedule.vessel')
            ->whereKey($occurrence)
            ->firstOrFail();
    }
}
