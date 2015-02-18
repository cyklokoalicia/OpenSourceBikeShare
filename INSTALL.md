Open Source Bike Share Installation Manual
============

Database setup
----------
1. Use `create-database.sql` file to create MariaDB/MySQL database with the system tables.
2. Create the bike stands in the table `stands`, use 0 (zero) for a `serviceTag` to enable the stand or 1 to disable it. Use the same name for `standName` and `placeName`. `standPhoto` is a URL pointing to an image.
3. Create the bicycles in the system in the table `bikes`. Use `standId` from the `stands` table for `currentStand` and set `currentCode` to random four digit code.

Config.php.example setup
----------
1. Open `config.php.example`, rename it to `config.php` and change basic variables (self-descriptive) to suit your system.
2. Edit `system` (server etc.) and `database` details and fill in correct info.
3. _Optional:_ Enable notifications in the `$watches` variables section to notify admins, if conditions met.
4. _Optional:_ Enable paid (credit) system in the `$credit` variables section, if you want charge the users for bike rental based on time and more.
5. _Optional:_ Leave `$connectors["sms"]` empty to disable SMS system or set the `$connectors["sms"]` variable to your provider's filename in `connectors/` directory.
6. _Optional:_ If you have enabled SMS system, set `$countryCode` to your country's international dialing code - no plus sign, no zeroes.

Admin user registration
----------
1. Go to yourweb/register.php to register a user.
2. If SMS system is enabled, there will be a two step registration with a phone number verification. Otherwise, there is just a one step registration.
3. If SMS system is disabled, your "username" will be displayed after successful registration (it is a `userId`).
4. Edit the first user in table `users` and change privileges of your admin user to `7`.

SMS system testing (with loopback SMS connector)
----------
1. Open `connectors/loopback/phone.php` and set `$usenumber` variable to the phone `number` of admin user from _step 4_ of _admin user registration_.
2. Set permissions to 777 for `connectors/loopback/loopback.log` file.
3. Open yourweb/connectors/loopback/phone.php in your browser.
4. Test loopback connector by sending `HELP` command.
5. If you receive message back, everything works fine.

Watches and notifications (config.php)
----------
Open Source Bicycle Share is a community run system and therefore requires some additional precautions to secure the bicycles.
You can set these using `$watches` variable. Any violations from the set rules will be reported to the admins (with `privileges` 2+) by SMS and by email `$watches["email"]`.
Watch and report:
* if bicycle is taken out of stack, if $forcestack is enabled (see below): `$watches["stack"]`.
* if any long rentals occur: `$watches["longrental"]`. In addition, if `$notifyuser` is set to 1, user will be notified as well to his phone.
* if any user has rented too many bikes during a short period: `$watches["timetoomany"]` and `$watches["numbertoomany"]`.

Additional variables are used by the credit system (if enabled):
* Free rental time: `$watches["freetime"]`.
* Flat price cycle length after first paid period: `$watches["flatpricecycle"]`.
* Double price cycle length after first paid period: `$watches["doublepricecycle"]`.
* Double price cycle capping: `$watches["doublepricecyclecap"]`.

Bicycle stacking at stands (config.php)
----------
You can decide to allow rentals of any bicycle at a stand or only the latest bicycle left at the stand for the security reasons. Use `$forcestack` variable.
This will require certain bicycle/stand setup to work.

Credit system (config.php)
----------
You can have either credit system enabled (charging per rentals) or disabled (free rentals). Set `$creditsystem` variable accordingly.
* Choose your system currency - it could be real money or "points" or whatever. Set `$creditcurrency` accordingly, e.g. `€` or `$`.
* Set `$credit["min"]` to minimum credit required for any bike operations. Note: users are required to have total of `$credit["min"]`+`$credit["rent"]`+`$credit["longrental"]` to rent a bicycle to prevent long term rentals by users with minimum credit.
* Decide what you want to charge for the rentals and set `$credit["pricecycle"]` accordingly:
    0. One-time only price: 0
    1. Flat price every $watches["flatpricecycle"] minutes: 1
    2. Doubled price every $watches["doublepricecycle"] minutes: 2 (with capping at $watches["doublepricecyclecap"] cycles)
* Set additional charge for long rentals (e.g. over 24 hours) using `$credit["longrental"]` variable.
* If you want to allow users to temporarily increase their rental limit, set `$limits["increase"]` to number of bikes allowed in addition to their limit. Also set `$credit["limitincrease"]` to require credits for this operation.
* Set system violation fee using `$credit["violation"]`.

CRON job
----------
1. _Optional:_ If you have some `$watches` notifications enabled, add `cron.php` to be called once a day.

User registration
----------
* Set `$limits["registration"]` to number of bikes user can rent after he registered. 0 is recommended, if you run a community system (admin can change this limit after verification).
* Point users to yourweb/register.php to register.

Connectors (SMS provider / gateway API files)
----------
1. See `eurosms.php` connector file for basic structure of the API connector.
2. Set the basic variables (provided by your SMS gateway API) such as API username, API password/key, system phone number etc.
3. SMS receiving requires SMS text, sender, time and unique ID to be set.
4. SMS sending requires `Send()` function to be edited.
5. If your SMS provider expects a response after SMS is received via API, edit `Respond()` function as needed (usually responding with SMS unique ID).

Statistics (in Google Analytics)
----------
System is (Universal) Google Analytics ready.
1. Create file `analytics.php` with Google Analytics Javascript code (in <script>...</script> tags).
2. User actions will be reported as Events. This includes bicycle rentals, returns, notes etc.

Need help to set it up?
---------
**We are also available to help you to set up your own bike sharing system** including the real world part (the stands, bicycles, locks etc.).

We will talk to you about the expectations, situation, bicycle theft, potential users and **provide you with help to launch your own successful bike sharing system**.

First consultation is free, **get in touch**: [consult@whitebikes.info](mailto:consult@whitebikes.info)