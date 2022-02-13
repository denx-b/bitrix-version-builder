<?php


namespace VersionBuilder;

class Helper
{
    /**
     * @param $path
     * @return void
     */
    public static function removeDirectory($path)
    {
        $files = glob($path . '/' . '{,.}[!.,!..]*', GLOB_BRACE);
        foreach ($files as $file) {
            if (in_array(basename($file), ['.', '..'])) {
                continue;
            }
            is_dir($file) ? self::removeDirectory($file) : unlink($file);
        }
        rmdir($path);
    }

    /**
     * @param $haystack
     * @param array $needles
     * @param int $offset
     * @return false|mixed
     */
    public static function strposa($haystack, array $needles = [], int $offset = 0)
    {
        $chr = [];
        foreach ($needles as $needle) {
            $res = strpos($haystack, $needle, $offset);
            if ($res !== false) {
                $chr[$needle] = $res;
            }
        }
        if (empty($chr)) {
            return false;
        }
        return min($chr);
    }
}
