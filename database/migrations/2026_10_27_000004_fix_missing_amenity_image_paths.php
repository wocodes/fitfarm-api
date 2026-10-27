<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            '/images/amenities/strength.webp' => '/images/amenities/l1.webp',
            '/images/amenities/cardio.webp' => '/images/amenities/l2.webp',
            '/images/amenities/boxing.webp' => '/images/amenities/l4.webp',
            '/images/amenities/recovery.webp' => '/images/amenities/l3.webp',
        ];

        $replace = function ($value) use (&$replace, $replacements) {
            if (is_string($value)) {
                return str_replace(
                    array_keys($replacements),
                    array_values($replacements),
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
            ->where('section_key', 'amenities')
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
                            'content' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Image paths are intentionally not reverted.
    }
};
