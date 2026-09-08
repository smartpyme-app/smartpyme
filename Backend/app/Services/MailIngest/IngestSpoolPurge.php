<?php

namespace App\Services\MailIngest;

class IngestSpoolPurge
{
    /**
     * Deletes spool files older than $days in failed/, processing/ and incoming/.
     */
    public function purge(string $root, int $days = 7): int
    {
        $cutoff = time() - max(1, $days) * 86400;
        $deleted = 0;
        foreach (['failed', 'processing', 'incoming'] as $dir) {
            $path = rtrim($root, '/') . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            foreach (glob($path . '/*') ?: [] as $file) {
                if (!is_file($file) || str_ends_with($file, '.gitkeep')) {
                    continue;
                }
                $mtime = filemtime($file);
                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
