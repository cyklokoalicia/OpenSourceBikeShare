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


## Spec 0013: single-pairActionId foundation (staged)

The new contract is terminal RETURN/FORCERETURN/REVERT -> opening RENT/FORCERENT.
Starts always retain NULL. No second rental-link column or currentRentId is added.
This foundation does not enable the new rental writer or reconstruct any history.

1. Back up the existing schema and history. Apply `migrations/0013/01-expand.sql`
   once to an existing installation. Nullable metadata leaves old rows unverified
   (`ledgerVersion IS NULL`), including any pre-existing pairActionId values.
2. **Defer `migrations/0013/02-enforce.sql` until the normalization/cutover phase.**
   Archive and validate old pairs, clear reverse links from starts, resolve or
   quarantine duplicate/missing targets, reconcile current bike holders, and pause
   every rental writer. This PR deliberately supplies no automatic normalization.
   After preflight succeeds, apply the constraints once and verify them before activation.

Both SQL phases must first be rehearsed on a copy of the actual deployment. Check
id/pairActionId signedness and supported CHECK enforcement. The bootstrap SQL is
for a fresh database and includes the final constraints; do not run it on existing
history. ALTER statements are checkpointed migration steps, not a single rollbackable
application transaction. Re-running an already applied SQL phase is an error.

UNIQUE prevents multiple terminal links; the FK checks existence only. The writer
must additionally check the referenced action, same bike, matching verified version,
and at most one open start under a bike lock. Metadata must be supplied explicitly
for version 1; legacy writes continue with NULL defaults. Do not mark all legacy
starts verified, or dangling old rows would become apparent active rentals.

`DbInterface::transactional()` commits the callback result or rolls back on any
Throwable. Nested calls are rejected before running the inner callback. Callbacks
must not issue DDL or manual transaction-control SQL. Repositories share the same
connection; the transaction owner decides when to notify external services.
