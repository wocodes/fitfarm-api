<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replace = function ($value) use (&$replace) {
            if (is_string($value)) {
                return str_replace(
                    ['Turnstiles', 'turnstiles', 'Turnstile', 'turnstile'],
                    ['Access Gates', 'access gates', 'Access Gate', 'access gate'],
                    $value
                );
            }

            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = $replace($item);
                }
            }

            return $value;
        };

        DB::table('platform_settings')
            ->orderBy('id')
            ->each(function ($row) use ($replace) {
                $content = json_decode((string) $row->value, true);

                if (! is_array($content)) {
                    return;
                }

                $normalized = $replace($content);

                if ($normalized !== $content) {
                    DB::table('platform_settings')
                        ->where('id', $row->id)
                        ->update([
                            'value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Content wording is intentionally not reverted.
    }
};
