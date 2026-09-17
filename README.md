# HumHub Conversations

An independent module for **HumHub Community Edition 1.18.5**. It turns a Space discussion into a compact stream card and a separate, chat-like conversation view.

> Im Stream überblicken. In der Conversation schreiben.

It deliberately has no AI, automatic summaries, content screening or subtopics. Those are future product decisions, not hidden dependencies.

## What is implemented

- Space-only `Conversation` content type with the existing Space permission model.
- In enabled Spaces, Conversation is the primary standard-stream composer; it replaces the visible legacy **Beitrag** entry instead of adding a separate Space tab.
- Compact stream card: title, two-line manual summary, space/activity metadata, message count and a personal `X neu` count.
- Dedicated conversation page; messages are not shown as comments in the stream.
- Conversation messages are non-stream `ContentActiveRecord`s. This keeps HumHub's file manager, RichText and (where the core Like module is enabled) reactions usable per message.
- A sticky message composer with attachments.
- Private `last_seen_message_id` per user and conversation. The view scrolls to the first unread message and renders a divider.
- Sender-only message status: persisted messages are `✓✓` (published/available); blue `✓✓` means at least one voluntary read receipt exists. Other people never see those ticks.
- Sender-only `Gelesen von` modal, including the reading time.
- Account setting **Lesebestätigungen senden**. When off, a user is never added to receipts and cannot see named receipts for their own messages. Personal unread state still works.
- **V1.3:** Authors can edit only their own messages. Edited messages remain in place, keep files/reactions and show a discreet `bearbeitet` marker with its time. Deleting messages is intentionally not part of this version.
- **V1.4:** Every message offers **Reaktion**. It opens the complete Unicode emoji catalogue already bundled with HumHub; people can add or remove each emoji independently. The legacy single-purpose `Gefällt mir` control is not used in Conversations.
- **V1.5:** The emoji picker is grouped and searchable like familiar chat apps. Reactions sit compactly on the message bubble. `bearbeitet` is a small link to an immutable before/after timeline with each edit's time.

## Architecture

```text
Conversation (ContentActiveRecord, normal Stream entry)
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

## Installation (test instance first)

1. Back up the test database. Do not perform these steps on `community.selbstsein.events` during initial acceptance.
2. Clone this repository into the test installation's `protected/modules/conversations` directory.
3. From the HumHub installation root, enable the module:

   ```bash
   php protected/yii module/enable conversations
   php protected/yii migrate --include-module-migrations=1
   ```

4. Enable **Conversations** for a test Space in its module administration. Configure the module permission *Create conversations* for members as appropriate.
5. Clear HumHub caches if the deployment process requires it, then open that Space's ordinary Stream. Its composer offers **Conversation** instead of **Beitrag**.

Target test location: `testcommunity.selbstsein.events`. Production is explicitly out of scope for this repository bootstrap.

## Test plan for `testcommunity.selbstsein.events`

1. **Basic conversation:** In an enabled Space, confirm the normal Stream composer shows **Conversation** and no visible **Beitrag** entry. Member A creates a conversation with title and summary. Confirm a compact card appears in the ordinary Space stream and no comments appear below it.
2. **Permissions:** A non-member and a user without *Create conversations* cannot create or post. A Space member can view and post.
3. **Messages:** A and B exchange messages with an image/file. Confirm the attachment and Like reaction render on the message and no individual message appears in the stream.
4. **Unread:** B opens the card after A posted three messages. Confirm `● 3 neu`, scroll to the first new message and the divider. Reload and confirm the personal count is cleared; A's display is unaffected.
5. **Receipts on:** B leaves receipts enabled and opens A's message. Only A sees its ticks; A's blue ticks open B and the timestamp. B never sees ticks on A's message.
6. **Receipts off:** B disables the account option, receives new messages and opens them. Confirm unread state still advances, B is absent from `Gelesen von`, and B sees no named receipt list on their own messages.
7. **Regression:** Disable the module for the Space and verify no data is deleted. Re-enable it and verify existing conversations remain accessible.
8. **Editing:** Author A edits one of their messages. Confirm the edit dialog, the changed content and `bearbeitet` marker. Confirm B and Space managers do not receive an edit link and cannot call the edit route successfully.
9. **Emoji reactions:** Confirm every message has **Reaktion**, the picker can search the full catalogue and a selected emoji appears with its count. Select it again to remove the personal reaction.
10. **Edit history:** Edit a message twice. Confirm `bearbeitet` opens a timeline with the before/after content and time of both changes. Confirm all Conversation participants can read the history, but only the author can create revisions.

## Developer checks

```bash
php tests/run.php
HUMHUB_ROOT=/path/to/humhub php tests/compatibility.php
```

Run PHP syntax checks for every PHP file and the acceptance plan above before a production release. The module uses AGPL-3.0-only source headers.
