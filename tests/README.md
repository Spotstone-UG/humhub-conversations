# Tests

`php tests/run.php` validates the repository layout and privacy-critical invariants without a HumHub installation.

`HUMHUB_ROOT=/path/to/humhub php tests/compatibility.php` checks that the HumHub 1.18.5 extension points used by this module are present. It does not write to that installation.

Functional acceptance is intentionally performed in the dedicated test instance; see the repository README for the scenario list.


`php tests/archive-runtime.php /var/www/vhosts/sexpositiv.events/testcommunity.selbstsein.events write-test` exercises native archive/restore, overview rendering, member rejection and guest permissions using existing test accounts and a database transaction that is rolled back. It is restricted to that dedicated test installation and requires a chat plus an administrator and an ordinary member who can read it.
