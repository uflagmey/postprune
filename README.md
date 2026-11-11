# Post Prune (uflagmey/postprune)

[Deutsch](#deutsch) · [English](#english)

---

## Deutsch

phpBB-3.3-Extension: löscht in ausgewählten Foren **einzelne Beiträge** automatisch und **endgültig**, sobald sie älter als eine einstellbare Anzahl Tage sind, inklusive ihrer Dateianhänge.

Das eingebaute Themen-Pruning von phpBB löscht ganze Themen nach Inaktivität. Diese Extension arbeitet auf Beitragsebene und lässt sich mit dem Themen-Pruning kombinieren.

> **Beta (1.0.0-beta3):** Erst auf einem Testboard oder mit Backup + Trockenlauf einsetzen.

> **⚠ Achtung: Die Löschung ist hart und unwiderruflich.**
> Gelöschte Beiträge landen nicht im Papierkorb (kein Soft-Delete). Beiträge, Dateianhänge (auch die Dateien in `files/`), Suchindex-Einträge, Meldungen und Benachrichtigungen sind danach weg. Ohne Backup gibt es keinen Weg zurück.

### Voraussetzungen

- phpBB 3.3.3 oder neuer (3.3.x), PHP 7.2 oder neuer
- Keine Composer-Abhängigkeiten, kein Eingriff in den phpBB-Kern

### Installation

> **Download:** [Release v1.0.0-beta3](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta3) → Datei `postprune-1.0.0-beta3.zip` (alle Versionen: [Releases](https://github.com/uflagmey/postprune/releases)).
> **Nicht** über „Code → Download ZIP“ installieren: Das ist der Quellcode mit falscher Ordnerstruktur (`postprune-main/`) und kein Installationspaket.

1. Das Release-ZIP entpacken und den Ordner `uflagmey/postprune/` nach `ext/` hochladen, so dass `ext/uflagmey/postprune/ext.php` existiert.
2. Im ACP unter **Anpassen → Erweiterungen verwalten** „Post Prune“ aktivieren.
3. Damit ist noch nichts eingeschaltet: Alle Foren stehen auf **Aus**.

Deinstallation: Erweiterung deaktivieren und anschließend **Arbeitsdaten löschen**. Dabei werden die Spalten (auch die Themen-Markierungen) und Konfigurationswerte der Extension vollständig entfernt.

### Update von einer früheren Version

1. **Backup** von Datenbank und `files/`.
2. Die neuen Dateien hochladen und den Ordner `ext/uflagmey/postprune/` dabei überschreiben.
3. ACP → **Anpassen → Erweiterungen verwalten**: „Post Prune“ **deaktivieren** und gleich wieder **aktivieren**. **Nicht** „Arbeitsdaten löschen“ wählen: Das entfernt alle Einstellungen der Extension.

Die Einstellungen der Foren bleiben dabei erhalten. Der neue Geltungsbereich steht für alle Foren auf „Ganzes Forum“, das Verhalten ist also zunächst genau wie vorher.

Wird Schritt 3 ausgelassen (oder nur der Cache geleert), geht nichts kaputt: Die Extension arbeitet weiter wie die Vorversion, nur die neuen Funktionen erscheinen nicht. Sobald der Cache geleert wurde, steht im Forum-Formular im ACP ein Hinweis, dass die Erweiterung einmal deaktiviert und wieder aktiviert werden muss.

### Erste Schritte / Häufige Stolpersteine

1. **Einstellungen finden:** ACP → **Foren** (*Foren verwalten*). Steht das Forum in einer Kategorie, zuerst auf den **Namen der Kategorie** klicken. Dann beim eigentlichen Forum auf das **grüne Zahnrad** („Ändern“) klicken und nach unten scrollen bis zum Block **„Beitrags-Pruning (einzelne Beiträge)“**, direkt unter „Einstellungen zum automatischen Löschen“.
2. **⚠ Nicht verwechseln** mit ACP → Foren → **„Automatisches Löschen inaktiver Themen“**. Das ist das phpBB-eigene, manuelle Löschen von Themen: Es löscht beim Absenden **sofort ganze Themen**, ohne Trockenlauf. Mit dieser Extension hat es nichts zu tun.
3. **Der Cron läuft nur durch Seitenaufrufe im Forum**, nicht durch Seitenaufrufe im ACP. Pro Seitenaufruf erledigt phpBB höchstens **eine** fällige Aufgabe, ausgewählt per Zufall unter allen fälligen Aufgaben. War gerade keine Aufgabe fällig, prüft phpBB bis zu 60 Sekunden lang nicht erneut. Also nach dem Einschalten ein paar Forenseiten aufrufen (im Browser, als normaler Besucher) und dann im **Administrations-Protokoll** nachsehen (ACP → Wartung).
4. **Kein Eintrag im Protokoll?** Prüfen:
   - Ist der Modus im Forum wirklich gespeichert (Formular mit „Absenden“ bestätigt)?
   - Ist das Forum ein normales Beitragsforum (keine Kategorie, kein Link)?
   - Steht unter *Allgemein → Server-Konfiguration → Server und Domain* „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ auf **Deaktiviert**? Ist es aktiviert, startet phpBB keine Aufgaben über Seitenaufrufe, dann braucht es einen System-Cronjob (siehe unten).
   - Der Trockenlauf schreibt pro abgeschlossenem Zyklus **immer** einen Eintrag, auch wenn kein Beitrag alt genug ist („… würde kein Beitrag gelöscht“). Fehlt dieser Eintrag, ist der Cron noch nicht gelaufen. (In 1.0.0-beta1 gab es bei 0 Kandidaten noch keinen Eintrag.)
5. **Markierungen wirken nur mit „Nur markierte Themen“.** Steht der Geltungsbereich auf „Ganzes Forum“, werden alle Themen des Forums gekürzt und die Markierungen ignoriert. Das ACP weist darauf hin, wenn im Forum Themen markiert sind.
6. **Verschieben entfernt die Markierung.** Wird ein markiertes Thema in ein anderes Forum verschoben, verliert es seine Markierung, mit Eintrag im Administrations-Protokoll. Das gilt auch, wenn ein Forum gelöscht oder umgewandelt und sein Inhalt dabei verschoben wird. Im Zielforum muss das Thema bei Bedarf neu markiert werden.
7. **Zusammenführen in ein markiertes Thema:** Die übernommenen Beiträge gehören danach zum markierten Thema und werden künftig mitgekürzt. Sie behalten ihr ursprüngliches Datum: Sind sie schon alt genug, werden sie beim nächsten Lauf gelöscht.
8. **Menüeintrag „Automatisch kürzen“ fehlt?** Er erscheint nur für Administratoren mit dem Recht *Kann Foren verwalten* (`a_forum`) und nur, wenn das Update abgeschlossen ist (siehe „Update von einer früheren Version“).
9. **Datenbank-Backup zurückgespielt?** Danach **immer zuerst den Cache leeren** (ACP → Allgemein → „Cache leeren“), bevor die Erweiterung deaktiviert und wieder aktiviert wird. Sonst arbeitet phpBB noch mit den zwischengespeicherten Werten aus der Zeit vor dem Zurückspielen: Stammt das Backup aus der Zeit vor dem Update, bricht die geplante Aufgabe bis zum Cache-Leeren mit einem Datenbankfehler ab, und das Markieren von Themen kann mit einem Fehler 403 enden. Abhilfe, falls es schon passiert ist: Cache leeren, Erweiterung deaktivieren und wieder aktivieren (**nicht** „Arbeitsdaten löschen“). Einstellungen und Markierungen bleiben dabei erhalten.

### Einstellungen pro Forum

ACP → **Foren → Forum bearbeiten**, Block **„Beitrags-Pruning (einzelne Beiträge)“** direkt unter den Pruning-Einstellungen. Der Block erscheint nur bei normalen Foren (nicht bei Kategorien oder Links). Kategorien und Link-Foren werden vom Cron auch dann ignoriert, wenn dort Werte gespeichert sind.

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Modus | Aus | **Aus**: nichts passiert. **Trockenlauf**: es wird nur protokolliert, wie viele Beiträge gelöscht würden. **Aktiv**: Beiträge werden gelöscht. |
| Geltungsbereich | Ganzes Forum | **Ganzes Forum**: alle Themen des Forums. **Nur markierte Themen**: nur Themen, die in der Themenansicht markiert wurden (siehe „Nur bestimmte Themen kürzen“). Darunter listet das ACP die markierten Themen des Forums. |
| Alter der Beiträge | 30 Tage | Beiträge, die älter als so viele Tage sind (1–65535). |
| Ersten Beitrag eines Themas behalten | Ja | Ja: Der Startbeitrag bleibt und damit das Thema. Nein: Ein Thema verschwindet, sobald alle seine Beiträge alt sind. Ist bei „Ja“ auch der Startbeitrag alt, bleibt er stehen, bis ggf. das Themen-Pruning von phpBB greift. |
| Wichtige Themen und Bekanntmachungen ausnehmen | Ja | Beiträge in wichtigen Themen, Bekanntmachungen und globalen Bekanntmachungen werden nie gelöscht. Gilt nur beim Geltungsbereich „Ganzes Forum“, dort auch für markierte Themen (die Markierung wird dort ignoriert). Nur beim Geltungsbereich „Nur markierte Themen“ wird ein markiertes Thema auch dann gekürzt, wenn es wichtig oder eine Bekanntmachung ist. |
| Beitragszähler der Benutzer reduzieren | Nein | Nein (wie beim Themen-Pruning von phpBB): Der Beitragszähler des Verfassers bleibt. Ja: Er sinkt für jeden gelöschten (freigegebenen) Beitrag. |

Ungeprüfte und soft-gelöschte Beiträge werden ebenfalls nach Alter gelöscht. Die Board-Statistik (Anzahl Beiträge und Themen) wird in beiden Fällen korrigiert.

### Nur bestimmte Themen kürzen (Sammelthemen)

Oft soll nicht ein ganzes Forum gekürzt werden, sondern nur ein **Sammelthema** darin. Beispiel: Im Forum „Unterhaltung Rätsel Spiele“ gibt es die „Witzeecke“, in der täglich Bilder gepostet werden und `files/` füllen, daneben andere Themen wie „Jeden Tag ein Wetterbild“, die bleiben sollen. Ziel: In der Witzeecke werden Antworten gelöscht, die älter als 30 Tage sind. Alle anderen Themen bleiben unangetastet.

1. **Backup** von Datenbank und `files/`.
2. **Thema markieren:** Das Thema „Witzeecke“ öffnen. Unten im Menü **Schnellmoderation** „**Automatisch kürzen: einschalten**“ wählen und bestätigen. Der Eintrag ist nur für Administratoren mit dem Recht *Kann Foren verwalten* sichtbar. Die Markierung wird im Administrations-Protokoll vermerkt.
3. **Forum einstellen:** ACP → Foren → „Unterhaltung Rätsel Spiele“ bearbeiten, Block „Beitrags-Pruning“:
   - Geltungsbereich: **Nur markierte Themen**. Die Liste „Markierte Themen“ zeigt jetzt die Witzeecke.
   - Alter der Beiträge: **30** Tage.
   - Ersten Beitrag eines Themas behalten: **Ja**. Das Thema bleibt bestehen, nur alte Antworten verschwinden.
   - Modus: **Trockenlauf**, dann „Absenden“.
4. **Trockenlauf prüfen:** Nach dem nächsten Cron-Lauf steht im Administrations-Protokoll z. B. „Trockenlauf: Beitrags-Pruning – 120 Beiträge in 1 markierten Thema im Forum „Unterhaltung Rätsel Spiele“ würden gelöscht“. Administratoren sehen im Thema den Hinweis „Trockenlauf aktiv: …“. Gelöscht wird noch nichts.
5. **Aktiv schalten**, wenn die Zahl plausibel ist. Ab dann sehen alle Besucher in der Witzeecke den Hinweis „Beiträge in diesem Thema, die älter als 30 Tage sind, werden automatisch gelöscht. Der erste Beitrag bleibt erhalten.“ Die anderen Themen des Forums zeigen keinen Hinweis und werden nicht gekürzt.

Markierung entfernen: an derselben Stelle „Automatisch kürzen: ausschalten“. Ist bei „Nur markierte Themen“ kein Thema markiert, wird nichts gelöscht, und das Protokoll vermerkt einmal pro Zyklus „keine markierten Themen“.

### Empfohlenes Vorgehen

1. **Erst ein Backup** von Datenbank und `files/`.
2. **Dann Trockenlauf**: Modus auf „Trockenlauf“ stellen und abwarten. Im **Administrations-Protokoll** steht danach „Trockenlauf: Beitrags-Pruning – N Beiträge im Forum „…“ würden gelöscht“. Es wird nichts gelöscht.
3. **Dann Aktiv**, wenn die Zahl plausibel ist. Jede Löschung wird im Administrations-Protokoll vermerkt („Beitrags-Pruning – N Beiträge im Forum „…“ gelöscht“).

### Wann läuft das Pruning?

- Der Cron-Task `uflagmey.postprune.cron.task.prune` läuft standardmäßig einmal täglich (`uflagmey_pp_gc`, 86400 Sekunden).
- Pro Lauf werden über alle Foren höchstens **200 Beiträge** gelöscht (`uflagmey_pp_batch`), damit es auf Shared Hosting keine Timeouts gibt. Ist mehr zu tun, läuft der Task bei den nächsten Seitenaufrufen weiter, bis der Rückstand abgearbeitet ist. Trockenlauf-Foren zählen dabei nicht mit und werden einmal pro Zyklus protokolliert, auch mit 0 Kandidaten.
- **Der phpBB-Cron hängt an Seitenaufrufen.** Ohne Besucher läuft nichts. Optional: Im ACP unter *Allgemein → Server-Konfiguration → Server und Domain* „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ aktivieren und `php bin/phpbbcli.php cron:run` regelmäßig (z. B. alle 5 Minuten) per Cronjob ausführen.
- Einmalig von Hand: `php bin/phpbbcli.php cron:run uflagmey.postprune.cron.task.prune`
  **Hinweis:** `cron:run` mit Task-Namen **ignoriert `should_run()`**, also das Tagesintervall. Der Task läuft dann sofort, unabhängig vom letzten Lauf, und schreibt bei Trockenlauf-Foren erneut einen Protokolleintrag.

### Technik

Gelöscht wird ausschließlich über die phpBB-Kernfunktion `delete_posts()` (`includes/functions_admin.php`). Sie entfernt Anhänge samt Dateien, Suchindex, Meldungen und Benachrichtigungen und synchronisiert Themen und Foren. Die Extension enthält keine eigenen DELETE-Statements.

Ob ein Thema dem Pruning unterliegt, entscheidet eine einzige Regel (`query_builder::topic_where()`). Cron, Trockenlauf und der Hinweis im Thema nutzen sie gemeinsam, deshalb zeigt der Hinweis genau die Themen, die der Cron kürzt.

---

## English

phpBB 3.3 extension: automatically and **permanently** deletes **individual posts** in selected forums once they are older than a configurable number of days, including their attachments.

phpBB's built-in topic pruning removes whole topics after inactivity. This extension works on single posts and can be combined with topic pruning.

> **Beta (1.0.0-beta3):** Use it on a test board first, or with a backup + dry run.

> **⚠ Warning: deletion is hard and irreversible.**
> Deleted posts are not soft-deleted. Posts, attachments (including the files in `files/`), search index entries, reports and notifications are gone. Without a backup there is no way back.

### Requirements

- phpBB 3.3.3 or newer (3.3.x), PHP 7.2 or newer
- No Composer dependencies, no core changes

### Installation

> **Download:** [Release v1.0.0-beta3](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta3) → file `postprune-1.0.0-beta3.zip` (all versions: [Releases](https://github.com/uflagmey/postprune/releases)).
> Do **not** install from “Code → Download ZIP”: that is the source code with the wrong folder layout (`postprune-main/`), not an installation package.

1. Unpack the release ZIP and upload the folder `uflagmey/postprune/` to `ext/`, so that `ext/uflagmey/postprune/ext.php` exists.
2. In the ACP go to **Customise → Manage extensions** and enable “Post Prune”.
3. Nothing is switched on yet: every forum starts in mode **Off**.

Uninstall: disable the extension, then **Delete data**. This removes the extension's columns (including the topic marks) and config values completely.

### Updating from an earlier version

1. **Backup** of database and `files/`.
2. Upload the new files, overwriting the folder `ext/uflagmey/postprune/`.
3. ACP → **Customise → Manage extensions**: **disable** “Post Prune” and **enable** it again right away. Do **not** choose “Delete data”: that removes all of the extension's settings.

Your forum settings are kept. The new scope is set to “Whole forum” for every forum, so the behaviour is exactly as before at first.

If step 3 is skipped (or only the cache is purged), nothing breaks: the extension keeps working like the previous version, only the new features do not appear. Once the cache has been purged, the forum form in the ACP shows a notice that the extension has to be disabled and enabled once.

### Getting started / common pitfalls

1. **Finding the settings:** ACP → **Forums** (*Manage forums*). If the forum sits inside a category, first click the **category name**. Then click the **green cog** (“Edit”) of the actual forum and scroll down to the block **“Post pruning (individual posts)”**, right below “Forum prune settings”.
2. **⚠ Do not confuse** this with ACP → Forums → **“Prune forums”**. That is phpBB's own manual topic pruning: on submit it **immediately deletes whole topics**, without a dry run. It has nothing to do with this extension.
3. **The cron only runs on page requests in the forum**, not on page requests in the ACP. Each page request runs at most **one** due task, picked at random among all due tasks. If no task was due, phpBB does not check again for up to 60 seconds. So after switching it on, open a few forum pages (in a browser, as a normal visitor) and then look at the **admin log** (ACP → Maintenance).
4. **No log entry?** Check:
   - Is the mode really saved for the forum (form confirmed with “Submit”)?
   - Is the forum a normal forum (not a category, not a link)?
   - Is “Run periodic tasks from operating system cron” under *General → Server configuration → Server settings* set to **Disabled**? If it is enabled, phpBB does not start tasks on page requests, so you need a system cron job (see below).
   - The dry run **always** writes one entry per completed cycle, even if no post is old enough (“No posts … would be deleted”). If that entry is missing, the cron has not run yet. (In 1.0.0-beta1 there was no entry with 0 candidates.)
5. **Marks only take effect with “Marked topics only”.** With the scope “Whole forum” all topics of the forum are pruned and the marks are ignored. The ACP points this out when topics in the forum are marked.
6. **Moving removes the mark.** A marked topic moved to another forum loses its mark, with an admin log entry. This also applies when a forum is deleted or converted and its content is moved. Mark the topic again in the target forum if needed.
7. **Merging into a marked topic:** the merged posts then belong to the marked topic and are pruned from then on. They keep their original date: if they are already old enough, they are deleted on the next run.
8. **Menu entry “Prune automatically” missing?** It is only shown to administrators with the permission *Can manage forums* (`a_forum`), and only once the update is complete (see “Updating from an earlier version”).
9. **Restored a database backup?** Afterwards **always purge the cache first** (ACP → General → “Purge the cache”) before disabling and enabling the extension. Otherwise phpBB keeps working with the cached values from before the restore: if the backup is from before the update, the scheduled task fails with a database error until the cache is purged, and marking topics can end with an error 403. Remedy if it has already happened: purge the cache, disable and enable the extension (**not** “Delete data”). Settings and marks are kept.

### Settings per forum

ACP → **Forums → Edit forum**, block **“Post pruning (individual posts)”** right below the prune settings. It is shown for normal forums only (not for categories or links). The cron ignores categories and link forums even if values are stored there.

| Setting | Default | Meaning |
|---|---|---|
| Mode | Off | **Off**: nothing happens. **Dry run**: only logs how many posts would be deleted. **Active**: posts are deleted. |
| Scope | Whole forum | **Whole forum**: all topics of the forum. **Marked topics only**: only topics marked in the topic view (see “Pruning only certain topics”). Below it the ACP lists the forum's marked topics. |
| Post age | 30 days | Posts older than this many days (1–65535). |
| Keep first post of each topic | Yes | Yes: the first post and therefore the topic stays. No: a topic disappears once all of its posts are old. With “Yes” an old first post stays until phpBB's topic pruning (if enabled) removes the topic. |
| Skip stickies and announcements | Yes | Posts in stickies, announcements and global announcements are never deleted. Applies with the scope “Whole forum” only, and there to marked topics as well (the mark is ignored there). Only with the scope “Marked topics only” is a marked topic pruned even if it is a sticky or an announcement. |
| Reduce user post counts | No | No (like phpBB's topic pruning): the author's post count stays. Yes: it is reduced for every deleted (approved) post. |

Unapproved and soft-deleted posts are deleted by age as well. The board statistics (number of posts and topics) are corrected either way.

### Pruning only certain topics (collection topics)

Often not a whole forum should be pruned, but only one **collection topic** in it. Example: the forum “Fun, puzzles, games” contains the “Joke corner”, where images are posted every day and fill `files/`, next to other topics such as “A weather photo every day” that should stay. Goal: replies in the Joke corner older than 30 days are deleted, all other topics stay untouched.

1. **Backup** of database and `files/`.
2. **Mark the topic:** open the topic “Joke corner”. In the **Quick-mod tools** menu at the bottom choose “**Prune automatically: switch on**” and confirm. The entry is only visible to administrators with the permission *Can manage forums*. The mark is recorded in the admin log.
3. **Set up the forum:** ACP → Forums → edit “Fun, puzzles, games”, block “Post pruning”:
   - Scope: **Marked topics only**. The list “Marked topics” now shows the Joke corner.
   - Post age: **30** days.
   - Keep first post of each topic: **Yes**. The topic stays, only old replies go.
   - Mode: **Dry run**, then “Submit”.
4. **Check the dry run:** after the next cron run the admin log shows e.g. “Dry run: post pruning – 120 posts in 1 marked topic in forum “Fun, puzzles, games” would be deleted”. Administrators see the notice “Dry run active: …” in the topic. Nothing is deleted yet.
5. **Switch to Active** once the number looks right. From then on all visitors see the notice “Posts in this topic older than 30 days are deleted automatically. The first post is kept.” in the Joke corner. The other topics of the forum show no notice and are not pruned.

Removing the mark: same place, “Prune automatically: switch off”. If no topic is marked with “Marked topics only”, nothing is deleted, and the log says “no marked topics” once per cycle.

### Recommended procedure

1. **Backup first**: database and `files/`.
2. **Then dry run**: set the mode to “Dry run” and wait. The **admin log** then shows “Dry run: post pruning – N posts in forum “…” would be deleted”. Nothing is deleted.
3. **Then Active**, once the number looks right. Every deletion is recorded in the admin log (“Post pruning – N posts deleted in forum “…””).

### When does pruning run?

- The cron task `uflagmey.postprune.cron.task.prune` runs once a day by default (`uflagmey_pp_gc`, 86400 seconds).
- Each run deletes at most **200 posts** across all forums (`uflagmey_pp_batch`) to avoid timeouts on shared hosting. If there is more to do, the task continues on the next page requests until the backlog is gone. Dry-run forums do not count towards that limit and are logged once per cycle, even with 0 candidates.
- **phpBB's cron is triggered by page requests.** No visitors, no run. Optionally enable “Run periodic tasks from operating system cron” in the ACP (*General → Server configuration → Server settings*) and run `php bin/phpbbcli.php cron:run` regularly (e.g. every 5 minutes) from a cron job.
- One-off by hand: `php bin/phpbbcli.php cron:run uflagmey.postprune.cron.task.prune`
  **Note:** `cron:run` with a task name **ignores `should_run()`**, i.e. the daily interval. The task runs immediately regardless of the last run, and dry-run forums get another log entry.

### Technical notes

Deletion goes exclusively through the phpBB core function `delete_posts()` (`includes/functions_admin.php`). It removes attachments including their files, search index entries, reports and notifications and resyncs topics and forums. The extension contains no DELETE statements of its own.

Whether a topic is subject to pruning is decided by one single rule (`query_builder::topic_where()`). The cron, the dry run and the topic notice share it, so the notice shows exactly the topics the cron prunes.

---

License: [GPL-2.0-only](license.txt) · © 2026 Uwe Flagmeyer
