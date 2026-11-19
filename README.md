# Post Prune (uflagmey/postprune)

[Deutsch](#deutsch) · [English](#english)

---

## Deutsch

phpBB-3.3-Extension: löscht in ausgewählten Foren **einzelne Beiträge** automatisch und **endgültig**, inklusive ihrer Dateianhänge. Gelöscht wird **nach Alter** (älter als eine einstellbare Anzahl Tage) oder, in markierten Sammelthemen, **nach Anzahl** (je Thema bleiben nur die neuesten X Beiträge).

Das eingebaute Themen-Pruning von phpBB löscht ganze Themen nach Inaktivität. Diese Extension arbeitet auf Beitragsebene und lässt sich mit dem Themen-Pruning kombinieren.

> **Beta (1.0.0-beta4):** Erst auf einem Testboard oder mit Backup + Trockenlauf einsetzen.

> **⚠ Achtung: Die Löschung ist hart und unwiderruflich.**
> Gelöschte Beiträge landen nicht im Papierkorb (kein Soft-Delete). Beiträge, Dateianhänge (auch die Dateien in `files/`), Suchindex-Einträge, Meldungen und Benachrichtigungen sind danach weg. Ohne Backup gibt es keinen Weg zurück.

### Voraussetzungen

- phpBB 3.3.3 oder neuer (3.3.x); PHP 7.2 oder neuer
- Keine Composer-Abhängigkeiten, kein Eingriff in den phpBB-Kern

### Getestet mit

- phpBB 3.3.17 und 3.3.19
- Datenbanken: MySQL/MariaDB, PostgreSQL, SQLite. MS SQL Server und Oracle sind nicht getestet.

### Installation

> **Download:** [Release v1.0.0-beta4](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta4) → Datei `postprune-1.0.0-beta4.zip` (alle Versionen: [Releases](https://github.com/uflagmey/postprune/releases)).
> **Nicht** über „Code → Download ZIP“ installieren: Das ist der Quellcode mit falscher Ordnerstruktur (`postprune-main/`) und kein Installationspaket.

1. Das Release-ZIP entpacken und den Ordner `uflagmey/postprune/` nach `ext/` hochladen, so dass `ext/uflagmey/postprune/ext.php` existiert.
2. Im ACP unter **Anpassen → Erweiterungen verwalten** „Post Prune“ aktivieren.
3. Damit ist noch nichts eingeschaltet: Alle Foren stehen auf **Aus**.

Deinstallation: Erweiterung deaktivieren und anschließend **Arbeitsdaten löschen**. Dabei werden die Spalten (auch die Themen-Markierungen) und Konfigurationswerte der Extension vollständig entfernt.

### Update von einer früheren Version

Gilt für ein Update von 1.0.0-beta1 bis 1.0.0-beta4. Ab 1.0.0-beta3 ist phpBB 3.3.3 oder neuer nötig (1.0.0-beta1 lief noch ab 3.3.0); ein älteres Board zuerst aktualisieren.

1. **Backup** von Datenbank und `files/`.
2. Die neuen Dateien hochladen und den Ordner `ext/uflagmey/postprune/` dabei überschreiben.
3. ACP → **Anpassen → Erweiterungen verwalten**: „Post Prune“ **deaktivieren** und gleich wieder **aktivieren**. **Nicht** „Arbeitsdaten löschen“ wählen: Das entfernt alle Einstellungen der Extension.

Die Einstellungen der Foren bleiben dabei erhalten. Neue Einstellungen starten so, dass sich nichts ändert: Geltungsbereich „Ganzes Forum“, Löschregel „Nach Alter“.

Wird Schritt 3 ausgelassen (oder nur der Cache geleert), geht nichts kaputt: Die Extension arbeitet weiter wie die Vorversion, nur die neuen Funktionen erscheinen nicht. Sobald der Cache geleert wurde, steht im Forum-Formular im ACP ein Hinweis, dass die Erweiterung einmal deaktiviert und wieder aktiviert werden muss.

Nach dem Zurückspielen eines Datenbank-Backups immer den Cache leeren (ACP → Allgemein → „Cache leeren“), siehe Stolperstein 9.

### Erste Schritte / Häufige Stolpersteine

1. **Einstellungen finden:** ACP → **Foren** (*Foren verwalten*). Steht das Forum in einer Kategorie, zuerst auf den **Namen der Kategorie** klicken. Dann beim eigentlichen Forum auf das **grüne Zahnrad** („Ändern“) klicken und nach unten scrollen bis zum Block **„Beitrags-Pruning (einzelne Beiträge)“**, direkt unter „Einstellungen zum automatischen Löschen“.
2. **⚠ Nicht verwechseln** mit ACP → Foren → **„Automatisches Löschen inaktiver Themen“**. Das ist das phpBB-eigene, manuelle Löschen von Themen: Es löscht beim Absenden **sofort ganze Themen**, ohne Trockenlauf. Mit dieser Extension hat es nichts zu tun.
3. **Der Cron läuft nur durch Seitenaufrufe im Forum**, nicht durch Seitenaufrufe im ACP. Pro Seitenaufruf erledigt phpBB höchstens **eine** fällige Aufgabe, ausgewählt per Zufall unter allen fälligen Aufgaben. War gerade keine Aufgabe fällig, prüft phpBB bis zu 60 Sekunden lang nicht erneut. Also nach dem Einschalten ein paar Forenseiten aufrufen (im Browser, als normaler Besucher) und dann im **Administrations-Protokoll** nachsehen (ACP → Wartung).
4. **Kein Eintrag im Protokoll?** Prüfen:
   - Ist der Modus im Forum wirklich gespeichert? Die Zeile „Gespeichert für …“ im Block zeigt den gespeicherten Stand (siehe Stolperstein 10).
   - Ist das Forum ein normales Beitragsforum (keine Kategorie, kein Link)?
   - Steht unter *Allgemein → Server-Konfiguration → Server und Domain* „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ auf **Deaktiviert**? Ist es aktiviert, startet phpBB keine Aufgaben über Seitenaufrufe, dann braucht es einen System-Cronjob (siehe Stolperstein 11).
   - Der Trockenlauf schreibt pro abgeschlossenem Zyklus **immer** einen Eintrag, auch wenn kein Beitrag alt genug ist („… würde kein Beitrag gelöscht“). Fehlt dieser Eintrag, ist der Cron noch nicht gelaufen. (In 1.0.0-beta1 gab es bei 0 Kandidaten noch keinen Eintrag.)
5. **Markierungen wirken nur mit „Nur markierte Themen“.** Steht der Geltungsbereich auf „Ganzes Forum“, werden alle Themen des Forums gekürzt und die Markierungen ignoriert. Das ACP weist darauf hin, wenn im Forum Themen markiert sind.
6. **Verschieben entfernt die Markierung.** Wird ein markiertes Thema in ein anderes Forum verschoben, verliert es seine Markierung, mit Eintrag im Administrations-Protokoll. Das gilt auch, wenn ein Forum gelöscht oder umgewandelt und sein Inhalt dabei verschoben wird. Im Zielforum muss das Thema bei Bedarf neu markiert werden.
7. **Zusammenführen in ein markiertes Thema:** Die übernommenen Beiträge gehören danach zum markierten Thema und werden künftig mitgekürzt. Sie behalten ihr ursprüngliches Datum: Sind sie schon alt genug, werden sie beim nächsten Lauf gelöscht.
8. **Menüeintrag „Automatisch kürzen“ fehlt?** Er erscheint nur für Administratoren mit dem Recht *Kann Foren verwalten* (`a_forum`) und nur, wenn das Update abgeschlossen ist (siehe „Update von einer früheren Version“).
9. **Datenbank-Backup zurückgespielt?** Nach dem Zurückspielen eines Datenbank-Backups **immer den Cache leeren** (ACP → Allgemein → „Cache leeren“), und zwar bevor die Erweiterung deaktiviert und wieder aktiviert wird. Sonst arbeitet phpBB noch mit den zwischengespeicherten Werten aus der Zeit vor dem Zurückspielen: Stammt das Backup aus der Zeit vor dem Update, gilt bis zum Cache-Leeren: Die Seiten des Boards und die übrigen geplanten Aufgaben von phpBB laufen normal weiter, Post Prune löscht aber nichts und schreibt einmal pro Zyklus „Post Prune: Datenbankschema passt nicht zum Cache – bitte Cache leeren“ ins Fehler-Protokoll (ACP → Wartung). Das Markieren von Themen kann mit einem Fehler 403 enden. Nach dem Cache-Leeren löscht Post Prune ab dem nächsten Zyklus wieder (Standard: einen Tag nach dem Eintrag im Fehler-Protokoll). Abhilfe, falls es schon passiert ist: Cache leeren, Erweiterung deaktivieren und wieder aktivieren (**nicht** „Arbeitsdaten löschen“). Einstellungen und Markierungen bleiben dabei erhalten.
10. **Gespeichert wird erst mit „Absenden“** ganz unten im Forum-Formular. Wer im Block etwas ändert und die Seite verlässt, hat nichts gespeichert. Die Zeile **„Gespeichert für „<Forum>“: …“** oben im Block zeigt immer den Stand aus der Datenbank, z. B. „Trockenlauf · Nur markierte Themen (1) · 30 Tage · ersten Beitrag behalten“. Weichen die Felder darunter davon ab, sind diese Änderungen noch nicht gespeichert (das passiert z. B., wenn phpBB das Formular nach einem Fehler erneut anzeigt).
11. **⚠ System-Cron nur mit Cronjob für alle Aufgaben.** Die Option „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ (ACP → Allgemein → Server-Konfiguration → Server und Domain) **nicht** einschalten, solange auf dem Server kein Cronjob läuft, der **alle** Aufgaben (`php bin/phpbbcli.php cron:run`, **ohne** Task-Namen) alle paar Minuten ausführt. Ist die Option an, startet phpBB **keine** Aufgabe mehr über Seitenaufrufe. Ein Cronjob nur für Post Prune (`cron:run uflagmey.postprune.cron.task.prune`) legt dann den Rest des Boards lahm: E-Mail-Versand, Benachrichtigungen, Aufräumarbeiten usw. laufen nicht mehr.

### Einstellungen pro Forum

ACP → **Foren** → beim Forum auf **„Ändern“** (grünes Zahnrad) klicken, Block **„Beitrags-Pruning (einzelne Beiträge)“** direkt unter den Pruning-Einstellungen. Der Block erscheint nur bei normalen Foren (nicht bei Kategorien oder Links). Kategorien und Link-Foren werden vom Cron auch dann ignoriert, wenn dort Werte gespeichert sind.

Oben im Block stehen der Hinweis, dass erst „Absenden“ speichert, die Zeile **„Gespeichert für „<Forum>“: …“** mit dem gespeicherten Stand und darunter die Schaltfläche **„Trockenlauf jetzt berechnen“** (siehe „Vorschau / manuell auslösen“).

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Modus | Aus | **Aus**: nichts passiert. **Trockenlauf**: es wird nur protokolliert, wie viele Beiträge gelöscht würden. **Aktiv**: Beiträge werden gelöscht. |
| Geltungsbereich | Ganzes Forum | **Ganzes Forum**: alle Themen des Forums. **Nur markierte Themen**: nur Themen, die in der Themenansicht markiert wurden (siehe „Nur bestimmte Themen kürzen“). Darunter listet das ACP die markierten Themen des Forums. |
| Löschregel | Nach Alter | **Nach Alter**: Beiträge, die älter als „Alter der Beiträge“ sind. **Nach Anzahl**: je Thema bleiben nur die neuesten „Anzahl behalten“ freigegebenen Beiträge, ältere werden gelöscht. „Nach Anzahl“ geht nur mit „Nur markierte Themen“ (sonst Fehlermeldung beim Speichern). |
| Alter der Beiträge | 30 Tage | Nur bei „Nach Alter“: Beiträge, die älter als so viele Tage sind (1–65535). |
| Anzahl behalten | 100 | Nur bei „Nach Anzahl“: so viele der neuesten freigegebenen Beiträge bleiben je Thema (1–65535). Mit „Ersten Beitrag behalten“ bleibt der Startbeitrag **zusätzlich** stehen, er zählt nicht mit. |
| Ersten Beitrag eines Themas behalten | Ja | Ja: Der Startbeitrag bleibt und damit das Thema. Nein: Ein Thema verschwindet, sobald alle seine Beiträge alt sind. Ist bei „Ja“ auch der Startbeitrag alt, bleibt er stehen, bis ggf. das Themen-Pruning von phpBB greift. |
| Wichtige Themen und Bekanntmachungen ausnehmen | Ja | Beiträge in wichtigen Themen, Bekanntmachungen und globalen Bekanntmachungen werden nie gelöscht. Gilt nur beim Geltungsbereich „Ganzes Forum“, dort auch für markierte Themen (die Markierung wird dort ignoriert). Nur beim Geltungsbereich „Nur markierte Themen“ wird ein markiertes Thema auch dann gekürzt, wenn es wichtig oder eine Bekanntmachung ist. |
| Beitragszähler der Benutzer reduzieren | Nein | Nein (wie beim Themen-Pruning von phpBB): Der Beitragszähler des Verfassers bleibt. Ja: Er sinkt für jeden gelöschten (freigegebenen) Beitrag. |

Ungeprüfte und soft-gelöschte Beiträge werden ebenfalls gelöscht: nach Alter wie alle anderen; nach Anzahl zählen sie nicht zu den behaltenen Beiträgen, werden aber gelöscht, wenn sie älter als der älteste behaltene Beitrag sind. Die Board-Statistik (Anzahl Beiträge und Themen) wird in allen Fällen korrigiert.

### Nur bestimmte Themen kürzen (Sammelthemen)

Oft soll nicht ein ganzes Forum gekürzt werden, sondern nur ein **Sammelthema** darin. Beispiel: Im Forum „Plauderecke“ gibt es das Thema „Witze-Sammlung“, in dem täglich Bilder gepostet werden und `files/` füllen, daneben andere Themen wie „Vorstellungsrunde“, die bleiben sollen. Ziel: In der Witze-Sammlung werden Antworten gelöscht, die älter als 30 Tage sind. Alle anderen Themen bleiben unangetastet.

1. **Backup** von Datenbank und `files/`.
2. **Thema markieren:** Das Thema „Witze-Sammlung“ öffnen. Unten im Menü **Schnellmoderation** „**Automatisch kürzen: einschalten**“ wählen und bestätigen. Der Eintrag ist nur für Administratoren mit dem Recht *Kann Foren verwalten* sichtbar. Die Markierung wird im Administrations-Protokoll vermerkt.
3. **Forum einstellen:** ACP → Foren → beim Forum „Plauderecke“ auf „Ändern“ klicken, Block „Beitrags-Pruning“:
   - Geltungsbereich: **Nur markierte Themen**. Die Liste „Markierte Themen“ zeigt jetzt die Witze-Sammlung.
   - Alter der Beiträge: **30** Tage.
   - Ersten Beitrag eines Themas behalten: **Ja**. Das Thema bleibt bestehen, nur alte Antworten verschwinden.
   - Modus: **Trockenlauf**, dann „Absenden“.
4. **Trockenlauf prüfen:** Nach dem nächsten Cron-Lauf steht im Administrations-Protokoll z. B. „Trockenlauf: Beitrags-Pruning – 120 Beiträge in 1 markierten Thema im Forum „Plauderecke“ würden gelöscht“. Administratoren sehen im Thema den Hinweis „Trockenlauf aktiv: …“. Gelöscht wird noch nichts.
5. **Aktiv schalten**, wenn die Zahl plausibel ist. Ab dann sehen alle Besucher in der Witze-Sammlung den Hinweis „Beiträge in diesem Thema, die älter als 30 Tage sind, werden automatisch gelöscht. Der erste Beitrag bleibt erhalten.“ Die anderen Themen des Forums zeigen keinen Hinweis und werden nicht gekürzt.

Markierung entfernen: an derselben Stelle „Automatisch kürzen: ausschalten“. Ist bei „Nur markierte Themen“ kein Thema markiert, wird nichts gelöscht, und das Protokoll vermerkt einmal pro Zyklus „keine markierten Themen“.

#### Sammelthema: nur die neuesten 100 Beiträge behalten

Statt nach Alter lässt sich ein Sammelthema auch auf eine feste Länge begrenzen. Beispiel: In der Witze-Sammlung sollen immer nur die **neuesten 100 Beiträge** stehen bleiben, egal wie alt sie sind, dazu der Startbeitrag.

1. **Backup**, Thema markieren wie oben (Schritte 1 und 2).
2. **Forum einstellen:** Geltungsbereich **Nur markierte Themen**, Löschregel **Nach Anzahl**, Anzahl behalten **100**, Ersten Beitrag eines Themas behalten **Ja**, Modus **Trockenlauf**, „Absenden“. Die Zeile oben im Block zeigt danach „Gespeichert für „Plauderecke“: Trockenlauf · Nur markierte Themen (1) · neueste 100 je Thema behalten · ersten Beitrag behalten“.
3. **Sofort prüfen:** „Trockenlauf jetzt berechnen“ zeigt z. B. „→ 340 Beiträge würden gelöscht (ältester vom …, jüngster zu löschender vom …)“. Nach dem nächsten Cron-Lauf steht dieselbe Zahl im Administrations-Protokoll („… (neueste 100 je Thema behalten)“).
4. **Aktiv schalten.** Besucher sehen im Thema „In diesem Thema bleiben nur die neuesten 100 Beiträge erhalten, sowie der erste Beitrag.“

Gezählt werden nur **freigegebene** Beiträge (ohne Startbeitrag). Ein Thema mit **weniger als 100** freigegebenen Beiträgen verliert nichts, auch keine ungeprüften oder soft-gelöschten. Ab **genau 100** gilt die Grenze: Die freigegebenen bleiben alle, ungeprüfte und soft-gelöschte Beiträge, die älter als der älteste davon sind, werden gelöscht. Neue Beiträge verschieben die Grenze: Nach 5 neuen Antworten werden beim nächsten Lauf die 5 ältesten gelöscht. Ein großer Rückstand wird in Portionen abgebaut (höchstens 200 Beiträge pro Lauf, siehe unten).

### Vorschau / manuell auslösen

**Schaltfläche „Trockenlauf jetzt berechnen“** im Block (ACP → Foren → beim Forum auf „Ändern“ klicken): Sie zeigt sofort, was ein Trockenlauf mit den **gespeicherten** Einstellungen jetzt löschen würde, z. B. „→ 3.541 Beiträge würden gelöscht (ältester vom 02.03.2019 14:05, jüngster zu löschender vom 15.08.2026 09:12)“. Sie funktioniert in jedem Modus, auch bei „Aus“, also schon vor dem Einschalten.

- Es wird **nichts gelöscht** und nichts protokolliert; der Cron-Zeitplan bleibt unberührt.
- Gezählt wird mit derselben Regel wie Cron, Trockenlauf und Hinweis im Thema, ohne Limit pro Lauf. Die Zahl entspricht dem Eintrag „Trockenlauf …“ im Protokoll beim nächsten Cron-Lauf, sofern sich bis dahin nichts ändert.
- **Ungespeicherte Änderungen zählen nicht.** Erst „Absenden“, dann die Vorschau. Die Zeile „Gespeichert für …“ direkt darüber zeigt, welche Werte gerechnet werden.
- **Ohne JavaScript** lädt die Schaltfläche die Seite neu; das Ergebnis steht danach unter der Schaltfläche. Ungespeicherte Änderungen im Formular gehen dabei verloren.
- Nur für Administratoren mit *Kann Foren verwalten* (`a_forum`), und erst wenn das Update abgeschlossen ist.

**Für Fortgeschrittene:**

- `php bin/phpbbcli.php cron:run uflagmey.postprune.cron.task.prune` startet den Task sofort und **ignoriert das Tagesintervall**. Trockenlauf-Foren bekommen dabei einen weiteren Protokolleintrag, Aktiv-Foren löschen **wirklich**.
- Web-Cron-URL `https://<board>/app.php/cron/uflagmey.postprune.cron.task.prune` (bei URL-Rewriting ohne `app.php`): Läuft nur, wenn der Task fällig ist, **hält sich also an das Intervall**. Zum Erzwingen ungeeignet.

### Empfohlenes Vorgehen

1. **Erst ein Backup** von Datenbank und `files/`.
2. **Dann Trockenlauf**: Einstellungen speichern, mit „Trockenlauf jetzt berechnen“ sofort die Zahl prüfen, Modus auf „Trockenlauf“ stellen und abwarten. Im **Administrations-Protokoll** steht danach „Trockenlauf: Beitrags-Pruning – N Beiträge im Forum „…“ würden gelöscht“. Es wird nichts gelöscht.
3. **Dann Aktiv**, wenn die Zahl plausibel ist. Jede Löschung wird im Administrations-Protokoll vermerkt („Beitrags-Pruning – N Beiträge im Forum „…“ gelöscht“).

### Wann läuft das Pruning?

- Der Cron-Task `uflagmey.postprune.cron.task.prune` läuft standardmäßig einmal täglich (`uflagmey_pp_gc`, 86400 Sekunden).
- Pro Lauf werden über alle Foren höchstens **200 Beiträge** gelöscht (`uflagmey_pp_batch`), damit es auf Shared Hosting keine Timeouts gibt. Ist mehr zu tun, läuft der Task bei den nächsten Seitenaufrufen weiter, bis der Rückstand abgearbeitet ist. Trockenlauf-Foren zählen dabei nicht mit und werden einmal pro Zyklus protokolliert, auch mit 0 Kandidaten.
- **Der phpBB-Cron hängt an Seitenaufrufen.** Ohne Besucher läuft nichts.
- **⚠ System-Cron:** Die Option „Wiederkehrende Aufgaben über Cron-Job des Betriebssystems ausführen“ nur einschalten, wenn ein Server-Cronjob **alle** Aufgaben ausführt, also `php bin/phpbbcli.php cron:run` **ohne** Task-Namen alle paar Minuten (z. B. alle 5). Ein Cronjob nur für Post Prune legt den Rest des Boards lahm (E-Mail-Versand, Benachrichtigungen usw.), siehe Stolperstein 11.
- Sofort auslösen und Web-Cron: siehe „Vorschau / manuell auslösen“.

### Technik

Gelöscht wird ausschließlich über die phpBB-Kernfunktion `delete_posts()` (`includes/functions_admin.php`). Sie entfernt Anhänge samt Dateien, Suchindex, Meldungen und Benachrichtigungen und synchronisiert Themen und Foren. Die Extension enthält keine eigenen DELETE-Statements.

Ob ein Thema dem Pruning unterliegt, entscheidet eine einzige Regel (`query_builder::topic_where()`). Cron, Trockenlauf, Vorschau und der Hinweis im Thema nutzen sie gemeinsam, deshalb zeigt der Hinweis genau die Themen, die der Cron kürzt, und die Vorschau dieselbe Zahl wie der Trockenlauf.

Die Löschregel „Nach Anzahl“ kommt ohne Window-Funktionen aus: Je Thema wird der X-te neueste freigegebene Beitrag als Grenze gelesen, gelöscht wird alles davor (nach Zeit, bei gleicher Zeit nach Beitrags-ID).

---

## English

phpBB 3.3 extension: automatically and **permanently** deletes **individual posts** in selected forums, including their attachments. Posts are deleted **by age** (older than a configurable number of days) or, in marked collection topics, **by number** (each topic keeps only its newest X posts).

phpBB's built-in topic pruning removes whole topics after inactivity. This extension works on single posts and can be combined with topic pruning.

> **Beta (1.0.0-beta4):** Use it on a test board first, or with a backup + dry run.

> **⚠ Warning: deletion is hard and irreversible.**
> Deleted posts are not soft-deleted. Posts, attachments (including the files in `files/`), search index entries, reports and notifications are gone. Without a backup there is no way back.

### Requirements

- phpBB 3.3.3 or newer (3.3.x); PHP 7.2 or newer
- No Composer dependencies, no core changes

### Tested with

- phpBB 3.3.17 and 3.3.19
- Databases: MySQL/MariaDB, PostgreSQL, SQLite. MS SQL Server and Oracle are not tested.

### Installation

> **Download:** [Release v1.0.0-beta4](https://github.com/uflagmey/postprune/releases/tag/v1.0.0-beta4) → file `postprune-1.0.0-beta4.zip` (all versions: [Releases](https://github.com/uflagmey/postprune/releases)).
> Do **not** install from “Code → Download ZIP”: that is the source code with the wrong folder layout (`postprune-main/`), not an installation package.

1. Unpack the release ZIP and upload the folder `uflagmey/postprune/` to `ext/`, so that `ext/uflagmey/postprune/ext.php` exists.
2. In the ACP go to **Customise → Manage extensions** and enable “Post Prune”.
3. Nothing is switched on yet: every forum starts in mode **Off**.

Uninstall: disable the extension, then **Delete data**. This removes the extension's columns (including the topic marks) and config values completely.

### Updating from an earlier version

Applies to updating from 1.0.0-beta1 up to 1.0.0-beta4. From 1.0.0-beta3 on, phpBB 3.3.3 or newer is required (1.0.0-beta1 still ran on 3.3.0 and later); update an older board first.

1. **Backup** of database and `files/`.
2. Upload the new files, overwriting the folder `ext/uflagmey/postprune/`.
3. ACP → **Customise → Manage extensions**: **disable** “Post Prune” and **enable** it again right away. Do **not** choose “Delete data”: that removes all of the extension's settings.

Your forum settings are kept. New settings start so that nothing changes: scope “Whole forum”, deletion rule “By age”.

If step 3 is skipped (or only the cache is purged), nothing breaks: the extension keeps working like the previous version, only the new features do not appear. Once the cache has been purged, the forum form in the ACP shows a notice that the extension has to be disabled and enabled once.

After restoring a database backup always purge the cache (ACP → General → “Purge the cache”), see pitfall 9.

### Getting started / common pitfalls

1. **Finding the settings:** ACP → **Forums** (*Manage forums*). If the forum sits inside a category, first click the **category name**. Then click the **green cog** (“Edit”) of the actual forum and scroll down to the block **“Post pruning (individual posts)”**, right below “Forum prune settings”.
2. **⚠ Do not confuse** this with ACP → Forums → **“Prune forums”**. That is phpBB's own manual topic pruning: on submit it **immediately deletes whole topics**, without a dry run. It has nothing to do with this extension.
3. **The cron only runs on page requests in the forum**, not on page requests in the ACP. Each page request runs at most **one** due task, picked at random among all due tasks. If no task was due, phpBB does not check again for up to 60 seconds. So after switching it on, open a few forum pages (in a browser, as a normal visitor) and then look at the **admin log** (ACP → Maintenance).
4. **No log entry?** Check:
   - Is the mode really saved for the forum? The line “Saved for …” in the block shows the saved state (see pitfall 10).
   - Is the forum a normal forum (not a category, not a link)?
   - Is “Run periodic tasks from operating system cron” under *General → Server configuration → Server settings* set to **Disabled**? If it is enabled, phpBB does not start tasks on page requests, so you need a system cron job (see pitfall 11).
   - The dry run **always** writes one entry per completed cycle, even if no post is old enough (“No posts … would be deleted”). If that entry is missing, the cron has not run yet. (In 1.0.0-beta1 there was no entry with 0 candidates.)
5. **Marks only take effect with “Marked topics only”.** With the scope “Whole forum” all topics of the forum are pruned and the marks are ignored. The ACP points this out when topics in the forum are marked.
6. **Moving removes the mark.** A marked topic moved to another forum loses its mark, with an admin log entry. This also applies when a forum is deleted or converted and its content is moved. Mark the topic again in the target forum if needed.
7. **Merging into a marked topic:** the merged posts then belong to the marked topic and are pruned from then on. They keep their original date: if they are already old enough, they are deleted on the next run.
8. **Menu entry “Prune automatically” missing?** It is only shown to administrators with the permission *Can manage forums* (`a_forum`), and only once the update is complete (see “Updating from an earlier version”).
9. **Restored a database backup?** After restoring a database backup **always purge the cache** (ACP → General → “Purge the cache”), before disabling and enabling the extension. Otherwise phpBB keeps working with the cached values from before the restore: if the backup is from before the update, the following applies until the cache is purged: the board's pages and phpBB's other scheduled tasks keep working normally, but Post Prune deletes nothing and writes “Post Prune: database schema does not match the cache – please purge the cache” to the error log (ACP → Maintenance) once per cycle. Marking topics can end with an error 403. After the cache is purged, Post Prune deletes again from the next cycle on (default: one day after the error log entry). Remedy if it has already happened: purge the cache, disable and enable the extension (**not** “Delete data”). Settings and marks are kept.
10. **Nothing is saved until “Submit”** at the very end of the forum form. Changing values in the block and leaving the page saves nothing. The line **“Saved for “<forum>”: …”** at the top of the block always shows the state in the database, e.g. “Dry run · Marked topics only (1) · 30 days · keep first post”. If the fields below differ, those changes are not saved yet (this happens e.g. when phpBB shows the form again after an error).
11. **⚠ System cron only with a cron job for all tasks.** Do **not** enable “Run periodic tasks from operating system cron” (ACP → General → Server configuration → Server settings) unless a cron job on the server runs **all** tasks (`php bin/phpbbcli.php cron:run`, **without** a task name) every few minutes. With that option on, phpBB no longer starts **any** task on page requests. A cron job for Post Prune only (`cron:run uflagmey.postprune.cron.task.prune`) then brings the rest of the board to a halt: e-mails, notifications, clean-up tasks etc. stop running.

### Settings per forum

ACP → **Forums** → click **“Edit”** (green cog) at the forum, block **“Post pruning (individual posts)”** right below the prune settings. It is shown for normal forums only (not for categories or links). The cron ignores categories and link forums even if values are stored there.

At the top of the block: the note that only “Submit” saves, the line **“Saved for “<forum>”: …”** with the saved state and below it the button **“Calculate dry run now”** (see “Preview / running it by hand”).

| Setting | Default | Meaning |
|---|---|---|
| Mode | Off | **Off**: nothing happens. **Dry run**: only logs how many posts would be deleted. **Active**: posts are deleted. |
| Scope | Whole forum | **Whole forum**: all topics of the forum. **Marked topics only**: only topics marked in the topic view (see “Pruning only certain topics”). Below it the ACP lists the forum's marked topics. |
| Deletion rule | By age | **By age**: posts older than “Post age”. **By number**: each topic keeps only its newest “Number to keep” approved posts, older ones are deleted. “By number” requires “Marked topics only” (otherwise an error on save). |
| Post age | 30 days | “By age” only: posts older than this many days (1–65535). |
| Number to keep | 100 | “By number” only: this many of the newest approved posts stay per topic (1–65535). With “Keep first post” the first post stays **in addition**, it does not count. |
| Keep first post of each topic | Yes | Yes: the first post and therefore the topic stays. No: a topic disappears once all of its posts are old. With “Yes” an old first post stays until phpBB's topic pruning (if enabled) removes the topic. |
| Skip stickies and announcements | Yes | Posts in stickies, announcements and global announcements are never deleted. Applies with the scope “Whole forum” only, and there to marked topics as well (the mark is ignored there). Only with the scope “Marked topics only” is a marked topic pruned even if it is a sticky or an announcement. |
| Reduce user post counts | No | No (like phpBB's topic pruning): the author's post count stays. Yes: it is reduced for every deleted (approved) post. |

Unapproved and soft-deleted posts are deleted as well: by age like all others; by number they do not count towards the posts kept, but they are deleted when they are older than the oldest post kept. The board statistics (number of posts and topics) are corrected in every case.

### Pruning only certain topics (collection topics)

Often not a whole forum should be pruned, but only one **collection topic** in it. Example: the forum “Chit-chat” contains the topic “Joke collection”, where images are posted every day and fill `files/`, next to other topics such as “Introductions” that should stay. Goal: replies in the Joke collection older than 30 days are deleted, all other topics stay untouched.

1. **Backup** of database and `files/`.
2. **Mark the topic:** open the topic “Joke collection”. In the **Quick-mod tools** menu at the bottom choose “**Prune automatically: switch on**” and confirm. The entry is only visible to administrators with the permission *Can manage forums*. The mark is recorded in the admin log.
3. **Set up the forum:** ACP → Forums → click “Edit” at the forum “Chit-chat”, block “Post pruning”:
   - Scope: **Marked topics only**. The list “Marked topics” now shows the Joke collection.
   - Post age: **30** days.
   - Keep first post of each topic: **Yes**. The topic stays, only old replies go.
   - Mode: **Dry run**, then “Submit”.
4. **Check the dry run:** after the next cron run the admin log shows e.g. “Dry run: post pruning – 120 posts in 1 marked topic in forum “Chit-chat” would be deleted”. Administrators see the notice “Dry run active: …” in the topic. Nothing is deleted yet.
5. **Switch to Active** once the number looks right. From then on all visitors see the notice “Posts in this topic older than 30 days are deleted automatically. The first post is kept.” in the Joke collection. The other topics of the forum show no notice and are not pruned.

Removing the mark: same place, “Prune automatically: switch off”. If no topic is marked with “Marked topics only”, nothing is deleted, and the log says “no marked topics” once per cycle.

#### Collection topic: keep only the newest 100 posts

Instead of by age, a collection topic can also be kept at a fixed length. Example: the Joke collection should always keep only its **newest 100 posts**, however old they are, plus the first post.

1. **Backup**, mark the topic as above (steps 1 and 2).
2. **Set up the forum:** scope **Marked topics only**, deletion rule **By number**, number to keep **100**, keep first post of each topic **Yes**, mode **Dry run**, “Submit”. The line at the top of the block then shows “Saved for “Chit-chat”: Dry run · Marked topics only (1) · keep the newest 100 per topic · keep first post”.
3. **Check right away:** “Calculate dry run now” shows e.g. “→ 340 posts would be deleted (oldest: …, newest to be deleted: …)”. After the next cron run the admin log shows the same number (“… (keeping the newest 100 per topic)”).
4. **Switch to Active.** Visitors see “Only the newest 100 posts in this topic are kept, as well as the first post.” in the topic.

Only **approved** posts count (not the first post). A topic with **fewer than 100** approved posts loses nothing, not even unapproved or soft-deleted posts. From **exactly 100** on the limit applies: all approved posts stay, unapproved and soft-deleted posts older than the oldest of them are deleted. New posts move the limit: after 5 new replies the 5 oldest go on the next run. A large backlog is worked off in portions (at most 200 posts per run, see below).

### Preview / running it by hand

**Button “Calculate dry run now”** in the block (ACP → Forums → click “Edit” at the forum): it shows right away what a dry run with the **saved** settings would delete now, e.g. “→ 3,541 posts would be deleted (oldest: Sat Mar 02, 2019 2:05 pm, newest to be deleted: Sat Aug 15, 2026 9:12 am)”. It works in every mode, also “Off”, so before switching anything on.

- **Nothing is deleted** and nothing is logged; the cron schedule is not touched.
- It counts with the same rule as the cron, the dry run and the topic notice, without the per-run limit. The number matches the “Dry run …” entry in the log on the next cron run, as long as nothing changes in between.
- **Unsaved changes do not count.** “Submit” first, then the preview. The line “Saved for …” right above shows which values are used.
- **Without JavaScript** the button reloads the page; the result then appears below the button. Unsaved changes in the form are lost.
- Only for administrators with *Can manage forums* (`a_forum`), and only once the update is complete.

**Advanced:**

- `php bin/phpbbcli.php cron:run uflagmey.postprune.cron.task.prune` starts the task immediately and **ignores the daily interval**. Dry-run forums get another log entry, active forums **really** delete.
- Web cron URL `https://<board>/app.php/cron/uflagmey.postprune.cron.task.prune` (without `app.php` with URL rewriting): only runs when the task is due, so it **keeps to the interval**. Not suitable for forcing a run.

### Recommended procedure

1. **Backup first**: database and `files/`.
2. **Then dry run**: save the settings, check the number right away with “Calculate dry run now”, set the mode to “Dry run” and wait. The **admin log** then shows “Dry run: post pruning – N posts in forum “…” would be deleted”. Nothing is deleted.
3. **Then Active**, once the number looks right. Every deletion is recorded in the admin log (“Post pruning – N posts deleted in forum “…””).

### When does pruning run?

- The cron task `uflagmey.postprune.cron.task.prune` runs once a day by default (`uflagmey_pp_gc`, 86400 seconds).
- Each run deletes at most **200 posts** across all forums (`uflagmey_pp_batch`) to avoid timeouts on shared hosting. If there is more to do, the task continues on the next page requests until the backlog is gone. Dry-run forums do not count towards that limit and are logged once per cycle, even with 0 candidates.
- **phpBB's cron is triggered by page requests.** No visitors, no run.
- **⚠ System cron:** only enable “Run periodic tasks from operating system cron” if a server cron job runs **all** tasks, i.e. `php bin/phpbbcli.php cron:run` **without** a task name every few minutes (e.g. every 5). A cron job for Post Prune only brings the rest of the board to a halt (e-mails, notifications etc.), see pitfall 11.
- Running it right away and web cron: see “Preview / running it by hand”.

### Technical notes

Deletion goes exclusively through the phpBB core function `delete_posts()` (`includes/functions_admin.php`). It removes attachments including their files, search index entries, reports and notifications and resyncs topics and forums. The extension contains no DELETE statements of its own.

Whether a topic is subject to pruning is decided by one single rule (`query_builder::topic_where()`). The cron, the dry run, the preview and the topic notice share it, so the notice shows exactly the topics the cron prunes, and the preview the same number as the dry run.

The deletion rule “By number” needs no window functions: per topic the X-th newest approved post is read as the limit, everything before it is deleted (by time, with equal times by post ID).

---

License: [GPL-2.0-only](license.txt) · © 2026 Uwe Flagmeyer
