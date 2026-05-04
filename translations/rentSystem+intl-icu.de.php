<?php

return [
    'bike.rent.success' => '{channel, select,
sms {Fahrrad {bikeNumber}: Mit dem Code {currentCode} öffnen. Ändere den Code sofort auf {newCode}.}
other {<h3>Fahrrad <span class="badge badge-primary">{bikeNumber}</span>: Mit dem Code <span class="badge badge-primary">{currentCode}</span> öffnen. Ändere den Code sofort auf <span class="badge badge-primary">{newCode}</span></h3> (öffnen, Metallteil drehen, neuen Code einstellen, Metallteil zurückdrehen).}}{hasNote, select, true {
Gemeldetes Problem: {note}} other {}}',

    'bike.return.success' => '{channel, select,
sms {Fahrrad {bikeNumber} am Ständer {standName} zurückgegeben. Mit dem Code {currentCode} verschließen.}
other {<h3>Fahrrad <span class="badge badge-primary">{bikeNumber}</span> am Ständer <span class="badge badge-primary">{standName}</span> zurückgegeben. Mit dem Code <span class="badge badge-primary">{currentCode}</span> verschließen.</h3> Bitte drehe das Schloss beim Verlassen auf 0000. Wische das Fahrrad bitte sauber, wenn es schmutzig ist.}}{hasNote, select, true {
Du hast außerdem dieses Problem gemeldet: {note}.} other {}}{hasCreditChange, select, true {
Guthabenänderung: -{creditChange}{creditCurrency}.} other {}}',

    'bike.revert.success' => '{channel, select,
sms {Fahrrad {bikeNumber} wurde zu {standName} mit dem Code {code} zurückgesetzt.}
other {<h3>Fahrrad <span class="badge badge-primary">{bikeNumber}</span> wurde zu <span class="badge badge-primary">{standName}</span> mit dem Code <span class="badge badge-primary">{code}</span> zurückgesetzt.</h3>}}',

    'bike.rent.error.not_found' => 'Fahrrad {bikeNumber} existiert nicht.',
    'bike.rent.error.already_rented_by_current_user' => 'Du hast das Fahrrad {bikeNumber} bereits ausgeliehen. Der Code ist {currentCode}.',
    'bike.rent.error.already_rented' => 'Fahrrad {bikeNumber} ist bereits ausgeliehen.',
    'bike.rent.error.insufficient_credit' => 'Du hast weniger als das erforderliche Guthaben {minRequiredCredit}{creditCurrency}. Bitte lade dein Guthaben auf.',
    'bike.rent.error.zero_limit' => 'Du kannst keine Fahrräder ausleihen. Wende dich an die Administratoren, um die Sperre aufzuheben.',
    'bike.rent.error.limit' => 'Du kannst nur {count, plural, one {# Fahrrad} other {# Fahrräder}} gleichzeitig ausleihen.',
    'bike.rent.error.service_stand' => 'Ausleihen von Service-Ständern ist nicht erlaubt: Das Fahrrad wartet wahrscheinlich auf eine Reparatur.',
    'bike.rent.error.inactive_stand' => 'Ausleihen von inaktiven Ständern ist nicht erlaubt.',
    'bike.rent.error.stack_top_bike' => 'Fahrrad {bikeNumber} kann derzeit nicht ausgeliehen werden, du musst Fahrrad {stackTopBike} von diesem Ständer ausleihen.',

    'bike.return.error.stand_not_found' => 'Standname \'{standName}\' existiert nicht. Stände sind mit GROSSBUCHSTABEN markiert.',
    'bike.return.error.no_rented_bikes' => 'Du hast derzeit keine ausgeliehenen Fahrräder.',
    'bike.return.error.invalid_bike_number' => 'Ungültige Fahrradnummer',
    'bike.return.error.multiple_rented_bikes' => 'Du hast derzeit {bikeNumber} ausgeliehene Fahrräder. Die Rückgabe per QR-Code ist nur möglich, wenn 1 Fahrrad ausgeliehen ist. Bitte verwende {hasSms, select, true {Web oder SMS} other {Web}}, um die Fahrräder zurückzugeben.',

    'bike.revert.error.not_rented' => 'Fahrrad {bikeNumber} ist derzeit nicht ausgeliehen. Rückgängigmachen nicht erfolgreich!',
    'bike.revert.error.no_stand_or_code' => 'Für Fahrrad {bikeNumber} wurde kein letzter Ständer oder Code gefunden. Rückgängigmachen nicht erfolgreich!',
    'bike.revert.error.not_supported' => 'Rückgängigmachen wird für QR-Code nicht unterstützt',
];
