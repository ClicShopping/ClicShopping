# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.1.0 - 2025-06-10

### Added
- Comprehensive RFC 5322 email validation:
  - Added new `Rfc5322Validator` class for strict RFC compliance
  - Added support for quoted strings in local part
  - Added proper domain literal (IP address) validation
  - Added comment extraction and validation
  - Added length validation for local part and domain components
  - Added new error code `FAIL_RFC5322` for RFC validation failures
- Enhanced `EmailAddress` class:
  - Added `getLocalPart()` method
  - Added `getComments()` method
  - Improved comment parsing with nested comment support
  - Better handling of quoted strings and escaped characters

## 2.0.0 - 2025-05-29

### Added
- Strict type declarations for all properties and method signatures.
- Expanded unit tests to cover edge cases, type safety, and invalid input handling.
- Added support for custom validators through the new `registerValidator()` method.
- Added new error code `FAIL_CUSTOM` for custom validation failures.

### Changed
- **Updated the minimum PHP version requirement to PHP 7.4**.
- Refactored internal logic for better null safety and array filtering.
- Refactored provider validators to handle null/invalid domains gracefully and consistently return true for invalid emails.
- Improved type safety with PHP 7.4+ typed properties and enhanced PHPDoc array type hints.
- Updated `.editorconfig` to be more explicit for certain file types, including PSR-12 for PHP.

### Fixed
- Issue #7: Improved email address parsing to properly handle RFC822 compliant addresses:
  - Multiple @ symbols in quoted strings
  - Domain literals (IP addresses in square brackets)
  - Comments in email addresses
  - Better handling of edge cases

## [1.1.4] - 2024-04-09

### Changed
- CHANGELOG format

### Fixed
- Issue #5: Static variables prevent running validation with different configurations
- Issue #6: `googlemail.com` is now recognized as a Gmail address
- Issue #6: `.` are now removed when sanitizing Gmail addresses (to get to the root email address)

## [1.1.3] - 2022-10-12

### Fixed

- Handled potential for null being returned when validating a banned domain name

## [1.1.1] - 2022-10-11

### Changed 

- Banned domain check to use pattern matching for more robust validation including subdomains

## [1.1.1] - 2022-02-22

### Fixed

- When getting an email address' username, if there was none, return an empty string instead of NULL

## [1.1.0] - 2022-02-02

### Added 

- Support for identifying and working with Gmail addresses using the "plus trick" to create unique addresses

## [1.0.2] - 2022-01-24

### Fixed

- Issue #2: Error state not clearing between validations

## [1.0.1] - 2021-09-20

### Added

- Pull Request #1: Added EmailValidator::getErrorCode()

## [1.0.0] - 2020-08-02

- Initial release
