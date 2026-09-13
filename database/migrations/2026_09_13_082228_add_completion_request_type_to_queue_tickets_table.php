<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('queue_tickets', 'completion_request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->string('completion_request_type')->nullable()->after('request_type');
            });
        }

        $this->enableDocumentCompletionForExistingSettings();
        $this->assignDocumentCompletionLaneToFullAccessTellers();
    }

    public function down(): void
    {
        if (Schema::hasColumn('queue_tickets', 'completion_request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->dropColumn('completion_request_type');
            });
        }
    }

    private function enableDocumentCompletionForExistingSettings(): void
    {
        if (! Schema::hasColumn('queue_system_settings', 'enabled_request_types')) {
            return;
        }

        $rows = DB::table('queue_system_settings')->get(['id', 'enabled_request_types']);

        foreach ($rows as $row) {
            $types = $this->decodeStringList($row->enabled_request_types);

            if ($types === [] || in_array('document_completion', $types, true)) {
                continue;
            }

            $types[] = 'document_completion';

            DB::table('queue_system_settings')
                ->where('id', $row->id)
                ->update(['enabled_request_types' => json_encode(array_values($types))]);
        }
    }

    private function assignDocumentCompletionLaneToFullAccessTellers(): void
    {
        if (! Schema::hasColumn('users', 'queue_lanes')) {
            return;
        }

        $previousLanes = [
            'nomination_card',
            'direct_application',
            'transfer',
            'current_student',
        ];

        $tellers = DB::table('users')
            ->where('role', 'teller')
            ->get(['id', 'queue_lanes']);

        foreach ($tellers as $teller) {
            $lanes = $this->decodeStringList($teller->queue_lanes);

            if ($lanes === [] || in_array('document_completion', $lanes, true)) {
                continue;
            }

            foreach ($previousLanes as $lane) {
                if (! in_array($lane, $lanes, true)) {
                    continue 2;
                }
            }

            $lanes[] = 'document_completion';

            DB::table('users')
                ->where('id', $teller->id)
                ->update(['queue_lanes' => json_encode(array_values($lanes))]);
        }
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn (mixed $item): bool => is_string($item)));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn (mixed $item): bool => is_string($item)));
    }
};
