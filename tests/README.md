# Tests

`php tests/message-menu-runtime.php /path/to/testcommunity.selbstsein.events`
renders the real chat view for an ordinary participant without chat-editing rights.
It checks own versus foreign messages, closed chats and deleted messages. All test
database changes are rolled back. A second argument can select a staged view.

`node tests/live-richtext.test.cjs` checks widget initialization for live delivery,
including initialization after DOM insertion, rendering before scroll placement,
scrolled-away readers and repeated delivery without resetting existing widgets.

`php tests/unicode-storage-runtime.php /path/to/humhub` checks native Unicode emoji,
skin tones, joined emoji, umlauts and existing emoji shortcodes through save, edit
and both history fields. It copies the installed column definitions to
connection-local temporary tables and never modifies persistent chat records.
Run after applying the module migrations; on the old utf8mb3 schema it fails.

`php tests/run.php` validates the repository layout and privacy-critical invariants without a HumHub installation.

`HUMHUB_ROOT=/path/to/humhub php tests/compatibility.php` checks that the HumHub 1.18.5 extension points used by this module are present. It does not write to that installation.

Functional acceptance is intentionally performed in the dedicated test instance; see the repository README for the scenario list.

`php tests/reaction-target-runtime.php /var/www/vhosts/sexpositiv.events/testcommunity.selbstsein.events` checks comment versus source-post reaction targeting, add/remove persistence, unchanged parent reactions and guest rejection with an ordinary member. It requires an existing readable comment and rolls database changes back. It refuses other installation paths.


`php tests/archive-runtime.php /var/www/vhosts/sexpositiv.events/testcommunity.selbstsein.events write-test` exercises native archive/restore, overview rendering, member rejection and guest permissions using existing test accounts and a database transaction that is rolled back. It is restricted to that dedicated test installation and requires a chat plus an administrator and an ordinary member who can read it.
