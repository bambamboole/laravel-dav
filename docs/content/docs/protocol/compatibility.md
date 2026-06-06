---
title: Compatibility
description: Current compatibility checks.
---

The test suite includes a CalDAV server compatibility harness that boots the DAV server as a real HTTP process and runs `caldav-server-tester` against it.

Install the tester where the suite runs:

```bash
uv tool install --with vobject caldav-server-tester
```

If the binary is not on `PATH`, set:

```bash
CALDAV_SERVER_TESTER_BIN=/path/to/caldav-server-tester
```

The compatibility test records current support status feature by feature. As server support improves, update the matching expectations so the diff documents the protocol progress.

Calendar sharing and calendar proxy delegation are covered by feature tests. Keep those expectations aligned with the compatibility harness as client-facing protocol support changes.
