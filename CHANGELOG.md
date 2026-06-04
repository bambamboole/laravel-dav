# Changelog

All notable changes to `bambamboole/laravel-dav` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-04

Initial release.

### Added

- CalDAV server supporting events (`VEVENT`), todos (`VTODO`), and journals (`VJOURNAL`) ([RFC 4791](https://datatracker.ietf.org/doc/html/rfc4791)).
- CardDAV server supporting contacts (`VCARD` 3.0) with rich, typed parsing ([RFC 6352](https://datatracker.ietf.org/doc/html/rfc6352)).
- WebDAV collection synchronization via sync tokens ([RFC 6578](https://datatracker.ietf.org/doc/html/rfc6578)).
- Service discovery through `/.well-known/caldav` and `/.well-known/carddav` redirects ([RFC 6764](https://datatracker.ietf.org/doc/html/rfc6764)).
- HTTP Basic authentication backed by hashed per-user credentials.
- DAV ACL with per-user principals and principal property search.
- Dead-property storage (`PropertyStorage`) and ICS/VCF collection export.
- Recurrence-aware `calendar-query` time-range matching for events, todos, and journals.
- Owner-agnostic design via the `DavOwner` contract, with swappable Eloquent models.
- Support for Laravel 12 and 13 on PHP 8.3 and 8.4 (CI matrix covers every combination).
- Typed DTOs carrying the verbatim `raw` payload alongside strongly-typed parsed fields.
- `DavCollectionChanged` event fired on collection mutations.
- `caldav-server-tester` compatibility harness with an asserted status-quo snapshot.

### Known limitations

- **RFC 6638 scheduling** (inbox/outbox, auto-schedule, free/busy, iMIP invitations) is not implemented.
- **Calendar sharing and proxy delegation** are not implemented.
- **vCard 4.0 / jCard** are not supported (vCard 3.0 only).
- **Server-side expansion of recurring `VTODO`s** (`<C:expand>`) is not implemented; clients expand recurrences themselves.
- The `caldav-server-tester` `save-load.event.timezone` check reports `broken`. Stored iCalendar (including `VTIMEZONE`) is persisted and returned verbatim, so standards-compliant clients round-trip correctly; the deviation is tracked for investigation.

[Unreleased]: https://github.com/bambamboole/laravel-dav/compare/0.1.0...HEAD
[0.1.0]: https://github.com/bambamboole/laravel-dav/releases/tag/0.1.0
