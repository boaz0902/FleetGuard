<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'checked_out_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dateTime('checked_out_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('bookings', 'checked_in_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dateTime('checked_in_at')->nullable()->after('checked_out_at');
            });
        }

        $this->backfillTimestamp('checked_out_at', ['rental_checked_out', 'booking_marked_ongoing']);
        $this->backfillTimestamp('checked_in_at', ['rental_checked_in', 'booking_marked_completed']);
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['checked_out_at', 'checked_in_at'],
            fn (string $column): bool => Schema::hasColumn('bookings', $column),
        ));

        if ($columns !== []) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    private function backfillTimestamp(string $column, array $actions): void
    {
        $events = DB::table('audit_logs')
            ->select('subject_id', DB::raw('COUNT(*) AS event_count'), DB::raw('MIN(created_at) AS event_at'))
            ->where('subject_type', 'App\\Models\\Booking')
            ->whereIn('action', $actions)
            ->groupBy('subject_id')
            ->havingRaw('COUNT(*) = 1')
            ->orderBy('subject_id')
            ->cursor();

        foreach ($events as $event) {
            DB::table('bookings')
                ->where('id', $event->subject_id)
                ->whereNull($column)
                ->update([$column => $event->event_at]);
        }
    }
};
