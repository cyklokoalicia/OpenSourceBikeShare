<?php

declare(strict_types=1);

namespace BikeShare\Rent\Enum;

/**
 * The two rental states a bike can be in, per the spec 0013 rental state machine.
 *
 * These are *derived* (from whether an open rental exists in history), not stored — there is
 * no bike-status column. Do not confuse with StandStatus, which applies to stands.
 */
enum BikeRentalState
{
    case PARKED;   // no open rental — available on a stand
    case ON_TRIP;  // an open rental exists — held by a user
}
