<?php

return [
    'bike.rent.success' => '{channel, select,
sms {Kolo {bikeNumber}: Otevřít kódem {currentCode}. Okamžitě změňte kód na {newCode}.}
other {<h3>Kolo <span class="badge badge-primary">{bikeNumber}</span>: Otevřít kódem <span class="badge badge-primary">{currentCode}</span>. Okamžitě změňte kód na <span class="badge badge-primary">{newCode}</span></h3> (otevřít, otočit kovovou část, nastavit nový kód, otočit kovovou část zpět).}}{hasNote, select, true {
Nahlášený problém: {note}} other {}}',

    'bike.return.success' => '{channel, select,
sms {Kolo {bikeNumber} vráceno na stojan {standName}. Zamknout kódem {currentCode}.}
other {<h3>Kolo <span class="badge badge-primary">{bikeNumber}</span> vráceno na stojan <span class="badge badge-primary">{standName}</span>. Zamknout kódem <span class="badge badge-primary">{currentCode}</span>.</h3> Prosím, při odchodu otočte zámek na 0000. Pokud je kolo špinavé, prosím, otřete ho.}}{hasNote, select, true {
Tento problém jste také nahlásili: {note}.} other {}}{hasCreditChange, select, true {
Změna kreditu: -{creditChange}{creditCurrency}.} other {}}',

    'bike.revert.success' => '{channel, select,
sms {Kolo {bikeNumber} vráceno na {standName} s kódem {code}.}
other {<h3>Kolo <span class="badge badge-primary">{bikeNumber}</span> vráceno na <span class="badge badge-primary">{standName}</span> s kódem <span class="badge badge-primary">{code}</span>.</h3>}}',

    'bike.rent.error.not_found' => 'Kolo {bikeNumber} neexistuje.',
    'bike.rent.error.already_rented_by_current_user' => 'Už jste si půjčili kolo {bikeNumber}. Kód je {currentCode}.',
    'bike.rent.error.already_rented' => 'Kolo {bikeNumber} je již půjčeno.',
    'bike.rent.error.insufficient_credit' => 'Máte méně než požadovaný kredit {minRequiredCredit}{creditCurrency}. Prosím, dobijte si kredit.',
    'bike.rent.error.zero_limit' => 'Nemůžete si půjčit žádná kola. Kontaktujte administrátory, aby zrušili zákaz.',
    'bike.rent.error.limit' => 'Můžete si najednou půjčit pouze {count, plural, one {# kolo} few {# kola} other {# kol}}.',
    'bike.rent.error.service_stand' => 'Půjčování ze servisních stojanů není povoleno: Kolo pravděpodobně čeká na opravu.',
    'bike.rent.error.inactive_stand' => 'Půjčování z neaktivních stojanů není povoleno.',
    'bike.rent.error.stack_top_bike' => 'Kolo {bikeNumber} není momentálně možné si půjčit, musíte si půjčit kolo {stackTopBike} z tohoto stojanu.',

    'bike.return.error.stand_not_found' => 'Název stojanu \'{standName}\' neexistuje. Stojany jsou označeny VELKÝMI PÍSMENY.',
    'bike.return.error.no_rented_bikes' => 'Momentálně nemáte půjčená žádná kola.',
    'bike.return.error.invalid_bike_number' => 'Neplatné číslo kola',
    'bike.return.error.multiple_rented_bikes' => 'Momentálně máte půjčeno {bikeNumber} kol. Vrácení pomocí QR kódu lze použít pouze tehdy, je-li půjčeno 1 kolo. K vrácení kol použijte {hasSms, select, true {web nebo SMS} other {web}}.',

    'bike.revert.error.not_rented' => 'Kolo {bikeNumber} není momentálně půjčeno. Vrácení neúspěšné!',
    'bike.revert.error.no_stand_or_code' => 'Pro kolo {bikeNumber} nebyl nalezen poslední stojan nebo kód. Vrácení neúspěšné!',
    'bike.revert.error.not_supported' => 'Vrácení není podporováno pro QR kód',
];
