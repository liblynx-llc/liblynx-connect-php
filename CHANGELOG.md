# Changelog

All notable changes to `liblynx-connect-php` will be documented in this file,
following the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## 1.0.1 - 2026-10-29

Updated to use psr/log v3

## 1.0.0 - 2026-10-29

Migrated to php 8.2+

## 0.4.0 - 2026-10-28

As well as adding support for LibLynx simultaneous usage control, this adds a number of features
that make it easier to support logging of API calls and responses with a middleware.

### Added
- Client supports including an additional middleware in constructor, this can be useful for API logging
- support for usage slot management to manage simultaneous user limits
- resources support a `getJson` method to get a pretty printed JSON representation for diagnostics

### Changed
- URL in identification request initialised as fully qualified

## 0.3.0 - 2018-11-10

### Fixed
- added missing implementation of `TokenPersistenceInterface::hasToken`

## 0.2.0-alpha - 2018-02-15

Refactoring of initial 0.1.0-alpha release to reduce complexity. This is NOT backwards
compatible with the previous release.

### Fixed

- Fixed issue #1 with introduction of `IdentificationRequest`
- Fixed issue #3 with introduction of exception classes and the `LibLynxException` marker interface

### Added

- `IdentificationRequest` added to better model the initial request
- Additional test coverage
- Exception classes for all exceptions thrown

### Changed

- Failures managed by the API, e.g. returning 400 responses will result in
  an `APIException` being thrown

### Removed

- `Identification::doWayfRedirect` removed as too simplistic for real-world use. Redirection example added to README.md
- `Identification::getRequestJSON` moved to new `IdentificationRequest`
- `Identification::fromSuperglobals`  moved to new `IdentificationRequest::fromArray`

## 0.1.0-alpha - 2018-02-15

- First open source release following cleanup of an older internal version
