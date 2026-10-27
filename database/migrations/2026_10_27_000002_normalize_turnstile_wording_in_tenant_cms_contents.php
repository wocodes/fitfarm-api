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

        DB::table('tenant_cms_contents')
            ->orderBy('id')
            ->each(function ($row) use ($replace) {
                $content = json_decode((string) $row->content, true);

                if (! is_array($content)) {
                    return;
                }

                $normalized = $replace($content);

                if ($normalized !== $content) {
                    DB::table('tenant_cms_contents')
                        ->where('id', $row->id)
                        ->update([
                            'content' => json_encode($normalized),
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
