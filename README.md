# HumHub Chats

Ein eigenständiges Modul für **HumHub Community Edition 1.18.5**. Es ergänzt Space-Diskussionen um kompakte Stream-Karten und eine eigene, chatartige Ansicht.

> Im Stream überblicken. Im Chat schreiben.

It deliberately has no AI, automatic summaries or content screening. AI support remains a future product decision, not a hidden dependency.

## What is implemented

- Space-only `Conversation` content type with the existing Space permission model; sichtbar heißt er überall **Chat**.
- In enabled Spaces, the familiar **Beitrag** composer stays first. **Chat** is an additional composer entry; it does not replace posts and does not add a separate Space tab.
- Compact stream card: title, two-line manual summary, space/activity metadata, message count and a personal `X neu` count.
- Eigene Chat-Seite; Nachrichten erscheinen nicht als Kommentare im Stream.
- Conversation messages are non-stream `ContentActiveRecord`s. This keeps HumHub's file manager, RichText and (where the core Like module is enabled) reactions usable per message.
- A sticky message composer with attachments.
- Private `last_seen_message_id` per user and conversation. The view scrolls to the first unread message and renders a divider.
- Sender-only message status: persisted messages are `✓✓` (published/available); blue `✓✓` means at least one voluntary read receipt exists. Other people never see those ticks.
- Sender-only `Gelesen von` modal, including the reading time.
- Account setting **Lesebestätigungen senden**. When off, a user is never added to receipts and cannot see named receipts for their own messages. Personal unread state still works.
- **V1.3:** Authors can edit only their own messages. Edited messages remain in place, keep files/reactions and show a discreet `bearbeitet` marker with its time. Deleting messages is intentionally not part of this version.
- **V1.4:** Every message offers **Reaktion**. It opens the complete Unicode emoji catalogue already bundled with HumHub; people can add or remove each emoji independently. The legacy single-purpose `Gefällt mir` control is not used in Conversations.
- **V1.5:** The emoji picker is grouped and searchable like familiar chat apps. Reactions sit compactly on the message bubble. `bearbeitet` is a small link to an immutable before/after timeline with each edit's time.
- **V1.6:** Normal Space posts replace the native binary **Gefällt mir** link with **Reaktion** and the same full emoji palette, without changing any HumHub core file.
- **V1.7:** Every message has a compact `…` menu. Any member who may write in the Space can start a linked **Unterthema** from a message. It becomes a separate chat and is represented only by a clear card at the originating message — never as a duplicate Stream entry. Authors may also delete their own message after confirmation; text and exclusively attached files are removed for all participants and a stable “Diese Nachricht wurde gelöscht.” marker preserves chronology.
- **V1.8:** Die Space-Übersicht ist eine klar gegliederte Karten-/Tabellenansicht mit Status, Aktivität und gespeichertem Ergebnis. Berechtigte Space-Mitglieder können einen Chat mit einem menschlich geschriebenen **Gesprächsergebnis** beenden; er bleibt lesbar und kann wieder geöffnet werden.
- **V1.9:** Der Hauptmenüpunkt **Chats** zeigt alle für die jeweilige Person lesbaren Chats, nach Space und Unterthemen gegliedert. Persönliche `X neu`-Zähler erscheinen in der Übersicht und als roter Zähler im Hauptmenü.
- **V1.10:** Beim Beenden wird festgehalten, **wer** den Chat beendet hat. Das Ergebnis erhält eine rein optische Konsensrunde für alle Teilnehmenden: Zustimmung durch alle bestätigt es sofort; nach 14 Tagen ohne Widerspruch gilt es als „keine schwerwiegenden Widersprüche“. Ein Widerspruch ermöglicht einen versionierten Alternativvorschlag mit einer neuen Konsensrunde.

## Architecture

```text
Conversation (ContentActiveRecord, normal Stream entry)
  ├── parent_conversation_id / origin_message_id  linked Unterthema (no Stream entry)
  ├── closed_at / outcome                          lifecycle and human outcome
  ├── closed_by                                    person who ended the chat
  ├── conversation_consensus_proposal              result versions / alternatives
  │     └── conversation_consensus_response        consent or objection per participant
  ├── conversation_user_state       private last_seen state
  └── ConversationMessage (ContentActiveRecord, stream_channel = null)
        └── conversation_read_receipt   voluntary, sender-visible only

conversation_user_setting
  └── read_receipts_enabled
```

Both the conversation and every message use HumHub content records. The conversation therefore inherits Space/container visibility, author metadata, content lifecycle and search integration. A message receives no stream channel, so it can keep native files and reactions without becoming a separate feed card. No HumHub core file is changed.

## Privacy logic

`conversation_user_state` is private operational state: it drives `X neu`, the divider and the first-unread scroll. It is updated whether or not someone sends read receipts and is never exposed in the UI.

`conversation_read_receipt` is opt-in visibility. It is created only when the reader enabled **Lesebestätigungen senden**, is never displayed on someone else's message, and is queried only by its sender. If the sender disabled the same setting, the modal and named receipt information are withheld from that sender as well. Disabling receipts later also filters old receipts from the read list.

The single check is an ephemeral client-side "saved" state while submitting. A server-rendered message is already persisted and visible in the conversation, so it correctly displays `✓✓`.

`conversation_consensus_proposal` is deliberately a transparent display workflow, not a voting or enforcement system. Teilnehmende sind ausschließlich die Personen, die im jeweiligen Chat eine Nachricht geschrieben haben. Reaktionen, bloßes Mitlesen und das Erstellen ohne Wortmeldung zählen ausdrücklich nicht. Eine neue Alternativfassung bewahrt die vorherige Fassung in der Datenbank und startet die 14-Tage-Frist neu.

## Installation (test instance first)

1. Back up the test database. Do not perform these steps on `community.selbstsein.events` during initial acceptance.
2. Clone this repository into the test installation's `protected/modules/conversations` directory.
3. From the HumHub installation root, enable the module:

   ```bash
   php protected/yii module/enable conversations
   php protected/yii migrate --include-module-migrations=1
   ```

4. Enable **Chats** for a test Space in its module administration. Configure the module permission *Create conversations* for members as appropriate.
5. Clear HumHub caches if the deployment process requires it, then open that Space's ordinary Stream. Its composer offers **Beitrag** and **Chat**, with **Beitrag** first.

Target test location: `testcommunity.selbstsein.events`. Production is explicitly out of scope for this repository bootstrap.

## Test plan for `testcommunity.selbstsein.events`

1. **Grundfunktion:** In einem aktivierten Space bleibt **Beitrag** der erste Eintrag im Stream-Composer; **Chat** erscheint zusätzlich. Person A erstellt einen Chat mit Thema und Kurzfassung. Prüfe die kompakte Karte im Stream ohne Kommentarbereich.
2. **Permissions:** A non-member and a user without *Create conversations* cannot create or post. A Space member can view and post.
3. **Messages:** A and B exchange messages with an image/file. Confirm the attachment and Like reaction render on the message and no individual message appears in the stream.
4. **Unread:** B opens the card after A posted three messages. Confirm `● 3 neu`, scroll to the first new message and the divider. Reload and confirm the personal count is cleared; A's display is unaffected.
5. **Receipts on:** B leaves receipts enabled and opens A's message. Only A sees its ticks; A's blue ticks open B and the timestamp. B never sees ticks on A's message.
6. **Receipts off:** B disables the account option, receives new messages and opens them. Confirm unread state still advances, B is absent from `Gelesen von`, and B sees no named receipt list on their own messages.
7. **Regression:** Disable the module for the Space and verify no data is deleted. Re-enable it and verify existing conversations remain accessible.
8. **Editing:** Author A edits one of their messages. Confirm the edit dialog, the changed content and `bearbeitet` marker. Confirm B and Space managers do not receive an edit link and cannot call the edit route successfully.
9. **Emoji reactions:** Confirm every message has **Reaktion**, the picker can search the full catalogue and a selected emoji appears with its count. Select it again to remove the personal reaction.
10. **Edit history:** Edit a message twice. Confirm `bearbeitet` opens a timeline with the before/after content and time of both changes. Confirm all Chat-Teilnehmenden can read the history, but only the author can create revisions.
11. **Post reactions:** On a normal Space post, confirm **Reaktion** replaces **Gefällt mir**. Open the picker, search an emoji and confirm the selected emoji is counted; select the same chip again to remove the personal reaction.

12. **Unterthemen:** Member B starts an Unterthema from A’s message. Confirm the card at that precise location, the separate chat, inherited Space permissions and that no second unrelated Stream card appears.
13. **Löschen:** Author A deletes a message with an attachment. Confirm the confirmation dialog, that every participant sees only the deletion marker, and that the former attachment is unavailable.
14. **Beenden:** End a conversation with an outcome. Confirm it is read-only, opens with the stored outcome, remains readable and can be reopened by a permitted member.
15. **Konsens:** Beende einen Chat und prüfe „Beendet von …“ sowie das Ergebnis. Prüfe Zustimmung und Widerspruch nur für Teilnehmende. Erstelle nach einem Widerspruch einen Alternativvorschlag; bestätige ihn mit allen Teilnehmenden. Prüfe außerdem mit einem zurückdatierten Vorschlag den 14-Tage-Status „keine schwerwiegenden Widersprüche“.
16. **Globale Übersicht:** Prüfe den Hauptmenüpunkt **Chats** mit rotem persönlichem Zähler. Er darf ausschließlich Chats aus öffentlichen oder für die Person lesbaren Spaces zeigen und muss Unterthemen dem Hauptchat zuordnen.

## Developer checks

```bash
php tests/run.php
HUMHUB_ROOT=/path/to/humhub php tests/compatibility.php
```

Run PHP syntax checks for every PHP file and the acceptance plan above before a production release. The module uses AGPL-3.0-only source headers.

GitHub Actions also runs PHP linting and the repository-level invariants for PHP 8.2 and 8.3 on every pull request and every push to `main`.
