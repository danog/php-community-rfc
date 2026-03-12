# php-community: a faster-moving, community-driven PHP.

## Table of Contents

- [Introduction](#introduction)
  - [What problem is this RFC solving](#what-problem-is-this-rfc-solving)
  - [The community RFC process](#the-community-rfc-process)
  - [The php-community release process](#the-php-community-release-process)
  - [Feature extensions](#feature-extensions)
  - [API](#api)

## Introduction

With this proposal, the entire PHP community gets immediate access to experimental features through an official `php-community` version of PHP, versioned in a rolling manner (i.e. `php-community 2026.03.01`), and available on `php.net` along normal PHP releases.  

Experimental features distributed through `php-community` can be:

- Major new language features like True Async
- New stdlib functions, classes
- Deprecations for a more performant PHP
- Experimental optimizers (i.e. JIT v3)

Experimental features are offered as special PHP *feature extensions* **built into PHP by default**.  

These special *feature extensions* are **versioned** with semver and **disabled by default**, and can be easily enabled with a single `PhpFeature::get($name, $version)->enable()` call (i.e. automatically invoked by the Composer autoloader).  

*Feature extensions* can rely or conflict with specific versions of other feature extensions.  

*Feature extensions* **cannot** be enabled using php.ini, to distinguish them from normal extensions.  

For the first time, **official** binaries and packages will be provided for all major Linux distros for `php-community` releases on `php.net` (and the usual binary builds for Mac OS and Windows will be provided as well).

This makes it significantly easier to get real feedback on features from the **entire** PHP community.  

The main objective of this RFC is to allow the community to "preview" future language changes in an easily accessible manner: while there have been improvements lately with PIE, experimental language features distributed using normal extensions are still **not** easily accessible to the entire PHP community; every extra installation step is a barrier to entry, and often simply cannot be installed at all in the most popular PHP execution environment: **shared hosts**.  

### What problem is this RFC solving

The main problem this RFC addresses: often, proposals for important language features get either rejected, or forgotten about due to (often entirely reasonable!) doubts and uncertainty about adoption and impact on the PHP community as a whole.  

The RFC discussion process often offers *some* insight on the possible impact of a feature on the community as a whole, but often these are just **assumptions** not based on real data.  

Distributing major (and minor) language changes in an easily accessible manner, using an official PHP distro, allows:

- Immediate, large-scale community adoption if there is real interest in the feature.  
  Even deprecations can be interesting to users if they are offered a `performance` extension that disables some deprecated language features in exchange for significantly improved performance, and projects can simply `composer require ext-performance` to immediately speed up their project.  
- RFC authors and internals members get real-world feedback, from real users: extensions like Swoole have proven this feedback and adoption model works on a small scale, `php-community` brings it to the entire PHP community.  
- Quick iteration on features, allowing breaking changes across minor php-community releases thanks to semantic versioning of the features themselves.
  Multiple versions of the same feature may be offered at the same time in a single `php-community` release, but only one can be enabled at runtime, to further reduce the impact of breaking changes.    

### The community RFC process

Community RFCs will have a significantly leaner and relaxed community RFC process, designed for both speed and quality.  

This process is intentionally lean and non-specific to allow for quicker iteration, and is heavily inspired by [Golang's lean proposal process](https://go.dev/s/proposal-process).  

Please note: the intent of this leaner process is **not** to bypass the existing RFC process, but to **enhance** it, by providing precious, real-world adoption feedback from the PHP community, which can then be provided when and if the community RFC is converted into a normal RFC.  

- Community RFCs are proposed as simple GitHub issues to a separate php/php-community-rfcs repository, maintained and moderated by all current internals members.  
  It can be a simple proposal, without a full design document.
  As discussion proceeds, a design document (which will be eventually used for the full, non-community RFC) can be provided as a pull request, committed to the same repo once the RFC is accepted.  

  Once a community RFC is accepted, both breaking and non-breaking changes to all code related to that feature can occur through pull requests to php-src (`community` branch) without separate community RFCs, however a changelog should always be posted to the community RFC issue, by posting a new comment with the changelog and editing the first comment of the issue to point to the new comment.  

  Note: code and design-based breaking changes related to the subject of the community RFC are allowed without creating new community RFCs, only a major version bump is required, however only within the scope of the original RFC, for example:

  - Removing the deprecated `curl_close` function in the context of a `cleanup` feature centered around removing deprecated functions is allowed in a new major version of the `cleanup` feature.  
  - Removing the deprecated `curl_close` function in the context of an `async_http_client` feature unrelated to curl is **not** allowed even in a new major version.  

- Community RFC states:
  - Pending
  - Active
  - Accepted
  - Rejected

  The state is specified through appropriate issue labels, that can only be edited by internals members.  

- Voting is immediately open (at the Pending stage), and occurs through:
  - Simple GitHub 👍 = Accept, 👎 = Reject reactions on the issue, open to the entire PHP community, accounting for 50% of votes, simple majority.
  - internals members through GitHub 👍 = Accept, 👎 = Reject, 👀 = Abstain reactions on the issue using their GitHub accounts, accounting for 50% of votes, simple majority.  

  Voting ends:
  - 2-3 weeks after the issue is moved to the Active stage OR
  - 3 months after the issue is opened
  
  Results are valid if at least 50% of internals has voted (including abstain).  

  Voting results are fetched using the [gather_votes.php](https://github.com/danog/php-community-rfc/blob/main/gather_votes.php) script, which can be easily run by anyone at any time to get up-to-date voting results with a breakdown of internals and community votes, and the overall outcome.  

-  Once a community RFC is accepted, a design document must be committed to the repo, and kept updated with breaking/non-breaking changes: however, a fully detailed design document with full rationale, pro/counter arguments that will be turned into a full RFC is NOT required.  
  The committed design document can be a simple overview of the features, the API, and a link to the community RFC issue.  
  This is done because of the inherently mutable nature of php-community features: changes will likely be frequent, and updating rationale, examples and everything else that a full RFC requires will be mostly wasted work.  
  The design document should be finalized, including full examples, major arguments from the community discussion and community feedback and adoption data only when turning it into a normal RFC.  

- Once a community RFC is accepted and community adoption of the newly introduced features reaches a threshold (at the discretion of who proposed and implemented the community RFC, with involvement of the community), and in any case **at least** 6 months after its inclusion into a stable tag of `php-community`, a normal RFC can be proposed for inclusion into the main language.  

  The 6 months lower limit before conversion into a normal RFC is pretty much the only "hard" limit in community RFCs: with an excessively short feedback time authors may end up making RFCs without significant community adoption/feedback, which is essentially equivalent to doing RFCs with the old process.  

- The community RFC design document must contain the full list of the current maintainer(s) of the features, which can be updated by the maintainers themselves, or by internals members in case of inactivity of any of the maintainers.  

-  If a community RFC is accepted, then converted into a normal RFC, and then the normal RFC for some reason gets rejected, the community RFC is not automatically rejected and its features are not automatically removed from php-community.  

   Features introduced by an accepted community RFC can only be removed 6 months after it is accepted, through a separate removal community RFC.  

   A feature is eligible for removal only if **both** of the following conditions are met:  

   1. It has no active maintainer listed in the accepted community RFC design document.  
   2. Adoption is negligible, as evidenced by Packagist statistics.  

   The burden of proof lies with the party proposing removal, not with users. When a removal community RFC is accepted, a deprecation period of at least 3 stable `php-community` releases follows, during which the feature is still shipped but marked as deprecated, giving users time to speak up, take over maintenance, or migrate away.  

   This prevents removal of an actively-maintained, well-adopted feature through this process; equally, this lifts from php-src contributors the burden of indefinitely maintaining a dead one.  

   The above process is inspired by Linux's own unused feature removal process.  
  
- As always, discussion is open to everyone.

  The **key difference** between a community RFC discussion and a normal RFC discussion is that it should **not** be as heavily focused on whether it will be accepted or not by the larger community, the impact on frameworks, et cetera: it will be up the community to decide whether or not it will be used (including through packagist statistics).  

  In other words, discussion should follow mainly the same themes of a normal RFC discussion, just much lighter, without assuming anything in regards on adoption (or non-adoption) and impact on the community.  

  Crucially, the implementation details (API, actual code) of the feature at this stage should not be grounds to accept or reject a feature: more control is given to the author of the community RFC in this sense, who will effectively act just like a simple library or extension maintainer, making major design choices mostly autonomously during the initial stages of the community RFC, and according to community feedback mostly **after** the community RFC is accepted and released.  

- There is no backoff period between similar RFCs: a v2 of the community RFC can be proposed a day after v1 is rejected for some reason.  

### The php-community release process

Versioning will be date-based, i.e. `php-community 2026.01.01`.  

`php-community` will always be based on the latest stable PHP release, and will be released according to the following schedule:

- One stable release every month, the first Thursday of the month.  
- Security releases do not postpone the main release schedule, even if it means making two releases in two days, i.e. the 31st and the 1st (`php-community 2026.01.31` and `php-community 2026.02.01`), or the 1st and the 2nd (`php-community 2026.02.01` and `php-community 2026.02.02`).   
- Full nightly releases (with binaries) every day for even faster iteration (`php-community-nightly 2026.01.01`).  

`php-community` will live on a new `community` branch of `php-src`.  

Community releases are "handled" by the same release managers of the latest stable PHP release, however the release process will be significantly automated, compared to the current manual release process:

- Binary, packaged and source releases for both stable and nightly will be reproducible, auto-built and published to `php.net` by Github Actions on php-src, without human involvement (which also improves security).  
- Release managers only need to:
  - Branch off `community-202X.XX.XX` from `community`
  - Wait for the CI status of the new branch to become green
  - Create a new `php-community-202X.XX.XX` tag
  - Wait for the CI status of the tag to become green, which will automatically build and stage all binaries and packages for all platforms.  
    The final deployment step, deploying all staged binaries and packages and sending off an email to the mailing lists will also be automatic, based on the statuses of all builds for all platforms.  

    Human involvement is only needed in case of errors in any of the CI jobs, fixed by committing first to the `community` branch, then pushing to the `community-202X.XX.XX` branch, eventually re-creating the `php-community-202X.XX.XX` tag if the failure occurred only in the tag CI build.  

### Feature extensions

Core language behavior and features can be defined by optionally enabled, but always built-in **feature extensions**.  

Taking as an example features already merged in PHP, deprecations can be provided as a versioned `performance` or `strict` extension:  

- 1.0 - "Implicitly nullable parameter declarations deprecated" (from 8.4)
- 2.0 - "Non-canonical scalar type casts (boolean|double|integer|binary) deprecated" (from 8.5)
- 3.0 - Some other upcoming deprecation
- Et cetera.

Some feature extensions (like the `performance` extension above) may be provided in multiple versions at the same time, exposed through an appropriate API.  

Feature extensions may require or conflict with specific or range-based versions of other feature extensions, like Composer packages.  

A new `PhpFeature` class is offered to get info about available features and enable a specific version of a given **feature extension** at runtime: once enabled, the version cannot be changed.

The main intended usecase is integration into Composer through an `ext-X` dependency (or even `feature-X`), which will be automatically enabled by the composer autoloader according to package requirements.  
  
Apart from core language behavior, feature extensions may just be normal, community extensions being considered for inclusion into future PHP versions.

All features merged into php-community will be fully documented on php.net, just as if they were normal language features.


### API

What follows is the description of the API used to manage feature extensions.  

```php
/**
 * @type TPackage = array{
 *           name: string,
 *           version: string,
 *           type: "feature",
 *           description?: string,
 *           require?: array<string, string>,
 *           conflict?: array<string, string>,
 *           replace?: array<string, string>,
 *           provide?: array<string, string>,
 *           suggest?: array<string, string>,
 *       }
 */
final class PhpFeature {
    /**
     * Fetches all available feature extensions.
     * 
     * name => (version => PhpFeature)
     * @return array<string, array<string, self>>
     */
    public static function getAllFeatures(): array;

    /**
     * Fetches all available feature extensions, 
     * formatted as the `packages` field of a 
     * Composer repository, for easy integration.
     * 
     * name => (version => TPackage)
     * @return array<string, array<string, TPackage>>
     */
    public static function getAllFeaturesArray(): array; 

    /**
     * Returns all currently enabled feature extensions.
     * 
     * 
     * name => PhpFeature
     * @return array<string, PhpFeature>
     */
    public static function getEnabledFeatures(): array;

    /**
     * Checks if a feature exists, or if a (possibly constrained) version of a feature exists.  
     */
    public static function has(string $feature, ?string $version = null): bool;

    /**
     * Fetches a feature extension by its name and its exact or constrainted version.  
     * 
     * I.e. Feature::get("performance", "^2")->enable()
     * 
     * @throws RuntimeException If the specified feature extension or version cannot be found. 
     */
    public static function get(string $feature, string $version): self;

    /**
     * Checks if the feature can be enabled. 
     * 
     * Returns true if the feature is already enabled. 
     * 
     * Returns false if any of the currently loaded
     * feature extensions conflict with the current feature extension.
     */
    public function canEnable(): bool;
    /**
     * Checks if the feature is already enabled.
     */
    public function isEnabled(): bool;
    /**
     * Enables the feature.
     * 
     * @throws RuntimeException If the feature cannot be enabled due to conflicts of already loaded extensions with either the current feature or of features on which this feature depends.
     */
    public function enable(): void;

    /**
     * Gets the feature name.
     */
    public function getName(): string;
    /**
     * Gets the feature's version.
     */
    public function getVersion(): string;
    /**
     * Gets the feature's description.
     */
    public function getDescription(): ?string;

    /**
     * Returns info about the feature in composer.json format.  
     * 
     * @return TPackage
     */
    public function toComposer(): array;
}
```

The API is designed to be easily integrated into Composer, but also used standalone without it.  

Standalone, non-composer users can enable features, and check for conflicts with currently loaded features before enabling a feature.  

More complex `requires(self $other)`, `conflicts(self $other)`, `getDependencies(): list<self>`, etc. methods are omitted for simplicity, delegating dependency resolution through SAT solving to Composer.  