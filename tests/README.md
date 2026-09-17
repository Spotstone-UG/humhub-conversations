# Tests

`php tests/run.php` validates the repository layout and privacy-critical invariants without a HumHub installation.

`HUMHUB_ROOT=/path/to/humhub php tests/compatibility.php` checks that the HumHub 1.18.5 extension points used by this module are present. It does not write to that installation.

Functional acceptance is intentionally performed in the dedicated test instance; see the repository README for the scenario list.

