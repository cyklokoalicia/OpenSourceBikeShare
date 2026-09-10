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


## Spec 0013: one existing pairActionId (staged)

The contract is terminal RETURN/FORCERETURN/REVERT -> opening RENT/FORCERENT.
Starts always retain NULL. No columns are added: pairActionId, standId and the
other event fields already exist. This foundation does not activate the new writer.

1. Apply `migrations/0013/01-expand.sql` once to prepare the lookup index. It leaves
   the columns and existing history values unchanged.
2. At coordinated writer activation, pause/drain every old rental writer and record
   the maximum history.id as a fixed deployment boundary. Reconcile rentals already
   in progress separately. The new core reads active starts above that boundary;
   it must not guess that every old unpaired RENT is still active. The boundary
   is configuration, not another database field, and must not advance on restart.
3. Historical pair backfill can run after new writes begin. Archive original values,
   restrict legacy updates to IDs at/below the boundary, clear starts' reverse links,
   and resolve duplicate/missing/wrong-type targets. Never rewrite the live range or
   replay old commands through the rental engine. Uncertain historical pairs stay NULL.
4. Apply `migrations/0013/02-enforce.sql` after legacy non-NULL pairs are normalized.
   Verify preflight under a write pause before adding the global constraints. Until
   then, the coordinated writer enforces live pairing under its transaction locks.
   This PR supplies no automatic backfill or activation command.

Rehearse SQL phases on a copy of the deployment; check ID signedness and supported
CHECK enforcement. The bootstrap SQL is for fresh databases and includes final
constraints. ALTER steps require checkpoints, not an application transaction rollback.
Re-running an already applied SQL phase is an error.

UNIQUE prevents repeated references; the FK checks existence. The direction CHECK
allows a non-NULL pair only on a terminal action. NULL terminal pairs remain allowed
for incomplete old history. The new writer must always supply a terminal's exact
start, check type/bike/holder, and reject multiple open starts under the bike lock.

`DbInterface::transactional()` commits the callback result or rolls back on any
Throwable. Nested calls are rejected before running the inner callback. Callbacks
must not issue DDL or manual transaction-control SQL. Repositories share the same
connection; the transaction owner decides when to notify external services.
