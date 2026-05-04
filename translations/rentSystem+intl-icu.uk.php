<?php

return [
    'bike.rent.success' => '{channel, select,
sms {Велосипед {bikeNumber}: відкрити кодом {currentCode}. Негайно змініть код на {newCode}.}
other {<h3>Велосипед <span class="badge badge-primary">{bikeNumber}</span>: відкрити кодом <span class="badge badge-primary">{currentCode}</span>. Негайно змініть код на <span class="badge badge-primary">{newCode}</span></h3> (відкрити, повернути металеву частину, встановити новий код, повернути металеву частину назад).}}{hasNote, select, true {
Повідомлено про проблему: {note}} other {}}',

    'bike.return.success' => '{channel, select,
sms {Велосипед {bikeNumber} повернуто на стоянку {standName}. Заблокуйте кодом {currentCode}.}
other {<h3>Велосипед <span class="badge badge-primary">{bikeNumber}</span> повернуто на стоянку <span class="badge badge-primary">{standName}</span>. Заблокуйте кодом <span class="badge badge-primary">{currentCode}</span>.</h3> Будь ласка, при від\'\'їзді поверніть замок на 0000. Якщо велосипед брудний, протріть його.}}{hasNote, select, true {
Ви також повідомили про цю проблему: {note}.} other {}}{hasCreditChange, select, true {
Зміна кредиту: -{creditChange}{creditCurrency}.} other {}}',

    'bike.revert.success' => '{channel, select,
sms {Велосипед {bikeNumber} повернуто на {standName} з кодом {code}.}
other {<h3>Велосипед <span class="badge badge-primary">{bikeNumber}</span> повернуто на <span class="badge badge-primary">{standName}</span> з кодом <span class="badge badge-primary">{code}</span>.</h3>}}',

    'bike.rent.error.not_found' => 'Велосипед {bikeNumber} не існує.',
    'bike.rent.error.already_rented_by_current_user' => 'Ви вже орендували велосипед {bikeNumber}. Код: {currentCode}.',
    'bike.rent.error.already_rented' => 'Велосипед {bikeNumber} вже орендовано.',
    'bike.rent.error.insufficient_credit' => 'Ваш кредит менший за необхідний {minRequiredCredit}{creditCurrency}. Будь ласка, поповніть кредит.',
    'bike.rent.error.zero_limit' => 'Ви не можете орендувати велосипеди. Зверніться до адміністраторів, щоб зняти заборону.',
    'bike.rent.error.limit' => 'Ви можете орендувати одночасно лише {count, plural, one {# велосипед} few {# велосипеди} other {# велосипедів}}.',
    'bike.rent.error.service_stand' => 'Оренда зі сервісних стоянок не дозволена: велосипед, ймовірно, чекає на ремонт.',
    'bike.rent.error.inactive_stand' => 'Оренда з неактивних стоянок не дозволена.',
    'bike.rent.error.stack_top_bike' => 'Велосипед {bikeNumber} зараз неможливо орендувати, ви маєте орендувати велосипед {stackTopBike} з цієї стоянки.',

    'bike.return.error.stand_not_found' => 'Назва стоянки \'{standName}\' не існує. Стоянки позначені ВЕЛИКИМИ ЛІТЕРАМИ.',
    'bike.return.error.no_rented_bikes' => 'У вас зараз немає орендованих велосипедів.',
    'bike.return.error.invalid_bike_number' => 'Неправильний номер велосипеда',
    'bike.return.error.multiple_rented_bikes' => 'У вас зараз орендовано {bikeNumber} велосипедів. Повернення через QR-код доступне лише коли орендовано 1 велосипед. Будь ласка, скористайтеся {hasSms, select, true {вебом або SMS} other {вебом}}, щоб повернути велосипеди.',

    'bike.revert.error.not_rented' => 'Велосипед {bikeNumber} зараз не орендований. Скасування неуспішне!',
    'bike.revert.error.no_stand_or_code' => 'Для велосипеда {bikeNumber} не знайдено останньої стоянки або коду. Скасування неуспішне!',
    'bike.revert.error.not_supported' => 'Скасування не підтримується для QR-коду',
];
