<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\Archival;

use Illuminate\Support\Collection;
use Module\DataType\SwhCoreID;
use stdClass;
use Throwable;

interface SwhArchive
{

    public function save2Swh(...$flags): iterable|Collection|stdClass|Throwable;

    public function getArchivalStatus(string|int $saveRequestDateOrID, ...$flags): iterable|Collection|stdClass|Throwable;

    public function trackArchivalStatus(string|int $saveRequestDateOrID, ...$flags): iterable|Collection|stdClass|Throwable;

    public function getSnpFromSaveRequest(string|int $saveRequestDateOrID): SwhCoreID|Null|Throwable;

    public function getLatestArchivalAttempt(...$flags) : iterable|Collection|stdClass|Throwable;

}
