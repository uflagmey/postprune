# Post Prune (uflagmey/postprune)

[Deutsch](#deutsch) · [English](#english)

---

## Deutsch

phpBB-3.3-Extension: löscht in ausgewählten Foren **einzelne Beiträge** automatisch und **endgültig**, sobald sie älter als eine einstellbare Anzahl Tage sind, inklusive ihrer Dateianhänge.

Das eingebaute Themen-Pruning von phpBB löscht ganze Themen nach Inaktivität. Diese Extension arbeitet auf Beitragsebene und lässt sich mit dem Themen-Pruning kombinieren.

> **Beta (1.0.0-beta1):** Erst auf einem Testboard oder mit Backup + Trockenlauf einsetzen.

> **⚠ Achtung: Die Löschung ist hart und unwiderruflich.**
> Gelöschte Beiträge landen nicht im Papierkorb (kein Soft-Delete). Beiträge, Dateianhänge (auch die Dateien in `files/`), Suchindex-Einträge, Meldungen und Benachrichtigungen sind danach weg. Ohne Backup gibt es keinen Weg zurück.

### Voraussetzungen

- phpBB 3.3.x, PHP 7.2 oder neuer
- Keine Composer-Abhängigkeiten, kein Eingriff in den phpBB-Kern

### Installation

> **Download:** [Release v1.0.0-beta1](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta1) → Datei `postprune-1.0.0-beta1.zip` (alle Versionen: [Releases](https://github.com/uflagmey/postprune/releases)).
> **Nicht** über „Code → Download ZIP“ installieren: Das ist der Quellcode mit falscher Ordnerstruktur (`postprune-main/`) und kein Installationspaket.

1. Das Release-ZIP entpacken und den Ordner `uflagmey/postprune/` nach `ext/` hochladen, so dass `ext/uflagmey/postprune/ext.php` existiert.
2. Im ACP unter **Anpassen → Erweiterungen verwalten** „Post Prune“ aktivieren.
3. Damit ist noch nichts eingeschaltet: Alle Foren stehen auf **Aus**.

Deinstallation: Erweiterung deaktivieren und anschließend **Daten löschen**. Dabei werden die Spalten und Konfigurationswerte der Extension vollständig entfernt.

### Erste Schritte / Häufige Stolpersteine

1. **Einstellungen finden:** ACP → **Foren** (*Foren verwalten*). Steht das Forum in einer Kategorie, zuerst auf den **Namen der Kategorie** klicken. Dann beim eigentlichen Forum auf das **grüne Zahnrad** („Ändern“) klicken und nach unten scrollen bis zum Block **„Beitrags-Pruning (einzelne Beiträge)“**, direkt unter „Einstellungen zum automatischen Löschen“.
2. **⚠ Nicht verwechseln** mit ACP → Foren → **„Automatisches Löschen inaktiver Themen“**. Das ist das phpBB-eigene, manuelle Löschen von Themen: Es löscht beim Absenden **sofort ganze Themen**, ohne Trockenlauf. Mit dieser Extension hat es nichts zu tun.
3. **Der Cron läuft nur durch Seitenaufrufe im Forum**, nicht durch Seitenaufrufe im ACP. Pro Seitenaufruf erledigt phpBB höchstens **eine** fällige Aufgabe, ausgewählt per Zufall unter allen fälligen Aufgaben. War gerade keine Aufgabe fällig, prüft phpBB bis zu 60 Sekunden lang nicht erneut. Also nach dem Einschalten ein paar Forenseiten aufrufen (im Browser, als normaler Besucher) und dann im **Administrations-Protokoll** nachsehen (ACP → Wartung).
4. **Kein Eintrag im Protokoll?** Prüfen:
   - Ist der Modus im Forum wirklich gespeichert (Formular mit „Absenden“ bestätigt)?
   - Ist das Forum ein normales Beitragsforum (keine Kategorie, kein Link)?
   - Steht unter *Allgemein → Server-Konfiguration → Server und Domain* „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ auf **Deaktiviert**? Ist es aktiviert, startet phpBB keine Aufgaben über Seitenaufrufe, dann braucht es einen System-Cronjob (siehe unten).
   - Der Trockenlauf schreibt pro abgeschlossenem Zyklus **immer** einen Eintrag, auch wenn kein Beitrag alt genug ist („… würde kein Beitrag gelöscht“). Fehlt dieser Eintrag, ist der Cron noch nicht gelaufen. (In 1.0.0-beta1 gab es bei 0 Kandidaten noch keinen Eintrag.)

### Einstellungen pro Forum

ACP → **Foren → Forum bearbeiten**, Block **„Beitrags-Pruning (einzelne Beiträge)“** direkt unter den Pruning-Einstellungen. Der Block erscheint nur bei normalen Foren (nicht bei Kategorien oder Links). Kategorien und Link-Foren werden vom Cron auch dann ignoriert, wenn dort Werte gespeichert sind.

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Modus | Aus | **Aus**: nichts passiert. **Trockenlauf**: es wird nur protokolliert, wie viele Beiträge gelöscht würden. **Aktiv**: Beiträge werden gelöscht. |
| Alter der Beiträge | 30 Tage | Beiträge, die älter als so viele Tage sind (1–65535). |
| Ersten Beitrag eines Themas behalten | Ja | Ja: Der Startbeitrag bleibt und damit das Thema. Nein: Ein Thema verschwindet, sobald alle seine Beiträge alt sind. Ist bei „Ja“ auch der Startbeitrag alt, bleibt er stehen, bis ggf. das Themen-Pruning von phpBB greift. |
| Wichtige Themen und Bekanntmachungen ausnehmen | Ja | Beiträge in wichtigen Themen, Bekanntmachungen und globalen Bekanntmachungen werden nie gelöscht. |
| Beitragszähler der Benutzer reduzieren | Nein | Nein (wie beim Themen-Pruning von phpBB): Der Beitragszähler des Verfassers bleibt. Ja: Er sinkt für jeden gelöschten (freigegebenen) Beitrag. |

Ungeprüfte und soft-gelöschte Beiträge werden ebenfalls nach Alter gelöscht. Die Board-Statistik (Anzahl Beiträge und Themen) wird in beiden Fällen korrigiert.

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

---

## English

phpBB 3.3 extension: automatically and **permanently** deletes **individual posts** in selected forums once they are older than a configurable number of days, including their attachments.

phpBB's built-in topic pruning removes whole topics after inactivity. This extension works on single posts and can be combined with topic pruning.

> **Beta (1.0.0-beta1):** Use it on a test board first, or with a backup + dry run.

> **⚠ Warning: deletion is hard and irreversible.**
> Deleted posts are not soft-deleted. Posts, attachments (including the files in `files/`), search index entries, reports and notifications are gone. Without a backup there is no way back.

### Requirements

- phpBB 3.3.x, PHP 7.2 or newer
- No Composer dependencies, no core changes

### Installation

> **Download:** [Release v1.0.0-beta1](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta1) → file `postprune-1.0.0-beta1.zip` (all versions: [Releases](https://github.com/uflagmey/postprune/releases)).
> Do **not** install from “Code → Download ZIP”: that is the source code with the wrong folder layout (`postprune-main/`), not an installation package.

1. Unpack the release ZIP and upload the folder `uflagmey/postprune/` to `ext/`, so that `ext/uflagmey/postprune/ext.php` exists.
2. In the ACP go to **Customise → Manage extensions** and enable “Post Prune”.
3. Nothing is switched on yet: every forum starts in mode **Off**.

Uninstall: disable the extension, then **Delete data**. This removes the extension's columns and config values completely.

### Getting started / common pitfalls

1. **Finding the settings:** ACP → **Forums** (*Manage forums*). If the forum sits inside a category, first click the **category name**. Then click the **green cog** (“Edit”) of the actual forum and scroll down to the block **“Post pruning (individual posts)”**, right below “Forum prune settings”.
2. **⚠ Do not confuse** this with ACP → Forums → **“Prune forums”**. That is phpBB's own manual topic pruning: on submit it **immediately deletes whole topics**, without a dry run. It has nothing to do with this extension.
3. **The cron only runs on page requests in the forum**, not on page requests in the ACP. Each page request runs at most **one** due task, picked at random among all due tasks. If no task was due, phpBB does not check again for up to 60 seconds. So after switching it on, open a few forum pages (in a browser, as a normal visitor) and then look at the **admin log** (ACP → Maintenance).
4. **No log entry?** Check:
   - Is the mode really saved for the forum (form confirmed with “Submit”)?
   - Is the forum a normal forum (not a category, not a link)?
   - Is “Run periodic tasks from operating system cron” under *General → Server configuration → Server settings* set to **Disabled**? If it is enabled, phpBB does not start tasks on page requests, so you need a system cron job (see below).
   - The dry run **always** writes one entry per completed cycle, even if no post is old enough (“No posts … would be deleted”). If that entry is missing, the cron has not run yet. (In 1.0.0-beta1 there was no entry with 0 candidates.)

### Settings per forum

ACP → **Forums → Edit forum**, block **“Post pruning (individual posts)”** right below the prune settings. It is shown for normal forums only (not for categories or links). The cron ignores categories and link forums even if values are stored there.

| Setting | Default | Meaning |
|---|---|---|
| Mode | Off | **Off**: nothing happens. **Dry run**: only logs how many posts would be deleted. **Active**: posts are deleted. |
| Post age | 30 days | Posts older than this many days (1–65535). |
| Keep first post of each topic | Yes | Yes: the first post and therefore the topic stays. No: a topic disappears once all of its posts are old. With “Yes” an old first post stays until phpBB's topic pruning (if enabled) removes the topic. |
| Skip stickies and announcements | Yes | Posts in stickies, announcements and global announcements are never deleted. |
| Reduce user post counts | No | No (like phpBB's topic pruning): the author's post count stays. Yes: it is reduced for every deleted (approved) post. |

Unapproved and soft-deleted posts are deleted by age as well. The board statistics (number of posts and topics) are corrected either way.

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

---

License: [GPL-2.0-only](license.txt) · © 2026 Uwe Flagmeyer
