# Globale Mail-Bereinigung – tlehmann630@gmail.com

## Gescannte Dateien

Im Rahmen der globalen Mail-Bereinigung wurden die folgenden Dateien auf das Vorkommen
der Adresse `tlehmann630@gmail.com` untersucht und bei Bedarf geändert:

| Datei | Gefunden | Geändert |
|-------|----------|----------|
| `sql/dbs15253086.sql` | Nein | – |
| `pages/admin/bulk_invite.php` | Nein | – |
| `api/get_mail_template.php` | Nein | – |
| `.env` | Nein | – |

Die Adresse `tlehmann630@gmail.com` war in keiner der oben genannten Dateien vorhanden.
Es waren daher keine Ersetzungen durch `vorstand@business-consulting.de` erforderlich.

## Hinweis für Testzwecke

Für alle Testzwecke darf ab sofort **ausschließlich** eine der folgenden Adressen
verwendet werden:

- **Offizielle Vorstandsmail:** `vorstand@business-consulting.de`
- **SMTP-Absenderadresse aus `.env`:** der in `SMTP_USER` hinterlegte Wert
  (aktuell `noreply@intra.business-consulting.de`)

Private oder persönliche E-Mail-Adressen (z. B. `@gmail.com`) dürfen **nicht** mehr
als Test- oder Absenderadressen im Projekt eingetragen werden.

## Datenbankschema-Änderungen

Die folgenden Schema-Änderungen wurden direkt in den `CREATE TABLE`-Definitionen
in `sql/dbs15161271.sql` vorgenommen:

- **`inventory_items`**: Spalte `loaned_count INT DEFAULT 0` hinzugefügt
  (für Live-Datenbanken entspricht das:
  `ALTER TABLE inventory_items ADD COLUMN loaned_count INT DEFAULT 0;`)

- **`rentals`**: Spalte `status` um den Wert `'overdue'` erweitert –
  neues ENUM: `('active', 'returned', 'defective', 'pending_confirmation', 'overdue')`
  (für Live-Datenbanken entspricht das:
  `ALTER TABLE rentals MODIFY COLUMN status ENUM('active', 'returned', 'defective', 'pending_confirmation', 'overdue') NOT NULL DEFAULT 'active';`)
