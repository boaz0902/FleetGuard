<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('subject_id')->change();
        });
    }

    public function down(): void
    {
        $hasNonNumericSubjectId = false;

        DB::table('audit_logs')
            ->select(['id', 'subject_id'])
            ->orderBy('id')
            ->chunk(500, function ($logs) use (&$hasNonNumericSubjectId): bool {
                foreach ($logs as $log) {
                    if (! ctype_digit((string) $log->subject_id)) {
                        $hasNonNumericSubjectId = true;

                        return false;
                    }
                }

                return true;
            });

        if ($hasNonNumericSubjectId) {
            throw new RuntimeException('Cannot restore numeric audit subject IDs while non-numeric vehicle audit records exist.');
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('subject_id')->change();
        });
    }
};
