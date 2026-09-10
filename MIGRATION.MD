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


## Spec 0013: application-owned pairing with the existing pairActionId

The contract is terminal RETURN/FORCERETURN/REVERT -> opening RENT/FORCERENT.
Starts retain NULL. No columns, foreign keys, unique pair constraints, CHECK rules,
triggers or stored procedures are added. Pairing rules belong to the application.

Apply `migrations/0013/01-indexes.sql` once to prepare the ordinary lookup index.
The existing non-unique pairActionId index remains. Both indexes only support reads;
they do not enforce event relationships. Rehearse index creation on a deployment copy.
This foundation does not activate a writer or update history.

At coordinated activation, pause/drain every old rental writer, capture the maximum
history.id as a fixed deployment boundary, and reconcile rentals already in progress.
The new core finds active starts above this boundary; it must not treat old unpaired
RENT rows as live. The boundary is configuration and must not advance on restart.

Historical pair backfill may run after new writes start. Archive original values,
restrict updates to the old range, resolve reverse/duplicate/missing/wrong-type links,
and leave uncertain pairs NULL. Do not rewrite the live range or replay old commands.
There is no later SQL constraint-enforcement phase. Migration tooling validates its
own writes in code, just as the live writer does.

The application owns existence/type/bike/holder validation and the single-closure
rule. Every rental writer must use the same transaction/locking protocol: lock the
bike, read and validate current state, then persist the transition. InnoDB transactions
and row locks provide atomicity and serialization; direct SQL bypasses domain rules.

`DbInterface::transactional()` commits the callback result or rolls back on any
Throwable. Nested calls are rejected before running the inner callback. Callbacks
must not issue DDL or manual transaction-control SQL. Repositories share the same
connection; the transaction owner decides when to notify external services.
