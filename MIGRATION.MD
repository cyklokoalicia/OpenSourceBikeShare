# Migration Guide from version 1.0.0
## DB Schema Changes

```sql
ALTER TABLE `users` ADD `isNumberConfirmed` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `users` ADD `registrationDate` DATETIME DEFAULT NOW();
UPDATE users u
SET u.registrationDate = (
    SELECT MIN(h.time)
    FROM history h
    WHERE h.userId = u.userId
);

ALTER TABLE `history` MODIFY `action` enum('RENT','RETURN','REVERT','FORCERENT','FORCERETURN','PHONE_CONFIRM_REQUEST','PHONE_CONFIRMED','EMAIL_CONFIRMED','CREDITCHANGE','CREDIT', 'CHANGECODE') NOT NULL,

ALTER TABLE `users` ADD COLUMN userLimit INT(11) UNSIGNED NOT NULL DEFAULT 0;
UPDATE users u JOIN limits l ON u.userId = l.userId SET u.userLimit = l.userLimit;
RENAME TABLE limits TO limits_delete;
#DROP TABLE limits_delete;

CREATE TABLE `userSettings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `userId` INT NOT NULL,
    `settings` JSON NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `stands`
    ADD COLUMN `status` ENUM('active','technical','hidden','inactive') NOT NULL DEFAULT 'active' AFTER `serviceTag`;
UPDATE `stands` SET `status` = 'technical' WHERE `serviceTag` <> 0;
ALTER TABLE `stands` DROP COLUMN `serviceTag`;

ALTER TABLE `stands`
    MODIFY COLUMN `status` ENUM('active','technical','hidden','inactive','virtual') NOT NULL DEFAULT 'active';

CREATE TABLE `userClient` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` INT(11) UNSIGNED NOT NULL,
    `platform` ENUM('android','ios') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `lastSeenAt` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `userId_platform` (`userId`, `platform`),
    KEY `lastSeenAt` (`lastSeenAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

## Environment Changes

Add the following to your `.env` (see `.env.dist` for the canonical default):

```
ANDROID_APP_ENABLED=true
```

Set to `false` on SMS-only or web-only deployments to skip per-request client-version
writes and hide the "Apps last seen on" section in the admin user editor.
Symfony will fail to boot if this variable is undefined.

When iOS support is added, a parallel `IOS_APP_ENABLED` variable will be introduced.

```
MESSENGER_CHATS_JSON=[]
```

Newcomers-chat invites shown on `/welcome` after the first login that follows a fresh
registration. JSON array of `{name, url}`. The messenger icon is auto-detected from the
URL host (`t.me`/`telegram.me` → Telegram, `wa.me`/`chat.whatsapp.com` → WhatsApp,
`signal.group`/`signal.me` → Signal, `invite.viber.com`/`viber.com` → Viber,
`discord.gg`/`discord.com` → Discord); unrecognized hosts fall back to a generic chat
icon. Example:

```
MESSENGER_CHATS_JSON=[{"name":"Newcomers TG","url":"https://t.me/example"}]
```

Empty array (`[]`, the default) disables the welcome redirect for new registrations.

## Rental history pairs

Fill missing `pairActionId` values and normalize unambiguous reverse links across the full history:

```bash
docker compose exec web php bin/console app:migrate_rental_history --dry-run -v
docker compose exec web php bin/console app:migrate_rental_history
```

Optionally use `--bike=6` to migrate one bike; `-v` includes event IDs and skip reasons.
Repeated return links are cleared only when an existing adjacent pair proves the rental
was already closed. Backward links from starts are cleared when the previous pair or
the start's own unique closing pair confirms the relationship. The summary counts both separately.
Returns pointing to a previous, already closed rental are reassigned only when the adjacent
start, holder, timestamps and references confirm the new pair. Reassignments are counted separately.
Verified legacy REVERT restoration records are reported separately and lose their rental links;
REVERT points to the canceled start when it is unambiguous. Backward links on interrupted starts
are cleared without inventing their missing completions. Existing pairs with reversed insertion order
are retained only when their timestamps, holder and unique references agree.
Initial returns, parked returns, unpaired REVERTs and empty service actions have distinct report categories.
Existing unique RENT/RETURN pairs from 2014, before November 12, are normalized to FORCERETURN
when the return actor differs from the renter. Only the return's action changes for this rule;
its user, time and pairActionId remain intact. Normalizations have a separate summary counter.
Without a reviewed repair file, other rules change only `pairActionId` and do not synthesize missing events.
Uncertain links remain unchanged.
The command can be rerun after interruption; it does not change bike state, charges or notifications.


For reviewed insertions and deletions, keep the JSON repair file as the recovery archive and run:
```bash
php bin/console app:migrate_rental_history --repairs=/path/to/repairs.json --dry-run -v
php bin/console app:migrate_rental_history --repairs=/path/to/repairs.json
```
The version-1 file contains `delete` entries (`reason`, complete original `event`, and `keep` for
`duplicate_start`) and `insert` entries (complete original `start` and `next`, plus the closing
`event` without an ID). Supported deletion reasons are `duplicate_start`, `failed_qr_return`,
and `unknown_action`. Times are UTC. Originals are checked before any writes; reruns skip
already applied repairs. The retained duplicate keeps its latest code and existing closing links.
Historical completions are recognized by their pairs even when inserted after newer rentals.
Inferred completion times and origin stands are reconstruction conventions, not recovered physical returns.
