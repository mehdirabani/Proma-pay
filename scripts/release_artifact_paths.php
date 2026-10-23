<?php

declare(strict_types=1);

/** Resolve a release artifact in the active output folder or its version archive. */
final class ReleaseArtifactPaths
{
    public static function core(string $root, string $version): string
    {
        return self::versioned($root, 'core', $version, 'PromaPay-v' . $version . '.zip');
    }

    public static function update(string $root, string $version, string $filename): string
    {
        return self::versioned($root, 'updates', $version, $filename);
    }

    public static function coreFile(string $root, string $version, string $filename): string
    {
        return self::versioned($root, 'core', $version, $filename);
    }

    private static function versioned(string $root, string $area, string $version, string $filename): string
    {
        $activePath = $root . '/dist/' . $area . '/' . $filename;
        if (is_file($activePath)) {
            return $activePath;
        }

        $major = explode('.', $version, 2)[0];
        $archivePath = $root . '/dist/' . $area . '/archive/v' . $major . '/v' . $version . '/' . $filename;
        return is_file($archivePath) ? $archivePath : $activePath;
    }
}
