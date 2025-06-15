<?php

declare(strict_types=1);


namespace Cadfael\Engine\Entity\Database;

class SqlModes
{
    const COMBINED_MODE = [
        'ANSI' => [
            'ANSI_QUOTES',
            'IGNORE_SPACE',
            'ONLY_FULL_GROUP_BY',
            'PIPES_AS_CONCAT',
            'REAL_AS_FLOAT',
        ],
        'TRADITIONAL' => [
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ENGINE_SUBSTITUTION',
            'NO_ZERO_DATE',
            'NO_ZERO_IN_DATE',
            'STRICT_ALL_TABLES',
            'STRICT_TRANS_TABLES',
        ]
    ];

    const DEFAULT_MODE = [
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
        'NO_ZERO_DATE',
        'NO_ZERO_IN_DATE',
        'ONLY_FULL_GROUP_BY',
        'STRICT_TRANS_TABLES',
    ];

    public static function normaliseMode(?string $mode, ?string $version): array
    {
        $modes = $mode ? explode(',', $mode) : self::DEFAULT_MODE;
        // Search and replace any COMBINED_MODE value you find
        $normalised_modes = [];
        foreach ($modes as $entry) {
            if (isset(self::COMBINED_MODE[$entry])) {
                $normalised_modes += self::COMBINED_MODE[$entry];
            } else {
                $normalised_modes[] = $entry;
            }
        }
        return array_unique($normalised_modes);
    }

    /**
     * Determines if a mode array contains a specific mode. This does not account for defaults for a specific server
     * version.
     *
     * @TODO Move this into the server object?
     *
     * @param array $modes
     * @param string $mode
     * @return bool
     */
    public static function hasMode(array $modes, string $mode): bool
    {
        return in_array($mode, $modes) !== false;
    }
}
