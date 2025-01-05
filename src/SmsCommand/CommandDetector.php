<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

class CommandDetector
{
    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    private const COMMAND_PREG_MATCHES = [
        'HELP' => '/^(?<command>HELP).*$/i',
        'CREDIT' => '/^(?<command>CREDIT).*$/i',
        'FREE' => '/^(?<command>FREE).*$/i',
        'RENT bikeNumber' => '/^(?<command>RENT)\s*(?<bikeNumber>\d+)$/i',
        'RETURN bikeNumber stand note' => '/^(?<command>RETURN)\s*(?<bikeNumber>\d+)\s*(?<standName>\w+)(\s*(?<note>.+))?$/i',
        'WHERE bikeNumber' => '/^(?<command>WHERE)\s*(?<bikeNumber>\d+)$/i',
        'INFO stand' => '/^(?<command>INFO)\s*(?<standName>\w+)$/i',
        'NOTE bikeNumber problem_description' => '/^(?<command>NOTE)\s*(?<bikeNumber>\d+)\s*(?<note>.+)$/i',
        'NOTE stand problem_description' => '/^(?<command>NOTE)\s*(?<standName>\w+)\s*(?<note>.+)$/i',
        'FORCERENT bikeNumber' => '/^(?<command>FORCERENT)\s*(?<bikeNumber>\d+)$/i',
        'FORCERETURN bikeNumber stand note' => '/^(?<command>FORCERETURN)\s*(?<bikeNumber>\d+)\s*(?<standName>\w+)(\s*(?<note>.+))?$/i',
        'LIST stand' => '/^(?<command>LIST)\s*(?<standName>\w+)$/i',
        'LAST bikeNumber' => '/^(?<command>LAST)\s*(?<bikeNumber>\d+)$/i',
        'REVERT bikeNumber' => '/^(?<command>REVERT)\s*(?<bikeNumber>\d+)$/i',
        'ADD email phone fullname' => '/^(?<command>ADD)\s*(?<email>\S+)\s*(?<phone>\S+)\s*(?<fullname>.+)$/i',
        'DELNOTE bikeNumber [pattern]' => '/^(?<command>DELNOTE)\s*(?<bikeNumber>\d+)(\s*(?<pattern>.+))?$/i',
        'DELNOTE stand [pattern]' => '/^(?<command>DELNOTE)\s*(?<standName>\w+)(\s*(?<pattern>.+))?$/i',
        'TAG stand note_fo_all_bikes' => '/^(?<command>TAG)\s*(?<standName>\w+)\s*(?<note>.+)$/i',
        'UNTAG stand [pattern]' => '/^(?<command>UNTAG)\s*(?<standName>\w+)(\s*(?<pattern>.+))?$/i',
    ];

    public function detect(string $message): array
    {
        foreach (self::COMMAND_PREG_MATCHES as $pattern) {
            if (preg_match($pattern, $message, $matches, PREG_UNMATCHED_AS_NULL)) {
                $namedMatches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                unset($namedMatches['command']);

                return ['command' => strtoupper($matches['command']), 'arguments' => $namedMatches];
            }
        }

        $args = preg_split('/\s+/', $message);
        $possibleCommand = strtoupper(reset($args));

        return ['command' => 'UNKNOWN', 'possibleCommand' => $possibleCommand, 'arguments' => []];
    }
}
