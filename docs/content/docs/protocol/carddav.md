---
title: CardDAV
description: Contact protocol support.
---

CardDAV support includes address book collections and vCard resources.

Cards are stored exactly as uploaded. The server advertises both:

- vCard 3.0
- vCard 4.0

Clients can negotiate `text/vcard; version=4.0`. The default response favors vCard 3.0 for compatibility with common clients.

jCard can be negotiated through sabre’s converter, though most clients use `text/vcard`.
