<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DAGModel;

use Illuminate\Support\Collection;
use Module\DataType\SwhCoreID;
use stdClass;
use Throwable;

interface SwhNodes
{

    public function which(): String|Throwable;

    public function nodeExists(): String|Bool|Throwable;

    public function nodeHopp(): Iterable|Collection|stdClass|Throwable;

    public function nodeEdges(): Iterable|Collection|stdClass|Throwable;

    public function nodeTargetEdge(string $targetName): SwhCoreID|Array|Throwable;

    public function nodeTraversal(string|array $target = null): SwhCoreID|stdClass|Throwable;

}
