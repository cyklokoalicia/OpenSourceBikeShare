OpenSourceBikeShare Installation Manual
============

Database setup
----------
1. Use `create-database.sql` file to create MariaDB/MySQL database with the system tables.
2. Create an admin user in the table `users` with privileges 7.
3. Create the bike stands in the table `stands`, use 0 (zero) for a `serviceTag` to enable the stand or 1 to disable it. Use the same name for `standName` and `placeName`. `standPhoto` is a URL pointing to an image.
4. Create the bicycles in the system in the table `bikes`. Use `standId` from the `stands` table for `currentStand` and set `currentCode` to random four digit code.

Config.php.example setup
----------
1. Open `config.php.example` and change basic variables (self-descriptive) to suit your system.
2. Set `$countryCode` to your country;'s international dialing code - no plus sign, no zeroes.
3. _Optional:_ Enable notifications in the `$watches` variables section to notify admins, if conditions met.
4. _Optional:_ Enable paid (credit) system in the `$credit` variables section, if you want charge the users for bike rental based on time and more.
5. Edit database details and fill in correct info.
6. _Optional:_ If you want to have SMS enable in addition to the web app, set the `$connectors["sms"]` variable to your provider's file in `connectors/` directory.

CRON job
----------
1. _Optional:_ If you have some `$watches` notifications enabled, add `cron.php` to be called once a day.

User registration
----------
1. Point users to yourweb/register.php to register.

Connectors (SMS provider files)
----------
1. See `eurosms.php` connector file for basic structure of the API connector.
2. Set the basic variables (provided by your SMS gateway API) such as API username, API password/key, system phone number etc.
3. SMS receiving requires SMS text, sender, time and unique ID to be set.
4. SMS sending requires `Send()` function to be edited.
5. If your SMS provider expects a response after SMS is received via API, edit `Respond()` function as needed (usually responding with SMS unique ID).

Need help to set it up?
---------
**We are also available to help you to set up your own bike sharing system** including the real world part (the stands, bicycles, locks etc.).

We will talk to you about the expectations, situation, bicycle theft, potential users and **provide you with help to launch your own successful bike sharing system**.

First consultation is free, **get in touch**: [consult@whitebikes.info](mailto:consult@whitebikes.info)