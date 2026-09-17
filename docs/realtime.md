# Echtzeit-Nachrichten für Conversations

Conversations funktioniert ohne diese Erweiterung weiterhin mit dem normalen
Abgleich. Der optionale Dienst meldet ausschließlich, dass sich ein Chat
geändert hat. Nachrichteninhalt, Rechteprüfung und Lesen-Status bleiben bei
HumHub.

## Sicherheitsmodell

- Der Browser erhält von HumHub ein signiertes, fünf Minuten gültiges Ticket
  für genau eine Unterhaltung und genau einen Benutzer. Es wird als
  WebSocket-Header übertragen, nicht als URL-Parameter.
- Der Relay-Dienst prüft dieses Ticket und die erlaubte Browser-Herkunft.
- HumHub veröffentlicht Ereignisse nur an `127.0.0.1`; jedes Ereignis ist mit
  einem gemeinsamen Secret signiert und maximal 60 Sekunden gültig.
- Das Relay übermittelt nur Unterhaltung- und Nachrichten-ID. Nach Eingang
  lädt der Browser die Seite regulär aus HumHub nach. Ein Relay kann daher
  keine Nachrichtendaten oder Berechtigungen umgehen.

## Lokale Entwicklung

1. `config/realtime.local.php.example` nach `config/realtime.local.php`
   kopieren, mit einer lokalen WebSocket-URL und einem mindestens 32 Zeichen
   langen Secret füllen.
2. Dieselben Werte beim Dienst setzen. Ein Beispiel für eine lokale Sitzung:

   ```sh
   CONVERSATIONS_REALTIME_SECRET='ein-langes-lokales-test-secret-mit-32-zeichen' \
   CONVERSATIONS_REALTIME_ALLOWED_ORIGINS='http://localhost:8765' \
   node realtime/server.mjs
   ```

3. `node realtime/test.mjs` prüft die Trennung der Chat-Kanäle. Der Browser
   muss dann an der Chatansicht ein `data-conversation-realtime-url` tragen.

## Ubuntu/Plesk: testcommunity.selbsstein.events

Die folgenden Schritte einmalig per SSH als Root ausführen. Vorher das Modul
wie üblich in die Testcommunity ausrollen und den Live-Betrieb nicht ändern.

1. Node.js 20 oder neuer über das von Plesk bereitgestellte Node.js-Paket oder
   die Ubuntu-Paketverwaltung installieren. Node.js 18 funktioniert technisch
   ebenfalls als Untergrenze. Die Version mit `node --version` prüfen.
2. Den Relay-Code in einen eigenen, nicht öffentlich erreichbaren Ordner
   kopieren, zum Beispiel `/opt/conversations-realtime`. Benötigt werden
   `server.mjs`, `package.json` und die beiden Beispieldateien. Es gibt keine
   npm-Abhängigkeiten.
3. Einen eingeschränkten Systembenutzer erstellen und ihm nur diesen Ordner
   sowie `/var/log/conversations-realtime` geben:

   ```sh
   useradd --system --home /opt/conversations-realtime --shell /usr/sbin/nologin conversations-realtime
   install -d -o conversations-realtime -g conversations-realtime /opt/conversations-realtime /var/log/conversations-realtime
   ```

4. `/etc/conversations-realtime.env` mit Berechtigung `600` anlegen. Das
   Secret einmal mit `openssl rand -hex 32` erzeugen und dort einsetzen:

   ```ini
   CONVERSATIONS_REALTIME_HOST=127.0.0.1
   CONVERSATIONS_REALTIME_PORT=8091
   CONVERSATIONS_REALTIME_PATH=/conversations-realtime
   CONVERSATIONS_REALTIME_ALLOWED_ORIGINS=https://testcommunity.selbsstein.events
   CONVERSATIONS_REALTIME_SECRET=HIER_DAS_GENERIERTE_SECRET
   ```

5. Die mitgelieferte `realtime/conversations-realtime.service.example` nach
   `/etc/systemd/system/conversations-realtime.service` kopieren. Danach den
   Dienst aktivieren und seinen lokalen Gesundheitscheck prüfen:

   ```sh
   systemctl daemon-reload
   systemctl enable --now conversations-realtime
   systemctl status conversations-realtime --no-pager
   curl http://127.0.0.1:8091/healthz
   ```

6. Die Vorlage `config/conversations-realtime.php.example` als
   `protected/config/conversations-realtime.php` der Testcommunity ablegen
   und exakt dasselbe Secret eintragen. Diese Datei liegt bewusst außerhalb
   des Modulordners und überlebt damit Updates über den GitHub-Modulmanager.
   Die beiden URLs müssen so lauten:

   ```php
   'publicUrl' => 'wss://testcommunity.selbsstein.events/conversations-realtime',
   'publishUrl' => 'http://127.0.0.1:8091/publish',
   ```

   Die Datei bleibt außerhalb von Git. Die lokale Vorlage im Modul ist nur
   für Entwicklung vorgesehen und wird bei dieser Installation nicht benutzt.
7. In Plesk für die Domain unter **Apache & nginx-Einstellungen** eine
   zusätzliche nginx-Direktive eintragen und speichern:

   ```nginx
   location /conversations-realtime {
       proxy_pass http://127.0.0.1:8091;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_read_timeout 3600;
       proxy_send_timeout 3600;
   }
   ```

   Die normale HumHub-Konfiguration über Apache bleibt unverändert. nginx
   terminiert HTTPS und leitet nur diesen Pfad lokal an Node weiter. Port 8091
   darf deshalb **nicht** in der Firewall geöffnet werden.
8. HumHub-/PHP-Cache leeren und die Testcommunity neu laden. In den Browser-
   Entwicklerwerkzeugen muss für `/conversations-realtime` eine erfolgreiche
   WebSocket-Verbindung erscheinen. Mit zwei Testkonten denselben Chat öffnen:
   Die Nachricht des ersten Kontos muss beim zweiten unmittelbar erscheinen.
   Anschließend den Dienst einmal stoppen: Nach höchstens 12 Sekunden muss
   die vorhandene Polling-Rückfallebene weiter aktualisieren.

Bei einem späteren Produktions-Rollout werden dieselben Schritte mit der
Produktions-Domain, einem neuen Secret und einem getrennten Dienst/Port
wiederholt. Test- und Produktivumgebung teilen niemals ein Secret.
