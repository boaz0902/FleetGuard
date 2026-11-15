<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogFiltersRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const AREAS = [
        'Bookings',
        'Fleet',
        'Maintenance',
        'Customers',
        'Rentals',
        'Reports',
        'Auth',
        'Other',
    ];

    private const ACTIONS = [
        'booking_submitted' => ['Bookings', 'Reservation submitted', 'calendar-check'],
        'booking_approved' => ['Bookings', 'Reservation approved', 'check-circle'],
        'booking_rejected' => ['Bookings', 'Reservation rejected', 'x-circle'],
        'booking_cancelled' => ['Bookings', 'Reservation cancelled', 'slash-circle'],
        'booking_marked_ongoing' => ['Rentals', 'Rental marked ongoing', 'key'],
        'booking_marked_completed' => ['Rentals', 'Rental marked completed', 'check-circle'],
        'vehicle_created' => ['Fleet', 'Vehicle created', 'car-front'],
        'vehicle_updated' => ['Fleet', 'Vehicle updated', 'pencil'],
        'vehicle_activated' => ['Fleet', 'Vehicle activated', 'check-circle'],
        'vehicle_deactivated' => ['Fleet', 'Vehicle deactivated', 'slash-circle'],
        'vehicle_status_changed' => ['Fleet', 'Vehicle status changed', 'arrow-repeat'],
        'vehicle_unavailability_created' => ['Maintenance', 'Maintenance scheduled', 'tools'],
        'vehicle_unavailability_removed' => ['Maintenance', 'Maintenance removed', 'calendar-x'],
        'customer_note_added' => ['Customers', 'Customer note added', 'sticky'],
        'customer_blocked' => ['Customers', 'Customer blocked', 'slash-circle'],
        'customer_unblocked' => ['Customers', 'Customer unblocked', 'check-circle'],
        'customer_updated' => ['Customers', 'Customer updated', 'people'],
        'customer_account_registered' => ['Auth', 'Customer account registered', 'person-plus'],
        'customer_account_linked' => ['Auth', 'Customer account linked', 'link-45deg'],
        'customer_link_conflict' => ['Auth', 'Customer account link conflict', 'exclamation-triangle'],
        'rental_checked_out' => ['Rentals', 'Rental checked out', 'key'],
        'rental_checked_in' => ['Rentals', 'Rental checked in', 'box-arrow-in-left'],
        'report_exported' => ['Reports', 'Report exported', 'download'],
    ];

    public function index(AuditLogFiltersRequest $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $viewer = $request->user();
        $filters = array_filter(
            $request->filters(),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
        $now = Carbon::now(config('app.timezone'));
        $todayStart = $now->copy()->startOfDay();
        $tomorrow = $todayStart->copy()->addDay();
        $sevenDaysStart = $todayStart->copy()->subDays(6);

        $metricRow = AuditLog::query()
            ->selectRaw(
                'COUNT(*) as total, '
                .'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as today, '
                .'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as last_seven_days, '
                .'COUNT(DISTINCT user_id) as distinct_actors',
                [$todayStart, $tomorrow, $sevenDaysStart, $tomorrow],
            )
            ->first();

        $metrics = [
            'total' => (int) $metricRow->total,
            'today' => (int) $metricRow->today,
            'last_seven_days' => (int) $metricRow->last_seven_days,
            'distinct_actors' => (int) $metricRow->distinct_actors,
        ];

        $actionGroups = array_fill_keys(self::AREAS, []);
        $actionCatalog = [];

        foreach (array_keys(self::ACTIONS) as $action) {
            $metadata = $this->actionMetadata($action);
            $actionCatalog[$action] = $metadata;
            $actionGroups[$metadata['area']][] = [
                'value' => $action,
                ...$metadata,
            ];
        }

        foreach (AuditLog::query()->distinct()->orderBy('action')->pluck('action') as $action) {
            if (isset($actionCatalog[$action])) {
                continue;
            }

            $metadata = $this->actionMetadata($action);
            $actionCatalog[$action] = $metadata;
            $actionGroups[$metadata['area']][] = [
                'value' => $action,
                ...$metadata,
            ];
        }

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = AuditLog::query()->with(['user:id,name', 'subject']);

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['actor'])) {
            $query->where('user_id', $filters['actor']);
        }

        if (isset($filters['start_date'])) {
            $query->where(
                'created_at',
                '>=',
                Carbon::createFromFormat('!Y-m-d', $filters['start_date'], config('app.timezone')),
            );
        }

        if (isset($filters['end_date'])) {
            $query->where(
                'created_at',
                '<',
                Carbon::createFromFormat('!Y-m-d', $filters['end_date'], config('app.timezone'))
                    ->addDay(),
            );
        }

        if (isset($filters['search'])) {
            $this->applySearch($query, trim($filters['search']));
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $maintenanceVehicleIds = collect($logs->items())
            ->map(function (AuditLog $log): ?string {
                if ($log->subject instanceof VehicleUnavailability) {
                    return (string) $log->subject->vehicle_id;
                }

                $vehicleId = $log->changes['vehicle_id'] ?? null;

                return is_string($vehicleId) || is_int($vehicleId) ? (string) $vehicleId : null;
            })
            ->filter()
            ->unique()
            ->values();

        $linkedVehicles = Vehicle::query()
            ->whereIn('id', $maintenanceVehicleIds)
            ->get(['id', 'name'])
            ->keyBy('id');
        $maskContacts = ! $viewer->isAdmin();

        $logs->setCollection($logs->getCollection()->map(function (AuditLog $log) use (
            $actionCatalog,
            $linkedVehicles,
            $maskContacts,
            $viewer,
        ): array {
            $metadata = $actionCatalog[$log->action] ?? $this->actionMetadata($log->action);
            $changes = $this->sanitizeChanges($log->changes ?? [], $maskContacts);
            $record = $this->recordDetails($log, $viewer, $linkedVehicles);

            return [
                'timestamp' => $log->created_at,
                'actor' => $log->user?->name ?? 'Deleted user',
                'action' => $metadata,
                'record' => $record,
                'summary' => $this->summary($changes),
                'diff' => $this->diffRows($changes),
            ];
        }));

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'metrics' => $metrics,
            'filters' => $filters,
            'actionGroups' => $actionGroups,
            'actionCatalog' => $actionCatalog,
            'actors' => $actors,
            'filterChips' => $this->filterChips($filters, $actionCatalog, $actors),
            'now' => $now,
        ]);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(function (Builder $searchQuery) use ($like, $search): void {
            $searchQuery
                ->where('action', 'like', $like)
                ->orWhereHas('user', fn (Builder $actorQuery) => $actorQuery->where('name', 'like', $like))
                ->orWhereHasMorph(
                    'subject',
                    [Booking::class, Customer::class, Vehicle::class, VehicleUnavailability::class, User::class],
                    function (Builder $subjectQuery, string $type) use ($like, $search): void {
                        if ($type === Booking::class) {
                            $subjectQuery->where(function (Builder $recordQuery) use ($like, $search): void {
                                $recordQuery->where('booking_reference', 'like', $like);

                                if (ctype_digit($search)) {
                                    $recordQuery->orWhereKey($search);
                                }
                            });
                        } elseif ($type === Customer::class) {
                            $subjectQuery->where(function (Builder $recordQuery) use ($like, $search): void {
                                $recordQuery->where('full_name', 'like', $like);

                                if (ctype_digit($search)) {
                                    $recordQuery->orWhereKey($search);
                                }
                            });
                        } elseif ($type === Vehicle::class) {
                            $subjectQuery->where(function (Builder $recordQuery) use ($like): void {
                                $recordQuery->where('name', 'like', $like)
                                    ->orWhere('id', 'like', $like);
                            });
                        } elseif ($type === VehicleUnavailability::class) {
                            $subjectQuery->where(function (Builder $recordQuery) use ($like, $search): void {
                                $recordQuery->where('vehicle_id', 'like', $like);

                                if (ctype_digit($search)) {
                                    $recordQuery->orWhereKey($search);
                                }
                            });
                        } else {
                            $subjectQuery->where('name', 'like', $like);
                        }
                    },
                );

            if (ctype_digit($search)) {
                $searchQuery->orWhere('subject_id', $search);
            }
        });
    }

    private function actionMetadata(string $action): array
    {
        if (isset(self::ACTIONS[$action])) {
            [$area, $label, $icon] = self::ACTIONS[$action];
        } else {
            $area = match (true) {
                str_starts_with($action, 'booking_') => 'Bookings',
                str_starts_with($action, 'vehicle_unavailability_') => 'Maintenance',
                str_starts_with($action, 'vehicle_') => 'Fleet',
                str_starts_with($action, 'customer_') => 'Customers',
                str_starts_with($action, 'rental_') => 'Rentals',
                str_starts_with($action, 'report_') => 'Reports',
                str_starts_with($action, 'auth_'),
                str_starts_with($action, 'login_'),
                str_starts_with($action, 'logout_') => 'Auth',
                default => 'Other',
            };
            $label = Str::headline($action);
            $icon = match ($area) {
                'Bookings' => 'calendar-check',
                'Fleet' => 'car-front',
                'Maintenance' => 'tools',
                'Customers' => 'people',
                'Rentals' => 'key',
                'Reports' => 'bar-chart',
                'Auth' => 'shield-lock',
                default => 'clipboard-data',
            };
        }

        return [
            'area' => $area,
            'area_key' => Str::slug($area),
            'label' => $label,
            'icon' => $icon,
        ];
    }

    private function recordDetails(AuditLog $log, User $viewer, Collection $linkedVehicles): array
    {
        $subject = $log->subject;

        if ($subject instanceof Booking) {
            return [
                'label' => $subject->booking_reference,
                'context' => 'Reservation',
                'url' => $viewer->can('view', $subject) ? route('admin.bookings.show', $subject) : null,
            ];
        }

        if ($subject instanceof Vehicle) {
            return [
                'label' => $subject->name,
                'context' => 'Vehicle',
                'url' => $viewer->can('view', $subject) ? route('admin.fleet.show', $subject) : null,
            ];
        }

        if ($subject instanceof Customer) {
            return [
                'label' => $subject->full_name,
                'context' => 'Customer',
                'url' => $viewer->can('view', $subject) ? route('admin.customers.show', $subject) : null,
            ];
        }

        if ($subject instanceof VehicleUnavailability || $log->subject_type === VehicleUnavailability::class) {
            $vehicleId = $subject instanceof VehicleUnavailability
                ? (string) $subject->vehicle_id
                : (string) ($log->changes['vehicle_id'] ?? '');
            $vehicle = $linkedVehicles->get($vehicleId);

            return [
                'label' => 'Maintenance period #'.$log->subject_id,
                'context' => $vehicle?->name,
                'url' => $vehicle && $viewer->can('view', $vehicle)
                    ? route('admin.fleet.show', $vehicle)
                    : null,
            ];
        }

        if ($subject instanceof User) {
            return [
                'label' => $log->action === 'report_exported' ? 'Admin report export' : 'Account #'.$subject->getKey(),
                'context' => 'Reports and account activity',
                'url' => null,
            ];
        }

        $type = $subject ? class_basename($subject) : class_basename($log->subject_type);

        return [
            'label' => Str::headline($type).' #'.$log->subject_id,
            'context' => null,
            'url' => null,
        ];
    }

    private function sanitizeChanges(array $changes, bool $maskContacts): array
    {
        $safe = [];

        foreach ($changes as $key => $value) {
            $keyName = is_string($key) ? strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key)) : '';

            if (preg_match('/password|passwd|token|secret|apikey|privatekey|sessionid|authorization|credential|csrf/', $keyName)) {
                $safe[$key] = '[REDACTED]';
            } elseif ($maskContacts && preg_match('/email|phone|mobile|telephone|contact|address/', $keyName)) {
                $safe[$key] = $this->maskValue($value);
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitizeChanges($value, $maskContacts);
            } elseif ($maskContacts && is_string($value)) {
                $safe[$key] = preg_replace(
                    '/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i',
                    '[MASKED]',
                    $value,
                ) ?? $value;
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function maskValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value === null ? null : '[MASKED]';
        }

        $masked = [];

        foreach ($value as $key => $nested) {
            $masked[$key] = $this->maskValue($nested);
        }

        return $masked;
    }

    private function diffRows(array $changes): array
    {
        if (array_key_exists('from', $changes) || array_key_exists('to', $changes)) {
            return [[
                'field' => 'Status',
                'from' => $this->displayValue($changes['from'] ?? null),
                'to' => $this->displayValue($changes['to'] ?? null),
            ]];
        }

        $rows = [];
        $this->appendDiffRows($rows, $changes);

        return $rows;
    }

    private function appendDiffRows(array &$rows, array $changes, string $prefix = ''): void
    {
        foreach ($changes as $key => $value) {
            $field = trim($prefix.' '.Str::headline((string) $key));

            if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
                $rows[] = [
                    'field' => $field,
                    'from' => $this->displayValue($value['from'] ?? null),
                    'to' => $this->displayValue($value['to'] ?? null),
                ];
            } elseif (is_array($value)) {
                $this->appendDiffRows($rows, $value, $field);
            } else {
                $rows[] = [
                    'field' => $field,
                    'from' => '—',
                    'to' => $this->displayValue($value),
                ];
            }
        }
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            return Str::limit($encoded === false ? '[Unserializable value]' : $encoded, 180);
        }

        return Str::limit((string) $value, 180);
    }

    private function summary(array $changes): string
    {
        if (array_key_exists('from', $changes) || array_key_exists('to', $changes)) {
            return 'Status changed.';
        }

        $fields = array_map(
            fn (string|int $field): string => Str::headline((string) $field),
            array_keys($changes),
        );

        if ($fields === []) {
            return 'Audit event recorded.';
        }

        $visibleFields = array_slice($fields, 0, 3);

        return 'Recorded changes: '.implode(', ', $visibleFields).(count($fields) > 3 ? ', …' : '.');
    }

    private function filterChips(array $filters, array $actionCatalog, Collection $actors): array
    {
        $chips = [];

        foreach (['search' => 'Search'] as $key => $label) {
            if (isset($filters[$key])) {
                $chips[] = $this->makeChip($label.': '.$filters[$key], $filters, [$key]);
            }
        }

        if (isset($filters['action'])) {
            $metadata = $actionCatalog[$filters['action']] ?? $this->actionMetadata($filters['action']);
            $chips[] = $this->makeChip('Action: '.$metadata['label'], $filters, ['action']);
        }

        if (isset($filters['actor'])) {
            $actor = $actors->firstWhere('id', (int) $filters['actor']);
            $chips[] = $this->makeChip('Actor: '.($actor?->name ?? 'Unknown actor'), $filters, ['actor']);
        }

        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            $start = isset($filters['start_date'])
                ? Carbon::createFromFormat('!Y-m-d', $filters['start_date'], config('app.timezone'))->format('M j, Y')
                : 'Beginning';
            $end = isset($filters['end_date'])
                ? Carbon::createFromFormat('!Y-m-d', $filters['end_date'], config('app.timezone'))->format('M j, Y')
                : 'Now';
            $chips[] = $this->makeChip('Date: '.$start.' – '.$end, $filters, ['start_date', 'end_date']);
        }

        return $chips;
    }

    private function makeChip(string $label, array $filters, array $removedKeys): array
    {
        foreach ($removedKeys as $key) {
            unset($filters[$key]);
        }

        return [
            'label' => $label,
            'url' => route('admin.audit-logs.index', $filters),
        ];
    }
}
