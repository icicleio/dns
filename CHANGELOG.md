# Changelog

### v0.6.0

- Changes
    - All interface names have been changed to remove the `Interface` suffix. Since most classes in this package would now conflict with the interface names, the classes are prefixed with either `Basic` or `Default` (e.g.: `Icicle\Dns\Executor\BasicExecutor` and `Icicle\Dns\Connector\DefaultConnector`).

---

### v0.5.2

- New Features
    - Added the following functions in the `Icicle\Dns` namespace:
        - `execute()` - Executes a DNS query.
        - `executor()` - Accesses and sets the global executor used by `execute()`.
        - `resolve()` - Resolves the IP(s) for a given domain.
        - `resolver()` - Accesses and sets the global resolver used by `resolve()`.
        - `connect()` - Connects to a remote domain on a given port.
        - `connect()` - Accesses and sets the global connector used by `connect()`.
        
---

### v0.5.1

- Updated dependencies to allow `icicleio/socket` ^0.3.

---

### v0.5.0

- Changes
    - Switched to using an array of options on each interface instead of separate arguments. Will add flexibility for differing implementations to have more options.

---

### v0.4.0

- Changes
    - Changed `Icicle\Dns\Resolver\Resolver::resolve()` to resolve an empty array if no address is found instead of rejecting.
    - Updated `Icicle\Dns\Connector\Connector::connect()` to catch exceptions created by this component, then throw an exception as specified on `Icicle\Socket\Client\ConnectorInterface::connect()` with the caught exception as previous.

---

### v0.3.0

- Changes
    - Updated dependencies based on changes made in Icicle v0.7.0.
    - The following methods now return a `Generator` that can be used to create a `Coroutine` instead of returning a promise. Wrap the function call with `new Coroutine()` to create a promise or use with `yield` in a coroutine. *This change was made to support `yield from` in PHP 7.*
        - `Icicle\Dns\Connector\ConnectorInterface::connect()`
        - `Icicle\Dns\Executor\ExecutorInterface::execute()`
        - `Icicle\Dns\Resolver\ResolverInterface::resolve()`

---

### v0.2.0

- Changes
    - Update for API changes made in Icicle v0.5.0.

---

### v0.1.2

- Changes
    - Reorganized tests and made updates to test loading based on changes made in Icicle v0.4.0.

---

### v0.1.1

- Bug Fixes
    - Fixed bug in Executor where connection was not being closed.

---

### v0.1.0

- Initial Release.
