<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MaintenanceCompletion;
use App\Models\MaintenanceCompletionReversal;
use App\Models\MaintenanceSchedule;
use App\Models\Vessel;
use App\Models\VesselEquipmentItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\View\View;

class MaintenanceScheduleController extends Controller
{
    private const BASES = [
        'meter_hours' => 'Every meter-hours interval',
        'calendar_days' => 'Every calendar-day interval',
        'annual_date' => 'Every year on a month and day',
    ];

    private const SOURCE_KINDS = [
        'manufacturer_manual' => 'Manufacturer manual',
        'manufacturer_service_bulletin' => 'Manufacturer service bulletin',
        'manufacturer_technical_notice' => 'Manufacturer technical notice',
        'dealer_service_document' => 'Dealer service document',
    ];

    private const STATUSES = [
        'active' => 'Active',
        'paused' => 'Paused',
    ];

    private const LEAP_DAY_POLICIES = [
        'leap_year_only' => 'Wait for the next leap year',
        'february_28' => 'Use February 28 in non-leap years',
        'march_1' => 'Use March 1 in non-leap years',
    ];

    public function index(Request $request, Vessel $vessel): View
    {
        Gate::authorize('view', $vessel);

        return view('maintenance.index', [
            'vessel' => $vessel,
            'schedules' => $vessel->maintenanceSchedules()
                ->with([
                    'equipmentItem',
                    'completions' => fn ($query) => $query->with('reversal')->orderByDesc('completed_on')->orderByDesc('created_at'),
                    'reminderOccurrences' => fn ($query) => $query->where('status', 'open')->orderBy('opened_at'),
                ])
                ->orderBy('status')
                ->orderBy('name')
                ->get(),
            'schedule' => new MaintenanceSchedule,
            'equipmentItems' => $vessel->equipmentItems()->where('status', 'active')->orderBy('name')->get(),
            'bases' => self::BASES,
            'leapDayPolicies' => self::LEAP_DAY_POLICIES,
            'sourceKinds' => self::SOURCE_KINDS,
            'today' => now($request->user()->timezone ?: 'UTC')->startOfDay()->toImmutable(),
        ]);
    }

    public function store(Request $request, Vessel $vessel): RedirectResponse
    {
        Gate::authorize('update', $vessel);

        $details = $this->validatedDetails($request, $vessel);
        $details['status'] = 'active';
        $schedule = $vessel->maintenanceSchedules()->create($details);
        $this->audit($request, 'maintenance_schedule.created', $schedule);

        return redirect()
            ->route('vessels.maintenance.index', $vessel)
            ->with('status', 'Maintenance schedule added.');
    }

    public function edit(Request $request, string $schedule): View
    {
        $maintenanceSchedule = $this->ownedSchedule($request, $schedule);
        Gate::authorize('update', $maintenanceSchedule);

        return view('maintenance.edit', [
            'schedule' => $maintenanceSchedule,
            'equipmentItems' => $maintenanceSchedule->vessel->equipmentItems()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'bases' => self::BASES,
            'statuses' => self::STATUSES,
            'leapDayPolicies' => self::LEAP_DAY_POLICIES,
            'sourceKinds' => self::SOURCE_KINDS,
        ]);
    }

    public function storeCompletion(Request $request, string $schedule): RedirectResponse
    {
        $maintenanceSchedule = $this->ownedSchedule($request, $schedule);
        Gate::authorize('update', $maintenanceSchedule);

        $meterRule = $maintenanceSchedule->basis === 'meter_hours' ? 'required' : 'nullable';
        $details = $request->validate([
            'completed_on' => ['required', 'date_format:Y-m-d'],
            'meter_hours' => [$meterRule, 'numeric', 'decimal:0,1', 'between:0,999999999.9'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $completion = $maintenanceSchedule->completions()->create($details);
        $this->auditCompletion($request, $completion);

        return redirect()
            ->route('vessels.maintenance.index', $maintenanceSchedule->vessel_id)
            ->with('status', 'Maintenance completion recorded.');
    }

    public function reverseCompletion(Request $request, string $completion): RedirectResponse
    {
        $maintenanceCompletion = MaintenanceCompletion::query()
            ->ownedBy($request->user())
            ->with(['reversal', 'schedule.vessel'])
            ->whereKey($completion)
            ->firstOrFail();
        Gate::authorize('reverse', $maintenanceCompletion);

        $details = $request->validate([
            'reason' => ['required', 'string', 'max:500', 'regex:/\S/u'],
        ]);

        DB::transaction(function () use ($details, $maintenanceCompletion, $request): void {
            // Corrections remain append-only: never rewrite the original completion or an existing reversal.
            $reversal = $maintenanceCompletion->reversal()->firstOrCreate([], $details);

            if (! $reversal->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'reason' => 'This completion has already been reversed.',
                ]);
            }

            $this->auditCompletionReversal($request, $reversal);
        });

        return redirect()
            ->route('vessels.maintenance.index', $maintenanceCompletion->schedule->vessel_id)
            ->with('status', 'Maintenance completion reversed. Record a corrected completion separately if needed.');
    }

    public function update(Request $request, string $schedule): RedirectResponse
    {
        $maintenanceSchedule = $this->ownedSchedule($request, $schedule);
        Gate::authorize('update', $maintenanceSchedule);

        $details = $this->validatedDetails($request, $maintenanceSchedule->vessel, true);
        $maintenanceSchedule->update($details);
        $this->audit($request, 'maintenance_schedule.updated', $maintenanceSchedule);

        return redirect()
            ->route('vessels.maintenance.index', $maintenanceSchedule->vessel_id)
            ->with('status', 'Maintenance schedule updated.');
    }

    private function validatedDetails(Request $request, Vessel $vessel, bool $includeStatus = false): array
    {
        $rules = [
            'equipment_item_id' => [
                'nullable',
                'ulid',
                Rule::exists(VesselEquipmentItem::class, 'id')
                    ->where(fn (Builder $query): Builder => $query->where('vessel_id', $vessel->id)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'basis' => ['required', Rule::in(array_keys(self::BASES))],
            'interval_days' => ['exclude_unless:basis,calendar_days', 'required', 'integer', 'between:1,3650'],
            'interval_hours' => ['exclude_unless:basis,meter_hours', 'required', 'numeric', 'decimal:0,1', 'between:0.1,999999999.9'],
            'annual_month' => ['exclude_unless:basis,annual_date', 'required', 'integer', 'between:1,12'],
            'annual_day' => ['exclude_unless:basis,annual_date', 'required', 'integer', 'between:1,31'],
            'leap_day_policy' => ['nullable', Rule::in(array_keys(self::LEAP_DAY_POLICIES))],
            'source_kind' => ['nullable', Rule::in(array_keys(self::SOURCE_KINDS))],
            'source_title' => ['nullable', 'string', 'max:160', 'regex:/\S/u'],
            'source_reference' => ['nullable', 'string', 'max:160', 'regex:/\S/u'],
            'source_revision' => ['nullable', 'string', 'max:80', 'regex:/\S/u'],
            'source_section' => ['nullable', 'string', 'max:120', 'regex:/\S/u'],
            'source_published_on' => ['nullable', 'date_format:Y-m-d'],
            'source_url' => ['nullable', 'string', 'max:2048', 'url:https', 'not_regex:/[?#@]/u'],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_lead_days' => ['nullable', 'integer', 'between:0,3650'],
            'reminder_lead_hours' => ['nullable', 'numeric', 'decimal:0,1', 'between:0,999999999.9'],
            'baseline_on' => ['exclude_unless:basis,calendar_days', 'required', 'date_format:Y-m-d'],
            'baseline_hours' => ['exclude_unless:basis,meter_hours', 'required', 'numeric', 'decimal:0,1', 'between:0,999999999.9'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($includeStatus) {
            $rules['status'] = ['required', Rule::in(array_keys(self::STATUSES))];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function (ValidationValidator $validator) use ($request): void {
            $sourceFields = [
                'source_title',
                'source_reference',
                'source_revision',
                'source_section',
                'source_published_on',
                'source_url',
            ];
            $hasSourceDetails = collect($sourceFields)
                ->contains(fn (string $field): bool => $request->filled($field));

            // Provenance is optional, but partial citations are rejected so the UI cannot imply
            // that free-form text or a token-bearing URL is a verified manufacturer source.
            if (! $request->filled('source_kind') && $hasSourceDetails) {
                $validator->errors()->add(
                    'source_kind',
                    'Choose the type of document used for this source reference.',
                );
            }

            if ($request->filled('source_kind') && ! $request->filled('source_title')) {
                $validator->errors()->add(
                    'source_title',
                    'Enter the title of the referenced document.',
                );
            }

            $reminderEnabled = $request->boolean('reminder_enabled');
            $basis = $request->input('basis');

            // Preferences stay basis-specific so a stored value cannot be silently
            // reinterpreted as a different unit if the recurrence basis changes.
            if ($reminderEnabled && $basis === 'meter_hours') {
                if (! $request->filled('reminder_lead_hours')) {
                    $validator->errors()->add(
                        'reminder_lead_hours',
                        'Enter how many meter hours before due to use for this stored preference.',
                    );
                }

                if ($request->filled('reminder_lead_days')) {
                    $validator->errors()->add(
                        'reminder_lead_days',
                        'A day-based lead is allowed only for calendar-day or annual schedules.',
                    );
                }
            }

            if ($reminderEnabled && in_array($basis, ['calendar_days', 'annual_date'], true)) {
                if (! $request->filled('reminder_lead_days')) {
                    $validator->errors()->add(
                        'reminder_lead_days',
                        'Enter how many calendar days before due to use for this stored preference.',
                    );
                }

                if ($request->filled('reminder_lead_hours')) {
                    $validator->errors()->add(
                        'reminder_lead_hours',
                        'A meter-hour lead is allowed only for meter-hour schedules.',
                    );
                }
            }

            if ($request->input('basis') !== 'annual_date') {
                if ($request->filled('leap_day_policy')) {
                    $validator->errors()->add(
                        'leap_day_policy',
                        'A February 29 rule is allowed only for an annual February 29 schedule.',
                    );
                }

                return;
            }

            $month = filter_var($request->input('annual_month'), FILTER_VALIDATE_INT);
            $day = filter_var($request->input('annual_day'), FILTER_VALIDATE_INT);

            if ($month !== false && $day !== false && ! checkdate($month, $day, 2000)) {
                $validator->errors()->add('annual_day', 'The annual month and day must form a valid calendar date.');
            }

            $isLeapDay = $month === 2 && $day === 29;

            if ($isLeapDay && ! $request->filled('leap_day_policy')) {
                $validator->errors()->add(
                    'leap_day_policy',
                    'Choose how February 29 should behave in non-leap years.',
                );
            }

            if (! $isLeapDay && $request->filled('leap_day_policy')) {
                $validator->errors()->add(
                    'leap_day_policy',
                    'A February 29 rule is allowed only for an annual February 29 schedule.',
                );
            }
        });

        $details = array_merge([
            'equipment_item_id' => null,
            'interval_days' => null,
            'interval_hours' => null,
            'annual_month' => null,
            'annual_day' => null,
            'leap_day_policy' => null,
            'source_kind' => null,
            'source_title' => null,
            'source_reference' => null,
            'source_revision' => null,
            'source_section' => null,
            'source_published_on' => null,
            'source_url' => null,
            'reminder_enabled' => false,
            'reminder_lead_days' => null,
            'reminder_lead_hours' => null,
            'baseline_on' => null,
            'baseline_hours' => null,
        ], $validator->validate());

        $details['reminder_enabled'] = $request->boolean('reminder_enabled');

        if (! $details['reminder_enabled']) {
            $details['reminder_lead_days'] = null;
            $details['reminder_lead_hours'] = null;
        } elseif ($details['basis'] === 'meter_hours') {
            $details['reminder_lead_days'] = null;
        } else {
            $details['reminder_lead_hours'] = null;
        }

        return $details;
    }

    private function ownedSchedule(Request $request, string $schedule): MaintenanceSchedule
    {
        return MaintenanceSchedule::query()
            ->ownedBy($request->user())
            ->with('vessel')
            ->whereKey($schedule)
            ->firstOrFail();
    }

    private function auditCompletionReversal(Request $request, MaintenanceCompletionReversal $reversal): void
    {
        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'channel' => 'web',
            'action' => 'maintenance_completion.reversed',
            'target_type' => MaintenanceCompletionReversal::class,
            'target_id' => $reversal->id,
            'occurred_at' => now(),
        ]);
    }

    private function auditCompletion(Request $request, MaintenanceCompletion $completion): void
    {
        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'channel' => 'web',
            'action' => 'maintenance_completion.created',
            'target_type' => MaintenanceCompletion::class,
            'target_id' => $completion->id,
            'occurred_at' => now(),
        ]);
    }

    private function audit(Request $request, string $action, MaintenanceSchedule $schedule): void
    {
        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'channel' => 'web',
            'action' => $action,
            'target_type' => MaintenanceSchedule::class,
            'target_id' => $schedule->id,
            'occurred_at' => now(),
        ]);
    }
}
