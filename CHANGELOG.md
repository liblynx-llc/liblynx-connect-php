# Changelog

All notable changes to `liblynx-connect-php` will be documented in this file,
following the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased]

Further cleanup of the alpha release, addressing issue #3 by adding 
exception classes and a marker interface. Also addresses issue #1 by
extracting a separate `IdentificationRequest` from `Identification`

This release is not backwards compatible with 0.1.0

### Added

- `IdentificationRequest` added to better model the initial request
- Additional test coverage
- Exception classes for all exceptions thrown

### Removed

- `Identification::doWayfRedirect` removed as too simplistic for real-world use. Redirection example added to README.md
- `Identification::getRequestJSON` moved to new `IdentificationRequest`
- `Identification::fromSuperglobals`  moved to new `IdentificationRequest::fromArray`

## 0.1.0 - 2018-02-15

- First open source release following cleanup of an older internal version
