<?php

// Translations for messages produced by RentSystemResult (rent / return / revert flows).
// Successes branch on the `channel` parameter (auto-set by RentSystemResult from systemType):
//   - sms: short plain text suitable for SMS (no HTML, no post-action instructions)
//   - other: HTML for web/QR with badge spans on values and h3 around the headline
// Errors share wording across channels — flat strings.

return [
    'bike.rent.success' => '{channel, select,
sms {Bike {bikeNumber}: Open with code {currentCode}. Change code immediately to {newCode}.}
other {<h3>Bike <span class="badge badge-primary">{bikeNumber}</span>: Open with code <span class="badge badge-primary">{currentCode}</span>. Change code immediately to <span class="badge badge-primary">{newCode}</span></h3> (open, rotate metal part, set new code, rotate metal part back).}}{hasNote, select, true {
Reported issue: {note}} other {}}',

    'bike.return.success' => '{channel, select,
sms {Bike {bikeNumber} returned to stand {standName}. Lock with code {currentCode}.}
other {<h3>Bike <span class="badge badge-primary">{bikeNumber}</span> returned to stand <span class="badge badge-primary">{standName}</span>. Lock with code <span class="badge badge-primary">{currentCode}</span>.</h3> Please, rotate the lockpad to 0000 when leaving. Wipe the bike clean if it is dirty, please.}}{hasNote, select, true {
You have also reported this problem: {note}.} other {}}{hasCreditChange, select, true {
Credit change: -{creditChange}{creditCurrency}.} other {}}',

    'bike.revert.success' => '{channel, select,
sms {Bike {bikeNumber} reverted to {standName} with code {code}.}
other {<h3>Bike <span class="badge badge-primary">{bikeNumber}</span> reverted to <span class="badge badge-primary">{standName}</span> with code <span class="badge badge-primary">{code}</span>.</h3>}}',

    'bike.rent.error.not_found' => 'Bike {bikeNumber} does not exist.',
    'bike.rent.error.already_rented_by_current_user' => 'You have already rented the bike {bikeNumber}. Code is {currentCode}.',
    'bike.rent.error.already_rented' => 'Bike {bikeNumber} is already rented.',
    'bike.rent.error.insufficient_credit' => 'You are below required credit {minRequiredCredit}{creditCurrency}. Please, recharge your credit.',
    'bike.rent.error.zero_limit' => 'You can not rent any bikes. Contact the admins to lift the ban.',
    'bike.rent.error.limit' => 'You can only rent {count, plural, one {# bike} other {# bikes}} at once.',
    'bike.rent.error.service_stand' => 'Renting from service stands is not allowed: The bike probably waits for a repair.',
    'bike.rent.error.inactive_stand' => 'Renting from inactive stands is not allowed.',
    'bike.rent.error.stack_top_bike' => 'Bike {bikeNumber} is not rentable now, you have to rent bike {stackTopBike} from this stand.',

    'bike.return.error.stand_not_found' => 'Stand name \'{standName}\' does not exist. Stands are marked by CAPITALLETTERS.',
    'bike.return.error.no_rented_bikes' => 'You currently have no rented bikes.',
    'bike.return.error.invalid_bike_number' => 'Invalid bike number',
    'bike.return.error.multiple_rented_bikes' => 'You have {bikeNumber} rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use {hasSms, select, true {web or SMS} other {web}} to return the bikes.',

    'bike.revert.error.not_rented' => 'Bicycle {bikeNumber} is not rented right now. Revert not successful!',
    'bike.revert.error.no_stand_or_code' => 'No last stand or code for bicycle {bikeNumber} found. Revert not successful!',
    'bike.revert.error.not_supported' => 'Revert is not supported for QR code',
];
