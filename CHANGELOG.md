# Changelog

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
