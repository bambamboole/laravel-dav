# Changelog

All notable changes to `bambamboole/laravel-dav` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- CalDAV scheduling ([RFC 6638](https://datatracker.ietf.org/doc/html/rfc6638)):
  the calendar backend implements `SchedulingSupport` (backed by a new
  `dav_scheduling_objects` table), the `Sabre\CalDAV\Schedule\Plugin` is registered,
  and principals advertise `calendar-user-address-set` plus scheduling inbox/outbox
  URLs. Auto-scheduling delivers iTip `REQUEST`/`REPLY`/`CANCEL` to local attendees'
  inboxes, and free/busy queries work.
- iMIP email delivery ([RFC 6047](https://datatracker.ietf.org/doc/html/rfc6047)) for
  attendees that are not local principals, sent through Laravel Mail (transport
  configurable via `dav.scheduling.mailer`, `from` via `DAV_SCHEDULING_FROM`,
  defaulting to `MAIL_FROM_ADDRESS`) using the overridable `SchedulingMessageMail`
  mailable. The whole scheduling stack can be toggled with `dav.scheduling.enabled`
  (on by default). `schedule-tag` remains a follow-up.
- Calendar availability ([RFC 7953](https://datatracker.ietf.org/doc/html/rfc7953)):
  the `calendar-availability` property can be stored on a principal's scheduling
  inbox to publish working hours, and free/busy responses now mark time outside
  those windows as `BUSY-UNAVAILABLE`.

### Changed

- `calendar-query` REPORTs now narrow candidates in SQL using the denormalised
  `component_type`/`starts_at`/`ends_at` columns before parsing, instead of
  loading and parsing every object in the calendar. Recurring objects (a new
  `recurs` column) are always kept as candidates so recurrence expansion stays
  correct.

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

[Unreleased]: https://github.com/bambamboole/laravel-dav/compare/0.1.0...HEAD
[0.1.0]: https://github.com/bambamboole/laravel-dav/releases/tag/0.1.0
