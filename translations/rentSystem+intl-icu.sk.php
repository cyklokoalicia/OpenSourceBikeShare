<?php

return [
    'bike.rent.success' => '{channel, select,
sms {Bicykel {bikeNumber}: Otvoriť kódom {currentCode}. Okamžite zmeňte kód na {newCode}.}
other {<h3>Bicykel <span class="badge badge-primary">{bikeNumber}</span>: Otvoriť kódom <span class="badge badge-primary">{currentCode}</span>. Okamžite zmeňte kód na <span class="badge badge-primary">{newCode}</span></h3> (otvoriť, otočiť kovovú časť, nastaviť nový kód, otočiť kovovú časť späť).}}{hasNote, select, true {
Nahlásený problém: {note}} other {}}',

    'bike.return.success' => '{channel, select,
sms {Bicykel {bikeNumber} vrátený na stojan {standName}. Zamknúť kódom {currentCode}.}
other {<h3>Bicykel <span class="badge badge-primary">{bikeNumber}</span> vrátený na stojan <span class="badge badge-primary">{standName}</span>. Zamknúť kódom <span class="badge badge-primary">{currentCode}</span>.</h3> Pri odchode otočte zámok na 0000. Ak je bicykel špinavý, prosím utrite ho.}}{hasNote, select, true {
Tento problém ste tiež nahlásili: {note}.} other {}}{hasCreditChange, select, true {
Zmena kreditu: -{creditChange}{creditCurrency}.} other {}}',

    'bike.revert.success' => '{channel, select,
sms {Bicykel {bikeNumber} vrátený na {standName} s kódom {code}.}
other {<h3>Bicykel <span class="badge badge-primary">{bikeNumber}</span> vrátený na <span class="badge badge-primary">{standName}</span> s kódom <span class="badge badge-primary">{code}</span>.</h3>}}',

    'bike.rent.error.not_found' => 'Bicykel {bikeNumber} neexistuje.',
    'bike.rent.error.already_rented_by_current_user' => 'Už ste si požičali bicykel {bikeNumber}. Kód je {currentCode}.',
    'bike.rent.error.already_rented' => 'Bicykel {bikeNumber} je už požičaný.',
    'bike.rent.error.insufficient_credit' => 'Máte menej ako požadovaný kredit {minRequiredCredit}{creditCurrency}. Prosím, dobite si kredit.',
    'bike.rent.error.zero_limit' => 'Nemôžete si požičať žiadne bicykle. Kontaktujte administrátorov, aby zrušili zákaz.',
    'bike.rent.error.limit' => 'Môžete si naraz požičať len {count, plural, one {# bicykel} few {# bicykle} other {# bicyklov}}.',
    'bike.rent.error.service_stand' => 'Požičiavanie zo servisných stojanov nie je povolené: Bicykel pravdepodobne čaká na opravu.',
    'bike.rent.error.inactive_stand' => 'Požičiavanie z neaktívnych stojanov nie je povolené.',
    'bike.rent.error.stack_top_bike' => 'Bicykel {bikeNumber} momentálne nie je možné si požičať, musíte si požičať bicykel {stackTopBike} z tohto stojanu.',

    'bike.return.error.stand_not_found' => 'Názov stojanu \'{standName}\' neexistuje. Stojany sú označené VEĽKÝMI PÍSMENAMI.',
    'bike.return.error.no_rented_bikes' => 'Momentálne nemáte požičané žiadne bicykle.',
    'bike.return.error.invalid_bike_number' => 'Neplatné číslo bicykla',
    'bike.return.error.multiple_rented_bikes' => 'Momentálne máte požičaných {bikeNumber} bicyklov. Vrátenie pomocou QR kódu sa dá použiť len vtedy, keď je požičaný 1 bicykel. Na vrátenie bicyklov použite {hasSms, select, true {web alebo SMS} other {web}}.',

    'bike.revert.error.not_rented' => 'Bicykel {bikeNumber} momentálne nie je požičaný. Vrátenie neúspešné!',
    'bike.revert.error.no_stand_or_code' => 'Pre bicykel {bikeNumber} nebol nájdený posledný stojan alebo kód. Vrátenie neúspešné!',
    'bike.revert.error.not_supported' => 'Vrátenie nie je podporované pre QR kód',
];
