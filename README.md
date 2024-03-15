## Beta: LZI - SWH API Client (Documentation)

**Feedback appreciated**: https://github.com/dagstuhl-publishing/swh-client/issues/1

LZI has developed this API client and connector as part of the FAIR4CoreEOSC project to address the project's four pillars (Archive, Reference, Describe and Cite). The API is integrated to a Laravel-framework app and is wrapped round the [`Illuminate Http package`](https://packagist.org/packages/illuminate/http) and the [`GuzzleHTTP`](https://docs.guzzlephp.org/en/stable/index.html) library.
The functionality and use-cases are based upon the Software Heritage workflow provided as the server-side of communications.


| Project Tracking | https://github.com/orgs/dagstuhl-publishing/projects/8 |
|------------------|--------------------------------------------------------|

## API Principles

- #### As will be explored, the API interacts with the following SWH Merkle DAG (Directed Acyclic Graph) Model:
  
![Data Structure](https://github.com/dagstuhl-publishing/beta-faircore4eosc/blob/main/public/images/swh-merkle-dag.svg)
*[Figure: Server-side Data Structure](https://docs.softwareheritage.org/devel/swh-model/data-model.html#data-structure)*

- #### Full details of the server-side endpoints: [Software Heritage Endpoints](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html)

- #### Full details of the SWHID persistent Identifiers: [Syntax](https://docs.softwareheritage.org/devel/swh-model/persistent-identifiers.html#syntax)

## API Client

> The API is designed in classes and used as services or libraries throughout the application.

Initialise a new API session to explore each API class and its `public` methods:

```php
$ psysh 
Psy Shell v0.11.22 (PHP 8.2.0 — cli) by Justin Hileman
```

This will open a REPL console-based session where one can test the functionality of the api classes and their methods before building a suitable workflow/use-cases.

  > [!Note]
  > [`PsySH`](https://psysh.org/) should have been installed via `composer.json`.


## API Connections Classes 

The following settings are related to the base connection classes:

- #### Base connection classes (summary)

    * `HTTPClient`: Initialises a [`PendingRequest`](https://laravel.com/api/9.x/Illuminate/Http/Client/PendingRequest.html) instance with essential configurations for outgoing calls and defines the expected SWH endpoints.
    * `SyncHTTP`: Invokes synchronous HTTP calls and can receive multiple modifiable configurations.

      > Asynchronous calls: At the time of writing, SWH does not support such a pattern on the server-side.

- #### Default Configurations

The following configs are pre-configured in the `API Client` for all outgoing requests to SWH:

| Config             | Value                                                                                 | Notes                                                                                                                                                                                                                                                                                                                     |
|--------------------|---------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `accept`           | `application/json`                                                                    | - Specify the content type expected in SWH response to initiated requests.                                                                                                                                                                                                                                                |
| `decode_content`   | `gzip`                                                                                | - Pass `gzip` as the `Accept-Encoding` header. <br/>- Allows data transfer compression.                                                                                                                                                                                                                                   |
| `debug`            | `false`                                                                               | - Enable debug output (`cURL` verbose of `CURLOPT_VERBOSE` will be emitted).                                                                                                                                                                                                                                              |
| `delay`            | `0`                                                                                   | - The number of milliseconds to delay before sending requests to SWH.                                                                                                                                                                                                                                                     |
| `allow_redirects`  | - `max: 1`<br/>- `strict: true`<br/>- `protocols: https`<br/>-`track_redirects: true` | - Describes the redirect behaviour to SWH request: <br/>- Maximum number of allowed redirects.<br/>- use `strict` RFC compliant redirects.<br/>- Allowed protocol for redirect requests.<br/>- Redirected URI and status code are tracked in headers(`X-Guzzle-Redirect-History` and `X-Guzzle-Redirect-Status-History`). |
| `force_ip_resolve` | `v4`                                                                                  | - Enforces ipv4 protocol only.                                                                                                                                                                                                                                                                                            |
| `verify`           | `true`                                                                                | - Enables SSL certificate verification of SWH.<br/>- Uses the default CA bundle provided by OS.                                                                                                                                                                                                                           |
| `version`          | `1.1`                                                                                 | - HTTP Protocol version to use with the request.                                                                                                                                                                                                                                                                          |
| `synchronous`      | `true`                                                                                | - Inform HTTP handlers that waiting on SWH response is expected                                                                                                                                                                                                                                                           |

- #### Default Throwables (Exceptions)

The following `Exceptions` are caught (and `returned` gracefully to the invoking methods) by the `API Client` regardless to SWH endpoints:

| Exception                | On                                                                                                                  | Notes                                                                                                                                                             |
|--------------------------|---------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `RequestException`       | Client-side errors                                                                                                  | `e.g.` All 400-level errors except SWH-endpoints-related errors `(e.g., 400, 404)`; these will be reported individually per SWH endpoint, see class methods below |
| `GuzzleRequestException` | Internal errors                                                                                                     | `e.g.` Configuration Errors                                                                                                                                       |
| `ConnectionException`    | Internal errors                                                                                                     | `e.g.` Configuration Errors/loss of connectivity, ..                                                                                                              |
| `ValidationException`    | Non-valid `URL/SHA1`                                                                                                | `e.g.` Non-valid parameters expected by SWH endpoints before invoking a SWH call                                                                                  |
| `Exception`              | - SWH Server-side errors<br/>- HTTP Method mismatch <br/>- Invalid/Unsupported SWH endpoint <br/>- All other errors | `e.g.` All 500-level Errors, Unexpected, ..                                                                                                                       |

- #### Accessing Errors (Exceptions) through any instantiated class object, `$obj`

```php 
> $obj->getErrors()     // Access the most recent error reported by the API

> $obj->getMessages()   // Gets the Exception message
> $obj->getFile()       // Gets the file in which the exception occurred
> $obj->getCode()       // Gets the Exception code
> $obj->getLine()       // Gets the line in which the exception occurred
> $obj->getTrace()      // Gets the stack trace
```

- #### Preset Configurations

The following configs can be tweaked on different levels:


| Config              | Value/Type              | Notes                                                                                                                                                  | Level  |
|---------------------|-------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|--------|
| `debug`             | `bool`                  | - Allows debugging from handshake till connection closure.<br/>- Defualt: `false`                                                                      | Method |
| `delay`             | `ms`                    | - Specifies delay before some calls in ms.<br/>- Default: `0`                                                                                          | Method |
| `withHeaders`       | `bool`                  | - Outputs SWH response headers along with the response body                                                                                            | Method |
| `requireSnapshot`   | `bool`                  | - Outputs the latest visit that has a snapshot in the `visit` endpoint                                                                                 | Method |
| `stringType`        | `bool`                  | - Shows a string output on checking for SWH object or an origin existence                                                                              | Method |
| `distinctSnaps`     | `bool`                  | - Avoids snapshot redundancy in all stored visits of the SWH `visits endpoint` of an origin.                                                           | Method |
| `distinct`          | `bool`                  | - Avoids snapshot redundancy in all stored archive requests of the SWH `save endpoint` of an origin.                                                   | Method |
| `apiURL`            | `URL`                   | - Sets SWH production/staging API URL.<br/>- Default: `SWH production URL`<br/>- Read from `config/swh.php` to `.env` file.                            | Class  |
| `isVerbose`         | `bool`                  | - Allows detailed progress logging from method invoking to end results.<br/>- Default: `false`                                                         | Class  |
| `fileDatestamp`     | `bool`                  | - Allows temporarily logging to a date-stamped file.<br/> - Default: `false` Stored under `(storage/logs/swhAPI.log)`                                  | Class  |
| `responseType`      | `collect\|object\|json` | - Receives SWH response in one of these types <br/>- `Collection`<br/>- `Object`<br/>- `Array (default)`                                               | Class  |
| `echoFlag`          | `bool`                  | - Allows echoing output to `stdout` in the opened tinker session                                                                                       | Class  |
| `timeout`           | `5`                     | - The maximum number of _seconds_ to wait while trying to connect to SWH<br/><br/>- Throws `ConnectionException` when exhausted.                       | Class  |
| `connectTimeout`    | `5`                     | - The maximum number of _seconds_ to wait for a SWH response.                                                                                          | Class  |
| `retry`             | `5`                     | - Attempt retries if there has been `connectionException` or <br/>`$e->response->status() >= 500`<br/><br/>- Throws `RequestException` when exhausted. | Class  |
| `sleepMilliseconds` | `5000`                  | - The number of _milliseconds_ to wait in between retry attempts.                                                                                      | Class  |

### A) Class-level Options:

- Setting such options via `setOptions()` method:

>[!Note]
> The following 4 options can be changed during the course of any workflow. i.e. have immediate effect.
    
```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector;    
    
    //   Specify multiple `class-level` options as named parameters     
    
> HTTPClient::setOptions(responseType:'object', apiURL: 'https://webapp.staging.swh.network')

> HTTPClient::setOptions(isVerbose: true, fileDatestamp: false)
```

>[!Note]
> The following 4 options are available only for the lifetime of instantiated object, i.e. once changed, a new object should be redefined.

```php
    //   Specify multiple `class-level` options as named parameters
    
> HTTPClient::setOptions(connectTimeout: 10, timeout: 30, retry: 2, sleepMilliseconds: 1500)
```

- Setting such options on individual `static` members:

```php
// Specify `class-level` options individually on the following `HTTPClient` class static properties:

> HTTPClient::$responseType = 'collect'                     // SWH responses will be rendered as Collections

> HTTPClient::$apiURL='https://webapp.staging.swh.network'  // Invokes requests on the SWH staging server instead

> HTTPClient::$logFileTimestamp = true                      // temporarily log output to a timestamped file

> HTTPClient::$echoFlag = true                              // allow echoing output to `stdout` in the opened tinker session
⋮
```

### B) Method-level Options:

These options are defined on individual methods (see which ones as described below for applicability)

```php
// Specify `method-level` options as named parameters:
> namespace Module\Archival;
> use Module\Archival; 

> $archiveRequest = new Archive('https://github.com/RamyTestAccount/D2','git')
> $archiveRequest->save2swh(debug: true, delay:2000)    // e.g. options defined on `save2swh` method
```

## API SWH Classes 

The following classes interact with various SWH endpoints providing the functionality defined by the [graph model](https://github.com/dagstuhl-publishing/beta-faircore4eosc/edit/main/app/Modules/SwhApi/README.md#as-will-be-explored-the-api-interacts-with-the-following-swh-merkle-dag-tree-model)

### I) SWHOrigins

This class reveals information regarding software origins as stored in SWH.

> `new SwhOrigins($url[, ...$options])`
>>- `Extends: syncHTTP`
>>>- `Extends: HTTPClient`
>>- `$url: <string>` the origin url
>>- `...$options: named parameters` [Configs](https://github.com/dagstuhl-publishing/beta-faircore4eosc/edit/main/app/Modules/SwhApi/README.md#preset-configurations)

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/14 |
|-------------------|----------------------------------------------------------------|

Instantiate an origin object for the desired repository URL:

```php
> namespace Module\OriginVisits;
> use Module\OriginVisits; 

> $originObject = new SwhOrigins('https://github.com/RamyTestAccount/D2');

= Module\OriginVisits\SwhOrigins {#6480
    +url: "https://github.com/RamyTestAccount/D2",
  }
```
> #### SwhOrigins Methods:

- Get `ori` ID of the given URL in the SWH archive.
 
    > `oriID` is not part of SWH identifiers specification. It's used internally for the `graph` endpoint.

| `Class` Method            | Returns                                                        | `SWH` Endpoint  | `HTTP` Method |
|-------------------|----------------------------------------------------------------|---------------|-------------|
| `getOriFromURL()` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError` | [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-) | `GET`         |


```php
> $oriID = $originObject->getOriFromURL()

    // SWHCoreID dataType (object form)
= Module\DataType\SwhCoreID {#6525                            
    +"ori": "swh:1:ori:3f78f17262f89b425e8c8816fbc068d3e10cb996",
  } 
  
    // String form:  
> $oriID->getswhid()
= "swh:1:ori:3f78f17262f89b425e8c8816fbc068d3e10cb996"
```

- Retrieve all data from the `origin` endpoint of the given URL in the SWH archive.
  
| `Class` Method                 | Method `$options` (defaults)                                                                           | Returns                                                                             | `SWH` Endpoint                                                                                                      | `HTTP` Method |
|--------------------------------|--------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullOrigin([...$options])` | Named Parameters: <br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-) | GET           |

  
```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::setOptions(responseType: 'collect')

> $originObject->getFullOrigin()

= Illuminate\Support\Collection {#6577
    all: [
      "url" => "https://github.com/RamyTestAccount/D2",
      "origin_visits_url" => "https://archive.softwareheritage.org/api/1/origin/https://github.com/RamyTestAccount/D2/visits/",
      "metadata_authorities_url" => "https://archive.softwareheritage.org/api/1/raw-extrinsic-metadata/swhid/swh:1:ori:3f78f17262f89b425e8c8816fbc068d3e10cb996/authorities/",
    ],
  }
```

- Check if some SW origin is known to SWH 

| `Class` Method                | Method `$options` (defaults)                                                                         | Returns                                                           | `SWH` Endpoint                                                                                                      | `HTTP` Method |
|-------------------------------|------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|---------------|
| `originExists([...$options])` | Named Parameters:<br/>- `stringType: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `String\|True`<br/>- `Throwable: RequestException \| Exception` | [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-) | HEAD          |

```php
> $originObject->originExists()
= true

    // String Response
> $originObject->originExists(stringType: true)
= "https://github.com/RamyTestAccount/D2 --> Exists in SWH"
```

> `Exceptions` are `returned` rather than `thrown`, e.g. on non-existing identifiers `RequestException` is returned.

```php
> (new SwhOrigins('https://github.com/RamyTestAccount/D23'))->originExists();
          
= Illuminate\Http\Client\RequestException {#6554            // RequestException is returned
    #message: "HTTP request returned status code 404",
    #code: 404,
    #file: "..\faircore4eosc\vendor\laravel\framework\src\Illuminate\Http\Client\Response.php",
    #line: 272,
  }

    // Load Latest Errors on the $originObject    
> $originObject->getErrors()                    // Note: These errors are cleared out after each call to getErrors()
= [
    "2023-10-23 16:57:58 --> Non-Successful HTTP Status Code: 404 --> Reason: Origin with url https://github.com/RamyTestAccount/D23 not found!",
    "2023-10-23 16:57:58 --> 404 : Requested Origin was not found in SWH for: https://github.com/RamyTestAccount/D23",
  ]
```

### II) SwhVisits

This class reveals information regarding SWH visits on software origins and related snapshots (graph root nodes).

> `new SwhVisits($url[, ...$options])`
>>- `Extends: syncHTTP`
>>>- `Extends: HTTPClient`
>>- `$url: <string>` the origin url
>>- `...$options: named parameters` [Configs](https://github.com/dagstuhl-publishing/beta-faircore4eosc/edit/main/app/Modules/SwhApi/README.md#preset-configurations)

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/14 |
|-------------------|----------------------------------------------------------------|

Instantiate a visit object for the desired repository URL:

```php
> namespace Module\OriginVisits;
> use Module\OriginVisits; 

> $visitObject = new SwhVisits('https://github.com/torvalds/linux/');

= Module\OriginVisits\SwhVisits {#6789
    +url: "https://github.com/torvalds/linux/",
  }
```

> #### SwhVisits Methods:

- Get all performed visits' data by SWH on an origin.

    > This method follows pagination internally depending on the Link Header.

| `Class` Method                | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|-------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `getAllVisits([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |


```php
> $visitObject->getAllVisits()
= [
    [
      "origin" => "https://github.com/torvalds/linux",
      "visit" => 184,
      "date" => "2023-10-20T02:34:49.502245+00:00",
      "status" => "full",
      "snapshot" => "0a86a485d9c8db0b1d4c58240282dba7a42ecfac",
      "type" => "git",
      "metadata" => [],
      "origin_visit_url" => "https://archive.softwareheritage.org/api/1/origin/https://github.com/torvalds/linux/visit/184/",
      "snapshot_url" => "https://archive.softwareheritage.org/api/1/snapshot/0a86a485d9c8db0b1d4c58240282dba7a42ecfac/",
    ],
    ⋮
]
```

- Show all visits data with the SWH `full` visit status only.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method                      | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|-------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `showAllFullVisits([...$options])`  | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |

```php
> $visitObject->showAllFullVisits(withHeaders: true)
```

- Show all visits by a specific SWH `visit status` only.
  
  > This method follows pagination internally depending on the Link Header.
  

| `Class` Method                                   | Method Arguments                                                                                                                                        | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|--------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `showVisitsByStatus($visitStatus[,...$options])` | `<string> $visitStatus:`<ul><li>`'full'`</li><li>`'created'`</li><li>`'partial'`</li><li>`'not_found'`</li><li>`'failed'`</li><li>`'ongoing'`</li></ul> | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |


```php
> $visitObject->showVisitsByStatus('partial')
= [
    [
      "origin" => "https://github.com/torvalds/linux",
      "visit" => 66,
      "date" => "2020-09-21T17:12:11.930011+00:00",
      "status" => "partial",
      "snapshot" => null,
      "type" => "git",
      "metadata" => [],
      "origin_visit_url" => "https://archive.softwareheritage.org/api/1/origin/https://github.com/torvalds/linux/visit/66/",
      "snapshot_url" => null,
    ],
    ⋮
```

- Show all visits having distinct snapshots.
 
  > This method follows pagination internally depending on the Link Header.

| `Class` Method                            | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|-------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `showDistinctFullVisits([...$options])`   | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |

```php
> $visitObject->showDistinctFullVisits()
```

- Show the _first_ `full` visit from the swh visits list of an origin.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method                        | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|---------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `showFirstFullVisit([...$options])`   | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |

```php
> $visitObject->showFirstFullVisit()
```

- Show the very _last_ `full` visit from the swh visits list (data) of an origin.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method                       | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|--------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `showLastFullVisit([...$options])`   | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |

```php
> $visitObject->showLastFullVisit()
```

- Check if a specific visit (by number) for some SW origin exists in SWH visits.

| `Class` Method                            | Method Arguments               | Method `$options` (defaults)                                                                         | Returns                                                           | `SWH` Endpoint                                                                                                                  | `HTTP` Method |
|-------------------------------------------|--------------------------------|------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------|---------------|
| `visitExists($visitNumber[,...$options])` | `<int> $visitNumber: visit ID` | Named Parameters:<br/>- `stringType: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `String\|True`<br/>- `Throwable: RequestException \| Exception` | [`visit`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visit-(visit_id)-) | HEAD          |

```php
> $visitObject->visitExists(143)
= true

    // String Response
> $visitObject->visitExists(143, stringType: true)
= "Visit #: '143' for https://github.com/torvalds/linux/ --> Exists in SWH"
```

- Get a specific visit (given a `visit identifier` or requesting `latest`) of some SW origin.

| `Class` Method                   | Method Arguments                                                          | Method `$options` (defaults)                                                               | Returns                                                                              | `SWH` Endpoint                                                                                                                     | `HTTP` Method |
|----------------------------------|---------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getVisit($visit[,...$options])` | `<int>\|<string> $visit`<br/>- `<int>: visit ID`<br/>- `<string>: 'latest'` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `requireSnapshot: bool` <br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception`  | [`visit`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visit-(visit_id)-) <br/>[`visit`](https://archive.softwareheritage.org/api/1/origin/visit/latest/doc/)   | GET           |


```php
> $visitObject->getVisit(85)    // by visit ID

> $visitObject->getVisit('latest', requireSnapshot: true)   // latest visit with a snapshot
= [
    "origin" => "https://github.com/torvalds/linux",
    "visit" => 184,
    "date" => "2023-10-20T02:34:49.502245+00:00",
    "status" => "full",
    "snapshot" => "0a86a485d9c8db0b1d4c58240282dba7a42ecfac",
    "type" => "git",
    "metadata" => [],
    "origin_url" => "https://archive.softwareheritage.org/api/1/origin/https://github.com/torvalds/linux/get/",
    "snapshot_url" => "https://archive.softwareheritage.org/api/1/snapshot/0a86a485d9c8db0b1d4c58240282dba7a42ecfac/",
  ]
```

- Generate all graph root nodes (`snapshots`) keyed by the corresponding timestamp.

    > This method follows pagination internally depending on the Link Header.

| `Class` Method                             | Method `$options` (defaults)                                                                                                              | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|--------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `getAllSnapshotsFromVisits([...$options])` | Named Parameters:<br/>- `distinctSnaps: bool (false)`<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-) | GET           |


```php
> $visitObject->getAllSnapshotsFromVisits(distinctSnaps: true)
= [
    "2023-10-20T02:34:49.502245+00:00" => "swh:1:snp:0a86a485d9c8db0b1d4c58240282dba7a42ecfac",
    "2023-10-16T14:25:29.395808+00:00" => "swh:1:snp:3c0e2ec3b3a323713cefbc4b742ef8e1b2e178ee",
    ⋮
  ]
```

- Get a specific `snp` core ID from the visits list based on a visit date/identifier.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method                         | Method Arguments                                                                                                      | Returns                                                        | `SWH` Endpoint                                                                                                           | `HTTP` Method |
|----------------------------------------|-----------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------|---------------|
| `getSnpFromVisits($visitDateOrNumber)` | `<string> \| <int>: $visitDateOrNumber`<br/>- `<string>: ISO8601/RFC3339 visit date (in UTC)`<br/>- `<int>: visit ID` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-)   | GET           |

```php
> $visitObject->getSnpFromVisits('2023-10-20T02:34:49.502245+00:00')    // by visit date

    // SWHCoreID dataType (Object form)
    
= Module\DataType\SwhCoreID {#6611
    +"snp": "swh:1:snp:0a86a485d9c8db0b1d4c58240282dba7a42ecfac",
  }

    // String form
> $visitObject->getSnpFromVisits('2023-10-20T02:34:49.502245+00:00')->snp  // accessible on the `snp` property

= "swh:1:snp:0a86a485d9c8db0b1d4c58240282dba7a42ecfac"
```

- Get a specific `snp` core ID for a given visit specified by its identifier or order in the visit list.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method            | Method Arguments                                                                                | Returns                                                        | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                  | `HTTP` Method |
|---------------------------|-------------------------------------------------------------------------------------------------|----------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getSnpFromVisit($visit)` | `<string> \| <int>: $visit`<br/>- `<string>: 'latest', 'first', 'last'`<br/>- `<int>: visit ID` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError` | [`visit`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visit-(visit_id)-) <br/>[`visit`](https://archive.softwareheritage.org/api/1/origin/visit/latest/doc/)<br/>[`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-)  | GET           |

```php
> $visitObject->getSnpFromVisit('latest')   // latest if snapshot is available

> $visitObject->getSnpFromVisit('first')    // from the first visit ever

> $visitObject->getSnpFromVisit('last')     // from last visit in swh visit list

> $visitObject->getSnpFromVisit(141)        // by a specific visit order

= Modules\DataType\SwhCoreID {#6661
    +"snp": "swh:1:snp:4a86094c3828695d578a5fbd51de267bfd7ee8fb",
  }

    // String form
> $visitObject->getSnpFromVisit('latest')->snp  // accessible on the `snp` property

= "swh:1:snp:0a86a485d9c8db0b1d4c58240282dba7a42ecfac"
```

- Build graph nodes from all roots (snapshots) for the given origin keyed by the corresponding SWH object type.

    > This can take time for dense repositories. The method ignores revision log.

| `Class` Method     | Returns                                                       | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | `HTTP` Method |
|--------------------|---------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `buildGraphNodes()` | - `Iterable`<br/>- `Throwable: RequestException \| Exception` | [`visits`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visits-)<br/>[`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/> [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

```php
$visitObject->buildGraphNodes()

--> Graph built in: 25.3 seconds
= [
    "2023-10-24T19:30:11.735248+00:00" => [
      "swh:1:snp:8f6ad0ffefef9bff8d9088771162dfe8bece8031" => [
        [
         "branch" => "refs/heads/main",
         ⋮
        ]               
     ]
     ⋮
]]
```

### III) GraphNode

This class deals abstractly with swh objects (`snapshot`, `revision`, `release`, `directory`, `content`) as individual graph nodes on object-basis by implementing typical (Merkle DAG) graph node use cases. The use cases are gathered in the `Interface` it implements.

> `new GraphNode($swhid[, ...$options])`
>>- `Extends: syncHTTP`
  >>>- `Extends: HTTPClient`
>>- `$swhid: <string>` <font size='2'>the SWHID</font>
>>- `...$options: named parameters` [Configs](https://github.com/dagstuhl-publishing/beta-faircore4eosc/edit/main/app/Modules/SwhApi/README.md#preset-configurations)
>>- `Throws TypeError` <font size='2'>On malformed SwhID</font>
>>- `Implements SwhNodes`

This class `Implements` the `SwhNodes Interface` which comprises the following functionality for any node:

| Method              | Notes                                                             |
|---------------------|-------------------------------------------------------------------|
| `which()`           | determines which node type it is.                                 |
| `nodeExists()`      | checks if it's a swh-compatible node.                             |
| `nodeHopp()`        | retrieves all node information.                                   |
| `nodeEdges()`       | builds the entire node edges.                                     |
| `nodeTargetEdge()`  | retrieves a specific target edge from the node edges set.         |
| `nodeTraversalTo()` | traverses forwardly to the target node from the initialised node. |


| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/164 |
|-------------------|-----------------------------------------------------------------|

Instantiate a node object for any SWH object (`snapshot`, `revision`, `release`, `directory`, `content`), Examples:

```php
> namespace Module\DAGModel;
> use Module\DAGModel; 

> $nodeObject = new GraphNode('swh:1:snp:bcfd516ef0e188d20056c77b8577577ac3ca6e58')
> $nodeObject = new GraphNode('swh:1:rev:2d3af2a2db948a44caed042994a4f1779c8ea7c1')
> $nodeObject = new GraphNode('swh:1:rel:8a6b8c6072364f068c490fcd07c42ad52748dca9')
> $nodeObject = new GraphNode('swh:1:dir:8af8598a33cb11038a8d974ed213a31a49ef8612')
> $nodeObject = new GraphNode('swh:1:cnt:22fd0c4c0a0a9b6f87f89169352357cb3a386618')
```

Node objects can also be instantiated using `SWH Contextual IDs`, Examples:

```php
> $nodeObject = new GraphNode('swh:1:snp:bcfd516ef0e188d20056c77b8577577ac3ca6e58')

    // Directory Context
> $nodeObject = new GraphNode('swh:1:dir:58b57d150d3350b7702df80bf0d327a6474fa528;origin=https://github.com/openssl/openssl;visit=swh:1:snp:287360875eb1c114873f020be414ad1db8629557;anchor=swh:1:rev:d6e4056805f54bb1a0ef41fa3a6a35b70c94edba')

    // Snapshot Context
> $nodeObject = new GraphNode('swh:1:snp:c447a0efe4e558f64565865f2c2ade7c5d7255eb;origin=https://github.com/tensordiffeq/TensorDiffEq')        

    // Content Context
> $nodeObject = new GraphNode('swh:1:cnt:8164e8d75970d2e1c568287f45d460bf3dad93bd;origin=https://github.com/openssl/openssl;visit=swh:1:snp:6759d1b5890f54ed531e74fc3e9c38d3d2314b58;anchor=swh:1:rev:e9241d16b47f24e27966bee0f8664a6b88994164;path=/util/perl/OpenSSL/Util/Pod.pm')
```

> #### GraphNode Methods:

- Determine the node type, i.e. which SWH object the class object, `nodeObject` is instantiated on.

| `Class` Method | Returns                                                     | `SWH` Endpoint                                                       | `HTTP` Method |
|----------------|-------------------------------------------------------------|----------------------------------------------------------------------|---------------|
| `which()`      | - `String`<br/>- `Throwable: RequestException \| Exception` | [`resolve`](https://archive.softwareheritage.org/api/1/resolve/doc/) | GET           |

```php
> $nodeObject = new GraphNode('swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f177')

= Module\DAGModel\GraphNode {#6605
    +nodeID: "swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f178",
  }

> $nodeObject->which()
= "snapshot"
```

- Check if the instantiated node is a SWH-node, i.e. `SWHID` given on `GraphNode` exists as SoftWare Heritage persistent IDentifier.

| `Class` Method              | Method `$options` (defaults)                                                                         | Returns                                                           | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | `HTTP` Method |
|-----------------------------|------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `nodeExists([...$options])` | Named Parameters:<br/>- `stringType: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `String\|True`<br/>- `Throwable: RequestException \| Exception` | - [`resolve`](https://archive.softwareheritage.org/api/1/resolve/doc/)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | HEAD          |

```php
> $nodeObject = new GraphNode('swh:1:rev:2d3af2a2db948a44caed042994a4f1779c8ea7c1')

> $nodeObject->nodeExists()
= true

    // String Response
> $nodeObject->nodeExists(stringType: true)
= "swh:1:rev:2d3af2a2db948a44caed042994a4f1779c8ea7c1 --> Exists in SWH"
```

- Get all information of the given node.

  > This method follows pagination internally depending on the Link Header.

| `Class` Method            | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | `HTTP` Method |
|---------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `nodeHopp([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | - [`resolve`](https://archive.softwareheritage.org/api/1/resolve/doc/)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

```php
> $nodeObject = new GraphNode('swh:1:cnt:22fd0c4c0a0a9b6f87f89169352357cb3a386618')

> $nodeObject->nodeHopp()
```

> `Exceptions` are `returned` rather than `thrown`, e.g. on non-existing identifiers `RequestException` is returned.

```php
> $nodeObject = new GraphNode('swh:1:cnt:22fd0c4c0a0a9b6f87f89169352357cb3a386617');

$nodeObject->nodeEdges()

= Illuminate\Http\Client\RequestException {#6840      // RequestException is returned
    #message: """
      HTTP request returned status code 404:\n
      {"exception":"NotFoundExc","reason":"Content with sha1_git checksum equals to 22fd0c4c0a0a9b6f87f89169352357cb3a386617 n (truncated...)\n
      """,
    #code: 404,
    #file: "...\faircore4eosc\vendor\laravel\framework\src\Illuminate\Http\Client\Response.php",
    #line: 272,
  }
    
    // Load Latest Errors on the $nodeObject
    
> $nodeObject->getErrors()      // Note: These errors are cleared out after each call to getErrors()
= [
    "2023-11-02 22:30:26 --> Non-Successful HTTP Status Code: 404 --> Reason: Content with sha1_git checksum equals to 22fd0c4c0a0a9b6f87f89169352357cb3a386617 not found!",
    "2023-11-02 22:30:26 --> 404 : Requested swhID was not found in SWH",
  ]
```

- Get all node edges keyed by the respective name of children nodes.

| `Class` Method             | Method `$options` (defaults)                                                                           | Returns                                                                              | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | `HTTP` Method |
|----------------------------|--------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `nodeEdges([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)`  | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception`  | - [`resolve`](https://archive.softwareheritage.org/api/1/resolve/doc/)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | HEAD          |

> snapshot node example. From Repository: https://github.com/nodejs/node/

```php
> $nodeObject = new GraphNode('swh:1:snp:c457553344ef5afa928d740e97727bcde0a4e84c', responseType: 'collect')

> $nodeObject->nodeEdges()

= Illuminate\Support\Collection {#6939
    all: [
      "refs/heads/actions/tools-update-acorn-walk" => "swh:1:rev:4d3ee9ac3e5553ac91cacd136d7811a35dcb07f2",
      ⋮
      "refs/pull/10282/head" => "swh:1:rev:b8ed11bbe99faa45f482deaf8e7bf39bbcf4c69b",
      ⋮
      "refs/tags/v18.1.0" => "swh:1:rel:3a3f30ecf03cfe63234c58d629345f151f58a7ff",
      ⋮ 
    ]
  }

> $nodeObject->nodeEdges()->count()      // number of returned snapshot edges
= 33032
```

> `Directory` node example. From Repository: https://github.com/hylang/hy/

```php
> $nodeObject = new GraphNode('swh:1:dir:8af8598a33cb11038a8d974ed213a31a49ef8612')   

> $nodeObject->nodeEdges()

= [
    ".dockerignore" => "swh:1:cnt:6b8710a711f3b689885aa5c26c6c06bde348e82b",
    ⋮
    "hy" => "swh:1:dir:472e48d4910c9fddcad627ca8b324607147f5ca8",
    ⋮
  ]
```

> `Content` node example where edges are non-applicable

```php
> $nodeObject = new GraphNode('swh:1:cnt:3cce154395b00511add6f183bb6edd975285bf5a')

> $nodeObject->nodeEdges()

= Exception {#8201                              // Exception is returned
    #message: "No Edges. Contents are leaves.",
    #file: "..\faircore4eosc\app\Modules\SwhApi\GraphNode.php",
    #line: 194,
  }

        // Load Latest Errors on the $nodeObject        
> $nodeObject->nodeEdges()
= [
    "2023-11-04 21:17:10 --> No Edges. Contents are leaves.",
  ]
```


- Get a specific node from the set of edges by its name. Depending on node type, the target name may refer to `branch`, `directory`, `file`, `tag`, etc.

    > This method resolves the child node directly to its nodeID (`SWHID/SwhObject`).

| `Class` Method                | Method Arguments       | Returns                                                                                                            | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | `HTTP` Method |
|-------------------------------|------------------------|--------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `nodeTargetEdge($targetName)` | `<string> $targetName` | - `SwhCoreID`<br/>- `Array`<br/>- `Throwable: RequestException \| TypeError \| Exception \| ItemNotFoundException` | - [`resolve`](https://archive.softwareheritage.org/api/1/resolve/doc/)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

> From a `snapshot` node, get a target revision node specified by its name.

```php
> namespace Module\OriginVisits;
> use Module\OriginVisits; 

>$snpID = (new SwhVisits('https://github.com/tensordiffeq/TensorDiffEq'))
          ->getSnpFromVisit('latest')
          ->getSwhid()
          
> $nodeObject = new GraphNode($snpID)

> $nodeObject->nodeTargetEdge('refs/tags/v0.1.6.1')     // by edge name

= Modules\DataType\SwhCoreID {#7115
    +"rev": "swh:1:rev:fd403ffe91573e95d599ce5e449a45bde9e49627",
  }
```

> From a `revision` node, get a direct edge by its type

```php
> $nodeObject = new GraphNode('swh:1:rev:fd403ffe91573e95d599ce5e449a45bde9e49627')

> $nodeObject->nodeTargetEdge('directory')      // root directory returned as SwhCoreID object 

= Modules\DataType\SwhCoreID {#6622
    +"dir": "swh:1:dir:903ee7f51be999b101f7bdf65d23c033edaaafc7",
  }

> $nodeObject = new GraphNode('swh:1:rev:ce55c22ec8b223a90ff3e084d842f73cfba35588') 

> $nodeObject->nodeTargetEdge('parents')        // parents edges returned as array

= [
    "swh:1:rev:74e9347ebc5be452935fe4f3eddb150aa5a6f4fe",
    "swh:1:rev:524515020f2552759a7ef1c9d03e7dac9b1ff3c2",
  ]
```

> From a `directory` node, get a target node from its set of edges. It can either be a subdirectory or a file.

```php
> $nodeObject = new GraphNode('swh:1:dir:903ee7f51be999b101f7bdf65d23c033edaaafc7')

> $nodeObject->nodeTargetEdge('tensordiffeq')   // by edge name: directory name in this example

= Modules\DataType\SwhCoreID {#6549
    +"dir": "swh:1:dir:820c02cea138acde299f3c63cd87bb562add9314",
  }
```

- Traverse to a specific target node.

    > This method resolves the child node directly to its nodeID (`SWHID/SwhObject`).
    > 
    > For snapshot nodes, this method expects an array of two queues as described in [`traverseFromSnp`](#vi-graphtraversal)

| `Class` Method             | Method Arguments                                                                                                                                                                                                                                                                                                          | Returns                                                                                                | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | `HTTP` Method |
|----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `nodeTraversal([$target])` | `<string> \| <array>: $target`<br/><br/>- Initial Node: `rev \| dir`<br/> `<string>: path (root-relative)`<ul><li>`(sub)directory`<li> `content`</ul><br/>- Initial Node: `snp` <br/>`<array>: array of two Queues`<ul><li>`$nodeQueues['branchName']: queue of branch names`</li><li>`nodeQueues['path']: queue of path nodes`</li></ul>` | - `SwhCoreID`<br/>- `stdClass (object)`<br/>- `Throwable: RequestException \| TypeError \| Exception ` | - [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

> From a `revision`, traverse to a deeply nested file:

```php
> $nodeObject = new GraphNode('swh:1:rev:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c')

> $nodeObject->nodeTraversal('deps/nghttp2/lib/includes/nghttp2/nghttp2ver.h.in')

= Modules\DataType\SwhCoreID {#7081
    +"cnt": "swh:1:cnt:7717a647f7558135328d2877ad0f6aa45a3c5518",
  }
```

> From a `directory`, traverse to a deeply nested directory:

```php
> $nodeObject = new GraphNode('swh:1:dir:38139c56cb9ad67d68bf8afd7451a00098ae6402')

> $nodeObject->nodeTraversal('deps/base64/base64/lib/arch/neon64')

= Modules\DataType\SwhCoreID {#7125
    +"dir": "swh:1:dir:369efa02d089ee55f6fd82aebff461e5bb67e800",
  }
```

> From a `release`, traverse the final `revision` it points to:

```php
> $nodeObject = new GraphNode('swh:1:rel:4e69243a555b9e97395bc63bd02c399b2a3f2d81')

> $nodeObject->nodeTraversal()      // no target necessary.

= Modules\DataType\SwhCoreID {#7283
    +"rev": "swh:1:rev:f4e5bebe7d83727cd64ed4762a59e1336f5f3c89",
  }
```


### IV) GraphHopping

This class reveals all information, `full JSON data`, regarding SWH Objects (akin to hopping on graph nodes). This class is `abstract` with all its methods set as `static`, hence no object instantiation is necessary; all methods are accessible throughout as global functions. This class provides access to conveniently deal with SWH objects (graph nodes) based on self-explanatory naming of its methods.

> `Abstract GraphHopping::class`

Abstract class usage:

```php
> namespace Module\DAGModel;
> use Module\DAGModel; 

> GraphHopping::methodName();     // methodName() is prepended with the class name and two colons `::` 
```

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/43 |
|-------------------|----------------------------------------------------------------|

> #### GraphHopping Methods:

- Get all data of a `Snapshot` per its identifier.

    > This method follows pagination and builds the entire node contents.

| `Class static` Method                    | Method Arguments                                            | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                         | `HTTP` Method |
|------------------------------------------|-------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullSnapshot($snpID, [...$options])` | `<string> $snpID:`<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)   | GET           |


```php
> GraphHopping::getFullSnapshot('b8e164cfcf47da2323d5aef6e01fcd7f0c27f177')     // call on the 40-hexadecimal-string

> GraphHopping::getFullSnapshot('swh:1:snp:6d8ff23b72ba0d450c6d6b5fc127f2b07cdc9abe')   // as full snapshot ID

= [
    "id" => "6d8ff23b72ba0d450c6d6b5fc127f2b07cdc9abe",
    "branches" => [
      "HEAD" => [
        "target" => "refs/heads/master",
        "target_type" => "alias",
        "target_url" => "https://archive.softwareheritage.org/api/1/revision/c8451c141e07a8d05693f6c8d0e418fbb4b68bb7/",
      ],
      ⋮
    ]
]
```

> `Exceptions` are `returned` rather than `thrown`, e.g. on non-existing identifiers `RequestException` is returned.

```php
> GraphHopping::getFullSnapshot('swh:1:snp:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655d')

= Illuminate\Http\Client\RequestException {#6584        // RequestException is returned
    #message: """
      HTTP request returned status code 404:\n
      {"exception":"NotFoundExc","reason":"Snapshot with id 9cf5bf02b583b93aa0d149cac1aa06ee4a4f655d not found!"}\n
      """,
    #code: 404,
    #file: "...\faircore4eosc\vendor\laravel\framework\src\Illuminate\Http\Client\Response.php",
    #line: 272,
  }
  
    // Load Latest Errors on the HTTPClient base class
    
> HTTPClient::getErrors()   // Note: These errors are cleared out after each call to getErrors()
= [
    "2023-10-29 20:59:15 --> Non-Successful HTTP Status Code: 404 --> Reason: Snapshot with id 9cf5bf02b583b93aa0d149cac1aa06ee4a4f655d not found!",
    "2023-10-29 20:59:15 --> 404 : Requested Snapshot was not found in SWH",
  ]
```

- Get information about a `Revision/Release/Directory/Content` per its identifier.

| `Class static` Method                                                                                                                                                                                    | Method Arguments                                                                        | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | `HTTP` Method |
|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| <ul><li>`getFullRevision($revID[,...$options])`</li><li>`getFullRelease($relID[,...$options])`</li><li>`getFullDirectory($dirID[,...$options])`</li><li>`getFullContent($cntID[,...$options])`</li></ul> | `<string>: $revID / $relID / $dirID / $cntID:`<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | - [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |


```php
> GraphHopping::getFullRevision('swh:1:rev:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c')
 
> GraphHopping::getFullRelease('swh:1:rel:1791be4df87a0d69008ba46c5a03be2e4cfbe3d5')

> GraphHopping::getFullDirectory('swh:1:dir:7f465824589e766653ca53ed7cd398260e24a5e8')

> GraphHopping::getFullContent('swh:1:cnt:8f427082e6bc1a7b420b27954d9853a11600422e')
```

> Accessing special data

```php
> HTTPClient::$responseType = 'object'

> GraphHopping::getFullRevision('swh:1:rev:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c')->author->fullname  
= "Kodi Arfer <Kodiologist@users.noreply.github.com>"

> GraphHopping::getFullRelease('swh:1:rel:1791be4df87a0d69008ba46c5a03be2e4cfbe3d5')->name
= "v6.1-rc7"
   
> GraphHopping::getFullDirectory('swh:1:dir:7f465824589e766653ca53ed7cd398260e24a5e8')[0]->type
= "file"
 
>  GraphHopping::getFullContent('swh:1:cnt:8f427082e6bc1a7b420b27954d9853a11600422e')->checksums
= {#6722
    +"blake2s256": "c8bbbbeafc436d0666f57299c71e074b3b18fcfd7fe5d42ff86b61f8ebadc3b4",
    +"sha1_git": "8f427082e6bc1a7b420b27954d9853a11600422e",
    +"sha256": "e12a51eeeb0dd8555ae32f504dec0fdc845a710619f0056f616a4a6b9abe6aec",
    +"sha1": "f95c274b003999b705133bfcc0f495114e033bb9",
  }
```

- Check if a SWH Object, `Snapshot/Revision/Release/Directory/Content`, exists by its identifier.

| `Class static` Method                                                                                                                                                                                                                               | Method Arguments                                                                                 | Method `$options` (defaults)                                                                         | Returns                                                           | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | `HTTP` Method |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| <ul><li>`snapshotExists($snpID[,...$options])`</li><li>`revisionExists($revID[,...$options])`</li><li>`releaseExists($relID[,...$options])`</li><li>`directoryExists($dirID[,...$options])`</li><li>`contentExists($cntID[,...$options])`</li></ul> | `<string>: $snpID / $revID / $relID / $dirID / $cntID:`<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `stringType: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `String\|True`<br/>- `Throwable: RequestException \| Exception` | - [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-)<br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | HEAD          |


```php
> GraphHopping::snapshotExists('swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f177')  // as full snapshot ID

> GraphHopping::revisionExists('9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c')            // call on the 40-hexadecimal-string

> GraphHopping::releaseExists('swh:1:rel:1791be4df87a0d69008ba46c5a03be2e4cfbe3d5')

> GraphHopping::directoryExists('7f465824589e766653ca53ed7cd398260e24a5e8')

> GraphHopping::contentExists('swh:1:cnt:8f427082e6bc1a7b420b27954d9853a11600422e')
= true

    // String Response
> GraphHopping::snapshotExists('swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f177', stringType: true)
= "swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f177 --> Exists in SWH"
```

### V) GraphEdges

This class reveals all information about any SWH Object Edges (child nodes). This class is `abstract` with all its methods set as `static`, hence no object instantiation is necessary; all methods are accessible throughout as global functions. The class provide access to conveniently deal with SWH objects on explanatory naming.

> `Abstract GraphEdges::class`

Abstract class usage:

```php
> namespace Module\DAGModel;
> use Module\DAGModel; 

> GraphEdges::methodName();     // methodName() is prepended with the class name and two colons `::` 
```

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/35  |
|------------------|---|

> #### GraphEdges Methods:

- Resolve a `snapshot` node to one of its edges as given by the in-branch name (default: `main/master` or what `HEAD` points to). `Null` is returned if target doesn't exist.

  > This method follows pagination and searches the entire node contents for the requested child node.

| `Class static` Method                       | Method Arguments                                                                                                                                                          | Returns                                                                                  | `SWH` Endpoint                                                                                                       | `HTTP` Method |
|---------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|---------------|
| `getRevOrRelFromSnp($snpID[, $inBranches])` | - `<string> $snpID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `<mixed> inBranches:`<ul><li>`string: branch/release name`<li> `int: pull Request #`</ul> | - `SwhCoreID`<br/>- `Null`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-) | GET           |

> Without specifying a target edge, it will default to: `main/master` branch and utilises `HEAD` to locate default branch naming in the process.

```php
> GraphEdges::getRevOrRelFromSnp('swh:1:snp:6d8ff23b72ba0d450c6d6b5fc127f2b07cdc9abe')  
  
    // SWHCoreID dataType (object form)                                                                                      
= Modules\DataType\SwhCoreID {#6812
    +"rev": "swh:1:rev:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c",
  }
  
    // String form    
> GraphEdges::getRevOrRelFromSnp('swh:1:snp:6d8ff23b72ba0d450c6d6b5fc127f2b07cdc9abe')->getSwhid() 

= "swh:1:rev:9cf5bf02b583b93aa0d149cac1aa06ee4a4f655c"
```

> Specify a release name as target edge

```php
> GraphEdges::getRevOrRelFromSnp('6d8ff23b72ba0d450c6d6b5fc127f2b07cdc9abe', 'v6.1-rc7')     

= Modules\DataType\SwhCoreID {#7006
    +"rel": "swh:1:rel:1791be4df87a0d69008ba46c5a03be2e4cfbe3d5",
  }
```

> On non-existing branch/release, etc.

```php
> GraphEdges::getRevOrRelFromSnp('b8e164cfcf47da2323d5aef6e01fcd7f0c27f177', '1.0a36')       
= null 
```

> Specify a specific branch name in repository as target edge

```php
> GraphEdges::getRevOrRelFromSnp('swh:1:snp:d45df836d3d2793ffceac488140f56d8719875ac', '4th-branch/physics')     

= Modules\DataType\SwhCoreID {#6946
    +"rev": "swh:1:rev:d85dac0613eda6058f26006e3ed5693fc8ad21ad",
  } 
```
> Specify a pull request (int) as target edge

```php 
> GraphEdges::getRevOrRelFromSnp('b8e164cfcf47da2323d5aef6e01fcd7f0c27f177', 621)        

= Modules\DataType\SwhCoreID {#6886
    +"rev": "swh:1:rev:c8c154f725fd31eadb5d907463fa0efcd557786d",
  }
```

> `Exceptions` are `returned` rather than `thrown`, e.g. on non-existing identifiers `RequestException` is returned.

```php
> GraphEdges::getRevOrRelFromSnp('b8e164cfcf47da2323d5aef6e01fcd7f0c27f178')

= Illuminate\Http\Client\RequestException {#6806        // RequestException is returned
    #message: """
      HTTP request returned status code 404:\n
      {"exception":"NotFoundExc","reason":"Snapshot with id b8e164cfcf47da2323d5aef6e01fcd7f0c27f178 not found!"}\n
      """,
    #code: 404,
    #file: "...\faircore4eosc\vendor\laravel\framework\src\Illuminate\Http\Client\Response.php",
    #line: 272,
  }
    
    // Load Latest Errors on the HTTPClient base class
    
> HTTPClient::getErrors()   // Note: These errors are cleared out after each call to getErrors()
= [
    "2023-10-30 20:10:47 --> Non-Successful HTTP Status Code: 404 --> Reason: Snapshot with id b8e164cfcf47da2323d5aef6e01fcd7f0c27f178 not found!",
    "2023-10-30 20:10:47 --> 404 : Requested Revision or Release can not be found on this Snapshot: swh:1:snp:b8e164cfcf47da2323d5aef6e01fcd7f0c27f178",
  ]
```

- Resolve a `release` node to its direct edge (`rev/rel`). As per SWH docs, `Release` nodes can branch into another `release` or directly to `revision` nodes.

    > If `revID` is eventually sought, then traversing (see `GraphTraversal` class) to the `revision` node is relevant instead, `traverseFromRelToRev()`.  

| `Class static` Method        | Method Arguments                                           | Returns                                                                     | `SWH` Endpoint                                                                                                  | `HTTP` Method |
|------------------------------|------------------------------------------------------------|-----------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|---------------|
| `getRevOrRelFromRel($relID)` | `<string> $relID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-) | GET           |

```php
> GraphEdges::getRevOrRelFromRel('73c638e86af20a4d3ef1d2eb2be5892a841b233f')

= Modules\DataType\SwhCoreID {#6504                            
    +"rev": "swh:1:rev:699640f64a89eb90b470a9d536efbb1ace5cc9ec",
  } 
     
> GraphEdges::getRevOrRelFromRel('73c638e86af20a4d3ef1d2eb2be5892a841b233f')->getInitials()      // gets which node type is returned
= "rev"
```

- Resolve a `revision` node to its root directory node (`dirID`).

| `Class static` Method       | Method Arguments                                           | Returns                                                                     | `SWH` Endpoint                                                                                                    | `HTTP` Method |
|-----------------------------|------------------------------------------------------------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|---------------|
| `getRootDirFromRev($revID)` | `<string> $revID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-) | GET           |

```php
> GraphEdges::getRootDirFromRev('swh:1:rev:699640f64a89eb90b470a9d536efbb1ace5cc9ec')

= Modules\DataType\SwhCoreID {#6504
    +"dir": "swh:1:dir:2f987353e99c0ad90ab0de0b5cf9fbbf7f0cd34c",
  }
```

- Resolve a `directory` node to its direct edge (`dir/cnt`) given a specific name for the directory/content. `Null` is returned if target edge doesn't exist.

| `Class static` Method                         | Method Arguments                                                                                                                          | Returns                                                                                 | `SWH` Endpoint                                                                                                               | `HTTP` Method |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getNextNodeFromDir($dirID, $seekEdgeTarget)` | - `<string> $dirID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul><br/>- `<string> $seekEdgeTarget:`<br/> `file/subdirectory name` | - `SwhCoreID`<br/>-`Null`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) | GET           |

> Seek 'setup.py' file given a SWHID of its parent directory

```php
GraphEdges::getNextNodeFromDir('swh:1:dir:2f987353e99c0ad90ab0de0b5cf9fbbf7f0cd34c', 'setup.py')     

= Modules\DataType\SwhCoreID {#6504
    +"cnt": "swh:1:cnt:8758e3dc21918c563369498c75f98eef100316d2",
  }
```

> Seek 'crypto' subdirectory given a SWHID of its parent directory
 
```php
> GraphEdges::getNextNodeFromDir('swh:1:dir:919fc51c26a8b5f57b3c89f6a62d0f3bb1bdfd2c', 'crypto')        

= Modules\DataType\SwhCoreID {#6745
    +"dir": "swh:1:dir:f272adcb6d2adc96dde0bf968bd30c66d2935a37",
  }
```

> Non-existing target in some directory 

```php
> GraphEdges::getNextNodeFromDir('swh:1:dir:f272adcb6d2adc96dde0bf968bd30c66d2935a37', 'non-existing')   
= null
```

- Get all edges of a `snapshot` node keyed by the respective name of children nodes (`tags/pulls/features/branches`).

    > This method follows pagination to build entire snapshot node edges.

| `Class` Method                           | Method Arguments                                           | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                       | `HTTP` Method |
|------------------------------------------|------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|---------------|
| `getSnapshotEdges($snpID[,...$options])` | `<string> $snpID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-) | GET           |

```php
> HTTPClient::$responseType = 'collect'         // prepare results in Collection format

> $snpID = (new SwhVisits('https://github.com/torvalds/linux/'))        // define a new visitObject for this repository
            ->getSnpFromVisit('latest')                                 // get its latest snapshot
            ->getSwhid()                                                // get its snpID from SwhCoreID (Data Type)

> GraphEdges::getSnapshotEdges($snpID)

= Illuminate\Support\Collection {#6939
    all: [
      "refs/heads/master" => "swh:1:rev:ce55c22ec8b223a90ff3e084d842f73cfba35588",
      ⋮
      "refs/pull/296/head" => "swh:1:rev:e32d8d64fc1f78613cf5c946e405738c794d066d",
      ⋮
      "refs/tags/v3.1-rc10" => "swh:1:rel:bc9dac81d1d3442713e5b91ed7cda1646df9730e",
      ⋮ 
    ]
  }

> GraphEdges::getSnapshotEdges($snpID)->count()      // number of returned snapshot edges
= 1590
```

- Get all edges of a `revision` node keyed by the respective name of children nodes (`root-dir/parents-revisions`).

| `Class` Method                           | Method Arguments                                           | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                    | `HTTP` Method |
|------------------------------------------|------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|---------------|
| `getRevisionEdges($revID[,...$options])` | `<string> $revID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-) | GET           |

```php
> GraphEdges::getRevisionEdges('swh:1:rev:ce55c22ec8b223a90ff3e084d842f73cfba35588')

= [
    "directory" => "swh:1:dir:919fc51c26a8b5f57b3c89f6a62d0f3bb1bdfd2c",
    "parents" => [
      "swh:1:rev:74e9347ebc5be452935fe4f3eddb150aa5a6f4fe",
      "swh:1:rev:524515020f2552759a7ef1c9d03e7dac9b1ff3c2",
    ],
  ]
```

- Get all edges of a `release` node keyed by the respective name of the child node (`rev/rel`).

| `Class` Method                          | Method Arguments                                           | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                  | `HTTP` Method |
|-----------------------------------------|------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|---------------|
| `getReleaseEdges($relID[,...$options])` | `<string> $relID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-) | GET           |

```php
> GraphEdges::getReleaseEdges('swh:1:rel:73c638e86af20a4d3ef1d2eb2be5892a841b233f')

= [
    "1.0a4" => "swh:1:rev:699640f64a89eb90b470a9d536efbb1ace5cc9ec",
  ]
```

- Get all edges of a `directory` node keyed by the respective name of children nodes (`contents/subdirectories`).

| `Class` Method                            | Method Arguments                                           | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                               | `HTTP` Method |
|-------------------------------------------|------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getDirectoryEdges($dirID[,...$options])` | `<string> $dirID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) | GET           |

```php
> GraphEdges::getDirectoryEdges('919fc51c26a8b5f57b3c89f6a62d0f3bb1bdfd2c')

= [
    ".clang-format" => "swh:1:cnt:0bbb1991defead96a8beb762f692c4ed229b1f20",
    ⋮
    "drivers" => "swh:1:dir:a05ace70d3ae9f2d09bc0bdf37a574107fcaf9a9",
    ⋮
  ]
```


### VI) GraphTraversal

This class allows the traversal to any child node from a parent node (i.e. SWH objects). This class is `abstract` with all its methods set as `static`, hence no object instantiation is necessary; all methods are accessible throughout as global functions. The class provide access to conveniently deal with SWH objects on explanatory naming.

> `Abstract GraphTraversal::class`

Abstract class usage:

```php
> namespace Module\DAGModel;
> use Module\DAGModel; 

> GraphTraversal::methodName();     // methodName() is prepended with the class name and two colons `::` 
```

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/64 |
|-------------------|----------------------------------------------------------------|

> #### GraphTraversal Methods:

- Traverse from `snapshot` node to any child node, `revision`, `release (resolved to its revision)`, `directory`, `content` specified by an `Array` of  `Queues` for target nodes.
 
    > This method resolves the children nodes directly to their nodeIDs (`SWHIDs/SwhObjects`).
    > 
    > This method amends automatically the `branchName` key if part of the `branch` name is appended to the `path` key instead. i.e. the path queue pushes entries to the branch queue on demand.

| `Class static` Method                     | Method Arguments                                                                                                                                                                                                                   | Returns                                                                             | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | `HTTP` Method |
|-------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `traverserFromSnp($snpID[, $nodeQueues])` | -`<SWHCoreID>: $snpID: full SWHID (new SWHCoreID($snpID))`<br/> - `<array>: nodeQueues: array of two Queues`<ul><li>`$nodeQueues['branchName']: queue of branch names`</li><li>`nodeQueues['path']: queue of path nodes`</li></ul> | - `stdClass (object)`<br/>- `Throwable: RequestException \| TypeError \| Exception` | - [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

> Define method arguments (node names to traverse to):

```php
> use Ds\Queue;

> $snpID = (new SwhVisits('https://github.com/RamyTestAccount/D2'))->getSnpFromVisit('latest')

= Modules\DataType\SwhCoreID {#6545
    +"snp": "swh:1:snp:d45df836d3d2793ffceac488140f56d8719875ac",
  }

    // define branch queue (if in doubt, place any trailing entry to the path queue)

> $nodeQueues['branchName'] = new Queue(['5th-branch', 'maths', 'dev'])     
= Ds\Queue {#6945
    0: "5th-branch",
    1: "maths",
    2: "dev",
    count: 3,
    capacity: 8,
    +0: "5th-branch",
    +1: "maths",
    +2: "dev",
  }

> $nodeQueues['path'] = new Queue(['Matlab', 'Rosenbrock.m'])           // define the path nodes
= Ds\Queue {#7026
    0: "Matlab",
    1: "Rosenbrock.m",
    count: 2,
    capacity: 8,
    +0: "Matlab",
    +1: "Rosenbrock.m",
  }
```

> Traverse from the `snpashot` node to the `revision` whose name is `5th-branch/maths/dev` and to the `content` node whose relative path (from root dir) is `Matlab/RosenBrock.m` 

```php
> GraphTraversal::traverseFromSnp($snpID, $nodeQueues)

= {#7018
    +"snp": "swh:1:snp:d45df836d3d2793ffceac488140f56d8719875ac",
    +"rev": "swh:1:rev:6b3f8635f87b9cca6bd3bad660da973ab8790094",
    +"cnt": "swh:1:cnt:5de355c5291614aa07120e4e77aa928c6168e8ce",
  }
```

> Traverse from the `snpashot` node to the defaults (default branch, its root directory)

```php
> GraphTraversal::traverseFromSnp($snpID)        // drop out the nodeQueues argument

= {#6981
    +"snp": "swh:1:snp:d45df836d3d2793ffceac488140f56d8719875ac",
    +"rev": "swh:1:rev:0b719b2b5a93c2c06335e6afc6c8af145aa6444d",
    +"dir": "swh:1:dir:9b82760ee153c3374d4fc88b3ede355e039452ee",
  }
```

- Traverse from `revision` node to a child node (`directory/content`) specified by its path relative the root directory (i.e. direct `revision` edge).

    > This method resolves the child node directly to its nodeID (`SWHID/SwhObject`).

| `Class static` Method             | Method Arguments                                                                                                                                           | Returns                                                                     | `SWH` Endpoints                                                                                                                       | `HTTP` Method |
|-----------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `traverserFromRev($revID, $path)` | - `<string> $revID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $path`<br/>`(root-relative)`<ul><li>`(sub)directory`<li> `content`</ul> | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) | GET           |


> As an example: we can start from the latest `snapshot` of a repository to reach an example `revision`:

```php
> namespace Module\OriginVisits;
> use Module\OriginVisits; 

> $latestSnpID = (new SwhVisits('https://gitlab.mis.mpg.de/rok/mathrepo'))
                ->getSnpFromVisit('latest')
                ->getSwhid()

> $revID = (new GraphNode($latestSnpID))
          ->nodeTargetEdge('refs/heads/master')
          ->getSwhid()
```

> Then traverse to the following children nodes (of the `revision` node) from `revID`:

```php
> GraphTraversal::traverseFromRev($revID, 'source/GibbsManifolds/numerical_implicitization.jl')      // traverse from $revID to content child node

= Modules\DataType\SwhCoreID {#6629
    +"cnt": "swh:1:cnt:2071ba0e9f5a7a39597ef5d7009d091be368fba9",
  }

> GraphTraversal::traverseFromRev($revID, 'source/EulerIntegrals')   // traverse from $revID to directory child node

= Modules\DataType\SwhCoreID {#6703
    +"dir": "swh:1:dir:0c9888d32fdd676684c17faee1cc20a89d8de822",
  }

> GraphTraversal::traverseFromRev($revID, '.')       // `dot` implies root directory as traversal target

= Modules\DataType\SwhCoreID {#6584
    +"dir": "swh:1:dir:f6e88604f4cbf8ac027131fbc9d11032bba1489b",
  }
```

- Traverse from `revision` node to a child node (`directory/content`) specified by its path relative the root directory (direct `revision` edge).

  > This method retrieves the full child node (akin to node Hopping).

| `Class static` Method                             | Method Arguments                                                                                                                                           | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                       | `HTTP` Method |
|---------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullNodeFromRev($revID, $path[,...$options])` | - `<string> $revID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $path`<br/>`(root-relative)`<ul><li>`(sub)directory`<li> `content`</ul> | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) | GET           |

```php
> GraphTraversal::getFullNodeFromRev('badb581c17240cc57f6efe5edbe9ffe06f8e724e', 'source/Landau/LandauTutorial.ipynb')     
= [
    "type" => "file",
    ⋮
 ]
```

- Traverse from `release` node to the `revision` child node.

  > This method resolves the `release` node directly to the `revision` it finally points (can track multiple releases till its final `revision` node).
  
| `Class static` Method          | Method Arguments                                           | Returns                                                                     | `SWH` Endpoint                                                                                                  | `HTTP` Method |
|--------------------------------|------------------------------------------------------------|-----------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|---------------|
| `traverseFromRelToRev($relID)` | `<string> $relID:`<br/>- `40-hex-chars`<br/>- `full SWHID` | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-) | GET           |

```php
> GraphTraversal::traverseFromRelToRev('swh:1:rel:73c638e86af20a4d3ef1d2eb2be5892a841b233f')

= Modules\DataType\SwhCoreID {#6499                            
    +"rev": "swh:1:rev:699640f64a89eb90b470a9d536efbb1ace5cc9ec",
  } 
```

- Traverse from `directory` node to a child node (`subdirectory/content`) specified by its path relative the root directory.

  > This method resolves the child node directly to its nodeID (`SWHID/SwhObject`).

| `Class static` Method             | Method Arguments                                                                                                                                         | Returns                                                                     | `SWH` Endpoint                                                                                                                 | `HTTP` Method |
|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|---------------|
| `traverserFromDir($dirID, $path)` | - `<string> $dirID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $path`<br/>`(root-relative)`<ul><li>`subdirectory`<li> `content`</ul> | - `SwhCoreID`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-])   | GET           |


> As an example: start from the latest `snapshot` of a repository to get an example of some `root dir`:

```php
> namespace Module\OriginVisits;
> use Module\OriginVisits; 

> $latestSnpID = (new SwhVisits('https://github.com/matlab2tikz/matlab2tikz'))
                ->getSnpFromVisit('latest')
                ->getSwhid()
                
> $dirID = GraphEdges::getRootDirFromRev(GraphEdges::getRevOrRelFromSnp($latestSnpID)->getSwhid())->getSwhid()

= "swh:1:dir:7df73b1b1d85595601529cbcf9661e46f4062ce1"          // root directory to traverse to children nodes from.
```

> Then traverse to the following children nodes (of `directory` node) from `dirID`:

```php
> GraphTraversal::traverseFromDir($dirID, 'src/private/isAxis3D.m')      // traverse from $dirID to content child node

= Modules\DataType\SwhCoreID {#6883
    +"cnt": "swh:1:cnt:084d7a8ccb9d24b0aa00024c5860f816f0c72290",
  }

> GraphTraversal::traverseFromDir($dirID, 'test/suites')                 // traverse from $dirID to directory child node

= Modules\DataType\SwhCoreID {#6990
    +"dir": "swh:1:dir:9e991d377691ec83cf108f1740b8ee8bf3d7e87a",
  }
```

- Traverse from `directory` node to a child node (`subdirectory/content`) specified by its path relative the root directory.

  > This method retrieves the full child node data which bundles all `directory` contents under `target` ID (from which such directory contents can be further expanded).

| `Class static` Method                             | Method Arguments                                                                                                                                         | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                               | `HTTP` Method |
|---------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullNodeFromDir($dirID, $path[,...$options])` | - `<string> $dirID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $path`<br/>`(root-relative)`<ul><li>`subdirectory`<li> `content`</ul> | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) | GET           |

```php
> GraphTraversal::getFullNodeFromDir('7df73b1b1d85595601529cbcf9661e46f4062ce1', 'test/suites/private')
   
= [
    "dir_id" => "9e991d377691ec83cf108f1740b8ee8bf3d7e87a",     
    ⋮
    "target" => "2e96ed4392756a0def4b23bcd32c8f50cb147328",     // Note: usable further to hopp on this directory `content`
    ⋮
 ]
```

- Traverse from `snapshot` to historical commit (from revisions log) given the sha1 for the commit. It returns the commit hash as `revision` ID if exists, else returns `Null`.

  > This method follows `snapshot` pagination and interacts with the BFS traversal on the revision graph.

| `Class static` Method                         | Method Arguments                                                                                                        | Returns                                                                                  | `SWH` Endpoint                                                                                                                                                                                           | `HTTP` Method |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `traverseRevLogFromSnp($snpID, $commitHash])` | - `<string> $snpID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $commitHash:`<ul><li>`sha1_git`</ul> | - `SwhCoreID`<br/>- `Null`<br/>- `Throwable: RequestException \| TypeError \| Exception` | - [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/> - [`revision`](https://archive.softwareheritage.org/api/1/revision/log/doc/) | GET           |


```php
> GraphTraversal::traverseRevLogFromSnp('swh:1:snp:52431e12c648fa759c6e9ee0c20f000e59f7ed8b', '7c16ea809982b22016308ba085f28b1a1441be21')     // search by sha1_git and return it as SWHID if found

= Modules\DataType\SwhCoreID {#6746
    +"rev": "swh:1:rev:7c16ea809982b22016308ba085f28b1a1441be21",
  }

> GraphTraversal::traverseRevLogFromSnp('swh:1:snp:52431e12c648fa759c6e9ee0c20f000e59f7ed8b', 'ec16ea809982b22016308ca085f28b1a1441be21')      // non-existing commit
= null
```

- Traverse from `revision` to historical commit (from revisions log) given the sha1 for the commit. It returns the commit hash as `revision` ID if exists, else returns `Null`.

  > This method interacts with the BFS traversal on the revision graph.

| `Class static` Method                         | Method Arguments                                                                                                        | Returns                                                                                  | `SWH` Endpoint                                                               | `HTTP` Method |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|---------------|
| `traverseRevLogFromRev($revID, $commitHash])` | - `<string> $revID:`<ul><li>`40-hex-chars`</li><li>`full SWHID`</li></ul>- `string $commitHash:`<ul><li>`sha1_git`</ul> | - `SwhCoreID`<br/>- `Null`<br/>- `Throwable: RequestException \| TypeError \| Exception` | [`revision`](https://archive.softwareheritage.org/api/1/revision/log/doc/)   | GET           |

```php
> GraphTraversal::traverseRevLogFromRev('swh:1:rev:7c16ea809982b22016308ba085f28b1a1441be21', '90076d49e031f532eb9b70b30ecfb2f395983bbf')    // search by sha1_git and return it as SWHID if found

= Modules\DataType\SwhCoreID {#6562
    +"rev": "swh:1:rev:90076d49e031f532eb9b70b30ecfb2f395983bbf",
  }
```

### VII) Archive

This class allows for repositories archival, tracking archival status and eventually builds the graph nodes that corresponds to the given SW origin. The class also allows through the fluent `of()` method for retrieving archival data as stored in SWH.

On object instantiation, the given SW origin is processed to:

- detect visit type 
- build the paths that will be propagated to inside the graph model as an array of queues.
- throw errors on non-supported repositories or visit types respectively.
- initialises an `Archivable` object to help retrieve additional archival information for the given SW origin as stored in SWH. Done via the `static` method `of()` as will be shown.

> Currently, supported repositions: `GitHub`, `GitLab`, and `BitBucket`
>
> Currently, supported visit types: `git`


> `new Archive($url[, $visitType[, ...$options]])`
>>- `Extends: syncHTTP`
>>>- `Extends: HTTPClient`
>>- `$url: <string>` <font size='2'>the origin url</font>
>>- `$visitType: <string>` <font size='2'>the origin visit type (optional, can be omitted)</font>
>>- `...$options: named parameters` [Configs](https://github.com/dagstuhl-publishing/beta-faircore4eosc/edit/main/app/Modules/SwhApi/README.md#preset-configurations)
>>- `Throws Exception|UnhandledMatchError` <font size='2'>(On non-supported visit types | on non-supported repository type) respectively</font> 
>>- `Implements SwhArchive`

This class `Implements` the `SwhArchive Interface` which comprises the following functionality for any archival request:

| Method                       | Notes                                                                     |
|------------------------------|---------------------------------------------------------------------------|
| `save2Swh()|repository()`    | Submit an archival request for a given SW origin.                         |
| `getArchivalStatus()`        | retrieve current status data of an archival request.                      |
| `trackArchivalStatus()`      | Continuously requests status data of an archival request till success.    |
| `getLatestArchivalAttempt()` | retrieves the data of the latest archival attempt for a given SW origin.  |
| `getSnpFromSaveRequest()`    | retrieve the root node (`snapshot`) of a any successful archival attempt. |


| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/10 |
|-------------------|----------------------------------------------------------------|

Instantiate archive objects for desired repositories w/o paths in their URL: 

```php
> namespace Module\Archival;
> use Module\Archival; 

    // Example 1
> $archiveObject = new Archive('https://github.com/torvalds/linux/')

= Module\Archival\Archive {#6696
    +decomposedURL: [
      "scheme" => "https",
      "host" => "github.com",
      "path" => "/torvalds/linux",
    ],
    +nodeHits: [],              // empty graph nodes to propagate to, i.e. this origin is a base repository
    +url: "https://github.com/torvalds/linux/",
    +visitType: "git",
  }

    // Example 2
> $archiveObject = new Archive('https://github.com/hylang/hy/tree/stable/hy/core')

= Module\Archival\Archive {#6702
    +decomposedURL: [
      "scheme" => "https",
      "host" => "github.com",
      "path" => "/hylang/hy/tree/stable/hy/core",
    ],
    +nodeHits: [                        // two initial queues representing the graph nodes
      "branchName" => Ds\Queue {#6674   // branch queue will be automatically amended on non-existing entries by the path queue
        0: "stable",
        count: 1,
        capacity: 8,
        +0: "stable",
      },
      "path" => Ds\Queue {#6705        // path queue will pop entries and push them to branch queue on demand until it has been exhausted 
        0: "hy",
        1: "core",
        count: 2,
        capacity: 8,
        +0: "hy",
        +1: "core",
      },
    ],
    +url: "https://github.com/hylang/hy",
    +visitType: "git",
  }
```

> #### Archive Methods:

- Submit an archival request to SWH for the defined SW origin and receive the first status response accordingly. 

    > There are two variants for this method.

> I) Non-static save method:    

| `Class static` Method     | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                                       | `HTTP` Method |
|---------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `save2Swh([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#post--api-1-origin-save-(visit_type)-url-(origin_url)-) | POST          |

```php
> $archiveObject = new Archive('https://github.com/hylang/hy/tree/stable/hy/core')    // base repository with paths

> $archiveObject->save2Swh()   

= [
    "id" => 823148,
    "origin_url" => "https://github.com/hylang/hy",
    ⋮
    "save_request_date" => "2023-11-11T23:09:30.263581+00:00",
    "save_request_status" => "accepted",
    "save_task_status" => "not yet scheduled",
     ⋮
    "snapshot_swhid" => null,
    ⋮
  ]    
```

> II) Static save method:


| `Class static` Method                         | Method `$options` (defaults)                                                                                                                    | Returns                                                                             | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | `HTTP` Method |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `repository($url[,$visitType[,...$options]])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)`<br/>- `withTracking: NULL \| bool (Null)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#post--api-1-origin-save-(visit_type)-url-(origin_url)-) </br>- [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-)<br/>- [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | POST[, GET]   |

> Allow verbose logging (optional for debugging):

```php    
> namespace Module\HTTPConnector;
> use Module\HTTPConnector;
 
> HTTPClient::setOptions(isVerbose: true)
```

> Call the static method variant:
> 
```php
> namespace Module\Archival;
> use Module\Archival; 

> Archive::repository('https://github.com/openssl/openssl', withTracking: true)    

    ⋮
    Request Status --> accepted
    Task Status --> pending
    Visit Status -->
    ⋮

        // Final result:
= [
    "id" => 1166754,
    "origin_url" => "https://github.com/openssl/openssl",
    ⋮
    "save_request_date" => "2024-02-23T15:07:33.969286+00:00",
    "save_request_status" => "accepted",
    "save_task_status" => "succeeded",
     ⋮
    "snapshot_swhid" => "swh:1:snp:5c45e055a5eccb7eb369e4fe325fa9277c96b1bd",
    ⋮
  ]  
```

- Whilst archiving, retrieve current status data of the archival request per its date or identifier.

  > If at the time of retrieval the archival has been finished: 
  > 
  > - This method automatically propagates the detected `nodeHits`.
  >
  > - This method will show a built list of SwhIDs (keyed: `swh_id_list`) as well as contextual IDs (keyed `contextual_swh_ids`).
  >
  > - Due to potential delays on the server-side, the `save_task_status` may return `succeeded` in the response data while `snapshot_swhid` remains null (has not been timely generated).

| `Class static` Method                                   | Method Arguments                                                                                  | Method `$options` (defaults)                                                                          | Returns                                                                                         | `SWH` Endpoints                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | `HTTP` Method |
|---------------------------------------------------------|---------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getArchivalStatus($saveRequestDateOrID[,...$options])` | `<int>\|<string> $saveRequestDateOrID`<br/>- `<int>: saveID`<br/>- `<string>: ISO-formatted Date` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| TypeError \|Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-)<br/>- [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

```php
> $archiveObject->getArchivalStatus(823148)

= [
    "id" => 823148,
    ⋮
    "save_request_status" => "accepted",
    "save_task_status" => "running",
    "visit_status" => "created",
    "visit_date" => "2023-11-11T23:09:36.218049+00:00",
    ⋮
    "snapshot_swhid" => null,
    ⋮
  ]
```

- Continuously request status data of an archival request till archival has been finished (i.e. `save_task_status` returns `succeeded`).

    > This method tracks the archival progress and automatically propagates the detected `nodeHits` after successful archival.
    >
    > This method builds a list of SwhIDs (keyed: `swh_id_list`) as well as contextual IDs (keyed `contextual_swh_ids`) in its final output.
    >
    > Due to potential delays on the server-side, the `save_task_status` may return `succeeded` in the response data while `snapshot_swhid` remains null (has not been timely generated), in which case the tracking continues further.   

| `Class static` Method                                     | Method Arguments                                                                                  | Method `$options` (defaults)                                                                          | Returns                                                                                         | `SWH` Endpoints                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | `HTTP` Method |
|-----------------------------------------------------------|---------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `trackArchivalStatus($saveRequestDateOrID[,...$options])` | `<int>\|<string> $saveRequestDateOrID`<br/>- `<int>: saveID`<br/>- `<string>: ISO-formatted Date` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| TypeError \|Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) <br/>- [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::setOptions(isVerbose: true, fileDatestamp: true)

> $archiveObject->trackArchivalStatus(823114)

    // logs from the timestamped-file (under storage/logs):
⋮    
[2023-11-11 23:09:32] local.INFO: Done --> false
⋮
[2023-11-11 23:09:36] local.INFO: Done --> false
⋮
[2023-11-11 23:09:49] local.INFO: Done --> true

= [
    "id" => 823148,
    ⋮
    "save_task_status" => "succeeded",
    "visit_status" => "full",
    "visit_date" => "2023-11-11T23:09:41.587692+00:00",
    ⋮
    "snapshot_swhid" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
    ⋮
    "swh_id_list" => [
      "ori" => "swh:1:ori:7bed762bf5ddc7164a08dac613ead782784474bb",
      "snp" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",        // snapshotID (root node) from this archival request
      "rev" => "swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d",        // revisionID of the branch `stable`
      "dir" => "swh:1:dir:7f40a3d0904eeb0f754b98528239ff7036a46aa9",        // directoryID of the subdirectory `hy/core` relative to the root dir
    ],
    "contextual_swh_ids" => [
      "Directory-Context" => "swh:1:dir:7f40a3d0904eeb0f754b98528239ff7036a46aa9;origin=https://github.com/hylang/hy;visit=swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714;anchor=swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d",
      "Revision-Context" => "swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d;origin=https://github.com/hylang/hy;visit=swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
      "Snapshot-Context" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714;origin=https://github.com/hylang/hy",
    ],
  ]
```

- Retrieve the data of the latest archival attempt for a given SW origin in SWH (regardless to a self-submitted archival request)

    > This method automatically propagates the detected `nodeHits`.
    > 
    > This method builds a list of SwhIDs (keyed: `swh_id_list`) as well as contextual IDs (keyed `contextual_swh_ids`) in its final output.

| `Class static` Method                    | Method `$options` (defaults)                                                                          | Returns                                                                                         | `SWH` Endpoints                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | `HTTP` Method |
|------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getLatestArchivalAttempt[...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| TypeError \|Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) <br/>- [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-)<br/>- [`snapshot`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-snapshot-(snapshot_id)-)<br/>- [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-directory-[(path)-]) <br/> - [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-)<br/>- [`directory`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-directory-(sha1_git)-[(path)-]) <br/> - [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) <br/> - [`visit`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-visit-(visit_id)-) <br/>- [`visit`](https://archive.softwareheritage.org/api/1/origin/visit/latest/doc/) | GET           |

```php
> $archiveObject->getLatestArchivalAttempt()        // get latest data of the last archival request made for this repository

= [
    "id" => 823189,                                 // latest saveID for this repository archival
    "origin_url" => "https://github.com/hylang/hy",
    ⋮
    "save_request_date" => "2023-11-11T23:28:43.560508+00:00",  // latest date
    ⋮
    "save_task_status" => "succeeded",
    "visit_status" => "full",
    "visit_date" => "2023-11-11T23:28:50.420346+00:00",         // latest visit date
    ⋮
    "snapshot_swhid" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
    ⋮
    "swh_id_list" => [                                                      // latest list reflecting the repository paths (can be further compared to previously saved entries)
      "ori" => "swh:1:ori:7bed762bf5ddc7164a08dac613ead782784474bb",
      "snp" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
      "rev" => "swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d",
      "dir" => "swh:1:dir:7f40a3d0904eeb0f754b98528239ff7036a46aa9",
    ],
    "contextual_swh_ids" => [
      "Directory-Context" => "swh:1:dir:7f40a3d0904eeb0f754b98528239ff7036a46aa9;origin=https://github.com/hylang/hy;visit=swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714;anchor=swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d",
      "Revision-Context" => "swh:1:rev:4a2712d84b2c7f38a91495bf7708de51a05bb65d;origin=https://github.com/hylang/hy;visit=swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
      "Snapshot-Context" => "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714;origin=https://github.com/hylang/hy",
    ],
  ]
```

- Retrieve the root node (`snapshot`) of any successful archival attempt per its date or save request identifier.

    > This method resolves the latest archival attempt to the root node directly.

| `Class static` Method                         | Method Arguments                                                                                  | Returns                                                                                                                          | `SWH` Endpoint                                                                                                                                        | `HTTP` Method |
|-----------------------------------------------|---------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getSnpFromSaveRequest($saveRequestDateOrID)` | `<int>\|<string> $saveRequestDateOrID`<br/>- `<int>: saveID`<br/>- `<string>: ISO-formatted Date` | - `SwhCoreID`<br/>- `Null (on non-existing $saveRequestDateOrID) `<br/>- `Throwable: RequestException \| TypeError \| Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) | GET           |

```php
> $archiveObject->getSnpFromSaveRequest(823189)

    // SWHCoreID dataType (object form)  
= Modules\DataType\SwhCoreID {#6531
    +"snp": "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714",
  }
        // string format       
> $archiveObject->getSnpFromSaveRequest(823189)->snp

= "swh:1:snp:a941df442c25529a69f927d61b5df21a5c1f7714"
```

> ##### Accessing Archive requests data through the fluent `static of()` method:

```php
> Archive::of($url)->methodName();     // methodName() are the following accessible methods: 
```
> [!NOTE]
> The `of()` method may take repositories w/o paths. However, the following accessible functions will only consider the base repository for the server-side interaction with SWH. 

- Get full data of all archival attempts by SWH for an origin.

| `Class` Method                  | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                                      | `HTTP` Method |
|---------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getAllArchives([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) | GET           |

```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::setOptions(responseType: 'collect')

> Archive::of('https://github.com/nodejs/node/tree/main/deps/npm')->getAllArchives()    

= Illuminate\Support\Collection {#6691
    all: [
      [
        "id" => 770071,
        ⋮
        "visit_date" => "2023-10-16T11:57:23.013561+00:00",
        ⋮
        "snapshot_swhid" => "swh:1:snp:9c98b475b46058ac5823065c4bf107cf0bcf8c1e",
      ],
      ⋮
    ],
  }

> Archive::of('https://github.com/RamyTestAccount/D2/')->getAllArchives()->count()    // number of archival attempts by SWH
= 464
```

- Show full archival attempts' data that resulted in distinct snapshots.

| `Class` Method                        | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                                                      | `HTTP` Method |
|---------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `showDistinctArchives([...$options])` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) | GET           |

```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::setOptions(responseType: 'collect')

> Archive::of('https://github.com/torvalds/linux')->showDistinctArchives()      // will show archival data with unique snapshots

= [
    [
      "id" => 823146,
      ⋮
      "save_request_date" => "2023-11-11T23:05:12.983694+00:00",
      ⋮
      "snapshot_swhid" => "swh:1:snp:04b13a085a9609f3c221c2857b33a393b87cdfa3",
      ⋮
    ]
    ⋮
 ]

> Archive::of('https://github.com/torvalds/linux')->showDistinctArchives()->count()
= 28

> Archive::of('https://github.com/torvalds/linux')->getAllArchives()->count()
= 40
```

- Generate all graph root nodes (`snapshots`) keyed by the corresponding archival timestamp (`save_request_date`).

| `Class` Method                               | Method `$options` (defaults)                                                                                                         | Returns                                                                             | `SWH` Endpoint                                                                                                                                      | `HTTP` Method |
|----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getAllSnapshotsFromArchives([...$options])` | Named Parameters:<br/>- `distinct: bool (false)`<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) | GET           |

```php
> Archive::of('https://github.com/nodejs/node/tree/main/deps/npm')->getAllSnapshotsFromArchives()

= [
    "2023-10-16T11:56:54.314065+00:00" => "swh:1:snp:9c98b475b46058ac5823065c4bf107cf0bcf8c1e",
    "2023-10-16T10:40:22.228213+00:00" => "swh:1:snp:9c98b475b46058ac5823065c4bf107cf0bcf8c1e",
    "2023-10-03T22:56:19.360179+00:00" => "swh:1:snp:e25efbea2b451b6ace211f8aaa2829cbb2a9f0ce",
    ⋮
    "2020-11-08T15:33:10.003000+00:00" => "swh:1:snp:763420cde99c884aeb2d9b37d60873ed657f1179",

]

> Archive::of('https://github.com/nodejs/node/tree/main/deps/npm')->getAllSnapshotsFromArchives(distinct: true)

= [
    "2023-10-16T11:56:54.314065+00:00" => "swh:1:snp:9c98b475b46058ac5823065c4bf107cf0bcf8c1e",
    "2023-10-03T22:56:19.360179+00:00" => "swh:1:snp:e25efbea2b451b6ace211f8aaa2829cbb2a9f0ce",
    ⋮
    "2020-11-08T15:33:10.003000+00:00" => "swh:1:snp:763420cde99c884aeb2d9b37d60873ed657f1179",
]
```

- Get a specific archival attempt's full data given its date, `save_request_date`, its identifier.

| `Class` Method                                               | Method Arguments                                                                                  | Method `$options` (defaults)                                                                                                         | Returns                                                                             | `SWH` Endpoint                                                                                                                                      | `HTTP` Method |
|--------------------------------------------------------------|---------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullArchivalRequest($saveRequestDateOrID[,...$options])` | `<int>\|<string> $saveRequestDateOrID`<br/>- `<int>: saveID`<br/>- `<string>: ISO-formatted Date` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `requireSnapshot: bool` <br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(visit_type)-url-(origin_url)-) | GET           |

```php
> Archive::of('https://github.com/torvalds/linux')->getFullArchivalRequest("2019-08-25T13:32:01.314000+00:00")

= [
    "id" => 4236,
    ⋮
    "save_request_date" => "2019-08-25T13:32:01.314000+00:00",
    ⋮
    "snapshot_swhid" => "swh:1:snp:eb8087624d47f6e8ee89692df041b2f568fb0e5f",
    ⋮
 ]    

> Archive::of('https://github.com/torvalds/linux')->getFullArchivalRequest(12033)

= [
    "id" => 4236,
    ⋮
    "save_request_date" => "2020-09-21T15:56:43.145000+00:00",
    ⋮
    "visit_status" => "partial",
    ⋮
    "snapshot_swhid" => null,
    ⋮
 ]
```

- Retrieve the root node (`snapshot`) of any successful archival attempt per its save request identifier.

  > This method resolves the latest archival attempt to the root node directly using the `save` endpoint with identifier.

| `Class static` Method                     | Method Arguments                 | Returns                                                                                                                    | `SWH` Endpoint                                                                                                                       | `HTTP` Method |
|-------------------------------------------|----------------------------------|----------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getSnpFromSaveRequestID($saveRequestID)` | `<int> $saveRequestID`: `saveID` | - `SwhCoreID`<br/>- `Null (on non-existing $saveRequestID) `<br/>- `Throwable: RequestException \| TypeError \| Exception` | - [`save`](https://docs.softwareheritage.org/devel/apidoc/swh.web.save_code_now.api_views.html#get--api-1-origin-save-(request_id)-) | GET           |

```php
> Archive::of('https://github.com/nodejs/node')->getSnpFromSaveRequestID(22971)

    // SWHCoreID dataType (object form)
= Modules\DataType\SwhCoreID {#8154
    +"snp": "swh:1:snp:a2dca5eecf79e2898e0813411394abb36cf5dfe1",
  }

    // String form
> Archive::of('https://github.com/nodejs/node')->getSnpFromSaveRequestID(22971)->getswhid()
= "swh:1:snp:a2dca5eecf79e2898e0813411394abb36cf5dfe1"
```

### VIII) MetaData

This class reveals MetaData of `revision`, `release` and `content` nodes. This class is `abstract` with all its methods set as `static`, hence no object instantiation is necessary; all methods are accessible throughout as global functions. This class provides access to conveniently deal with these SWH objects based on self-explanatory naming of its methods.

> `Abstract SwhMetaData::class`

Abstract class usage:

```php
> namespace Module\MetaData;
> use Module\MetaData; 

> SwhMetaData::methodName();    // methodName() is prepended with the class name and two colons `::` 
```

| `Issues Tracking` | https://github.com/dagstuhl-publishing/faircore4eosc/issues/24 |
|-------------------|----------------------------------------------------------------|


- Get metadata for a given `revision` node by its identifier.

    > `revision` metadata are defined by the following node keys: `message, author, committer, committer_date, type, metadata`.

| `Class static` Method                       | Method Arguments                                             | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                    | `HTTP` Method |
|---------------------------------------------|--------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|---------------|
| `getRevisionMetaData($revID[,...$options])` | `<string>: $revID:`<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`revision`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-revision-(sha1_git)-) | GET           |

```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::$responseType = 'object'

> SwhMetaData::getRevisionMetaData('swh:1:rev:396b1ff29f7c75a0a3cc36f30e24ff7bae70bb52')           // get all metadata for this revision node
= {#6625
    +"message": "hal: Deposit 282 in collection hal",
    ⋮
  }
  
> SwhMetaData::getRevisionMetaData('swh:1:rev:396b1ff29f7c75a0a3cc36f30e24ff7bae70bb52')->committer    // get committer metadata
= {#6703
    +"fullname": "Software Heritage",
    +"name": "Software Heritage",
    +"email": "robot@softwareheritage.org",
  }

> SwhMetaData::getRevisionMetaData('swh:1:rev:396b1ff29f7c75a0a3cc36f30e24ff7bae70bb52')->metadata->{'codemeta:programmingLanguage'}       // get codeMeta-specific metaData
= "Java"
```

- Get metadata for a given `release` node by its identifier.

  > `release` metadata are defined by the following node keys: `message, author, date`.

| `Class static` Method                      | Method Arguments                                              | Method `$options` (defaults)                                                                          | Returns                                                                             | `SWH` Endpoint                                                                                                  | `HTTP` Method |
|--------------------------------------------|---------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|---------------|
| `getReleaseMetaData($relID[,...$options])` | `<string>: $relID: `<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Iterable\|Collection\|stdClass`<br/>- `Throwable: RequestException \| Exception` | [`release`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-release-(sha1_git)-) | GET           |


```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> HTTPClient::$responseType = 'object'

> SwhMetaData::getReleaseMetaData('swh:1:rel:1791be4df87a0d69008ba46c5a03be2e4cfbe3d5')->author

= {#6701
    +"fullname": "Linus Torvalds <torvalds@linux-foundation.org>",
    +"name": "Linus Torvalds",
    +"email": "torvalds@linux-foundation.org",
  }
```

- Get metadata for a given `content` node by its identifier.

    > This method retrieves the full `content` data and follows internally the links of `fileType`, `language`, and `license` to build full content node with metaData. 

| `Class static` Method                              | Method Arguments                                             | Method `$options` (defaults)                                                                          | Returns                                                         | `SWH` Endpoint                                                                                                                           | `HTTP` Method |
|----------------------------------------------------|--------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getFullContentWithMetaData($cntID[,...$options])` | `<string>: $cntID:`<br/>-`40-hex-chars`<br/>-`as full SWHID` | Named Parameters:<br/>- `withHeaders: bool (false)`<br/>- `delay: ms (0)`<br/>- `debug: bool (false)` | - `Collection`<br/>- `Throwable: RequestException \| Exception` | [`content`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-content-known-(sha1)[,(sha1),%20...,(sha1)]-) | GET           |

```php
> namespace Module\HTTPConnector;
> use Module\HTTPConnector; 

> SwhMetaData::getFullContentWithMetaData("7717a647f7558135328d2877ad0f6aa45a3c5518")->all()

= [
    "response" => [
      "length" => 1629,
      ⋮
    ],
    "fileType" => [
        ⋮
        "mimetype" => "text/plain",
        ⋮
    ],
    "language" => "No language information found for content sha1_git:7717a647f7558135328d2877ad0f6aa45a3c5518.",
    "license" => [
        [
        ⋮
        "license" => "MIT",
        ]   
    ]
]        
```

- Get origin MetaData per its URL

    > Returns a list of metadata authorities that provided metadata on the given target
    > 
    > Appends the `raw extrinsic metadata` collected on each object to the final results.
    > 
    > This method follows internal links of the metadata URL of each authority to build entire metadata available for this target. i.e. `/raw-extrinsic-metadata/get/40-HEX-CHARS/?filename=NAME`

| `Class static` Method     | Method Arguments                    | Returns                                                    | `SWH` Endpoint                                                                                                                                                                                                                                                                                                                                                            | `HTTP` Method |
|---------------------------|-------------------------------------|------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|
| `getOriginMetaData($url)` | `<string>: $url:`<br/> `Origin url` | - `Array`<br/>- `Throwable: RequestException \| Exception` | - [`origin`](https://docs.softwareheritage.org/devel/swh-web/uri-scheme-api.html#get--api-1-origin-(origin_url)-get-)<br/> - [`raw-extrinsic-metadata`](https://archive.softwareheritage.org/api/1/raw-extrinsic-metadata/swhid/authorities/doc/)<br/> - [`raw-extrinsic-metadata (SWHID)`](https://archive.softwareheritage.org/api/1/raw-extrinsic-metadata/swhid/doc/) | GET           |

```php
> SwhMetaData::getOriginMetaData('https://github.com/torvalds/linux/')

= [
    "metadata_list_url_1" => [
      [
        "discovery_date" => "2022-04-28T22:49:17+00:00",
        "authority" => [
          "type" => "forge",
          "url" => "https://github.com",
        ],
        "fetcher" => [
          "name" => "swh.loader.metadata.github",
          "version" => "0.0.2",
        ],
        "format" => "application/vnd.github.v3+json",
        ⋮
        "metadata_url" => [                         // expansion of `/raw-extrinsic-metadata/get/40-HEX-CHARS/?filename=NAME`
            ⋮
            "description" => "Linux kernel source tree",
            ⋮
            "stargazers_count" => 130895,
            ⋮
           ]       
        ]
       ⋮
      ]
   ]
```
