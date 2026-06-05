---
title: Authentication
description: HTTP Basic authentication for DAV clients.
---

The server uses stateless HTTP Basic authentication backed by `DavCredential` records.

Credentials are per client. A credential stores:

- owner ID
- display name
- username
- hashed secret
- last used timestamp

Use HTTPS in production. The plaintext secret is only available when it is generated and should be shown to the user once.

Digest authentication is not supported because it weakens the package’s credential storage model.
