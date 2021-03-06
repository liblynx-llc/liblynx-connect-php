# Changelog

All notable changes to `liblynx-connect-php` will be documented in this file,
following the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

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
