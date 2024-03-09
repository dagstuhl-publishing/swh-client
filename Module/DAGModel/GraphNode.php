<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DAGModel;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Module\DataType\SwhCoreID;
use Module\Globals\Formatting;
use Module\Globals\Helper;
use Module\HTTPConnector\HTTPClient;
use Module\HTTPConnector\SyncHTTP;
use stdClass;
use Throwable;
use TypeError;

class GraphNode extends SyncHTTP implements SwhNodes
{
    public const SUPPORTED_OPTIONS = ["stringType", "withHeaders"];

    private const EXISTS = 'Exists in SWH';

    protected SwhCoreID $nodeDataType;

    public string $nodeID;

    protected string $nodeInitials;

    /**
     * @param string $swhID
     * @param ...$options
     * @throws TypeError
     */
    public function __construct(string $swhID, ...$options)
    {
        $swhID = Str::of($swhID)->match('/^([^;]+)/')->value();

        $this->nodeDataType = new SwhCoreID(Formatting::reFormatSwhID($swhID));

        [$this->nodeID, $this->nodeInitials] = [$this->nodeDataType->getSwhid(), $this->nodeDataType->getInitials()];

        parent::__construct();

        self::setOptions(...$options);
    }

    /**
     * @return iterable|Throwable
     * @throws RequestException
     */
    private function hoppOn() : Array|Throwable
    {
        $resolvedTo = $this->invokeEndpoint("GET", 'resolve', collect($this->nodeID));

        return $resolvedTo instanceof Throwable
            ? $resolvedTo
            : Arr::where($resolvedTo->json(), function ($value, $key) {
                return $key==='object_type' || $key==='object_id' ;
            });
    }

    /**
     * @return String
     * @throws RequestException
     */
    public function which() : String|Throwable
    {
        try{
            $resolvedTo = $this->hoppOn();

            if($resolvedTo instanceof Throwable){
                return $resolvedTo;
            }

            return $resolvedTo['object_type'];

        }catch (RequestException $e){
            $this->addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid swhID identifier: $this->nodeID",
                    404 => "Requested swhID was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     */
    public function nodeHopp(...$flags): Iterable|Collection|stdClass|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $resolvedTo = $this->hoppOn();

            if($resolvedTo instanceof Throwable){
                return $resolvedTo;
            }
            $totalResponse=[]; $linked=true;
            do{
                $responseSwhObject = $this->invokeEndpoint("GET", $headerLink ?? $resolvedTo[Str::snake('objectType')], collect($resolvedTo[Str::snake('objectId')]));

                $totalResponse = Arr::map($responseSwhObject->json(), function($val, $key) use($totalResponse) {
                    if(empty($totalResponse)) return $val;

                    if (Arr::accessible($val)) {
                        $array = Arr::where($totalResponse, function ($v, $k) use ($key) {
                            return $k === $key;
                        });
                        $earlyBranches = array_shift($array);
                        return array_merge($earlyBranches, $val);
                    }
                    else return $val;
                });

                isset($responseSwhObject->headers()["Link"])
                    ? $headerLink =  preg_replace('/<|>/i', "",Str::of($responseSwhObject->headers()["Link"][0])->explode(";")[0])
                    : $linked = false;

            }while($linked);

            $totalResponse = Formatting::reCastTo($totalResponse, self::$responseType);

            return $flags['withHeaders'] ?? false
                ? collect(["response" => $totalResponse, "headers" => $responseSwhObject->headers()])
                : $totalResponse;

        }catch (RequestException $e){
            $this->addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid swhID identifier: $this->nodeID",
                    404 => "Requested swhID was not found in SWH",
                    default => $e->response->json()['reason'] ?? $e->response->body()
                });
            return $e;
        }catch(Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }

    /**
     * @param ...$flags
     * @return String|bool|Throwable
     */
    public function nodeExists(...$flags): String|Bool|Throwable
    {
        try{
            Helper::validateOptions($flags);

            $resolvedTo = $this->hoppOn();

            if($resolvedTo instanceof Throwable){
                return $resolvedTo;
            }

            $responseSwhObject = $this->invokeEndpoint("HEAD", $resolvedTo[Str::snake('objectType')], collect($resolvedTo[Str::snake('objectId')]), ...$flags);

            return $flags['stringType'] ?? false
                ? Formatting::formatSwhIDs($resolvedTo[Str::snake('objectType')], $resolvedTo[Str::snake('objectId')])." --> " .self::EXISTS
                : boolval($responseSwhObject->status());

        }catch (RequestException $e){
            $this->addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid swhID identifier: $this->nodeID",
                    404 => "Requested swhID was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch(Exception $e){
            $this->addErrors($e->getCode().": ".$e->getMessage());
            return $e;
        }
    }


    /**
     * @param ...$flags
     * @return iterable|Collection|stdClass|Throwable
     * @throws Exception
     */
    public function nodeEdges(...$flags): Iterable|Collection|stdClass|Throwable
    {
        try{
            $resolvedTo = $this->hoppOn();

            if($resolvedTo instanceof Throwable){
                return $resolvedTo;
            }

            $edgeMethod = 'get'.Str::ucfirst($resolvedTo[Str::snake('objectType')]).'Edges';

            return match ($resolvedTo[Str::snake('objectType')]){
                'snapshot',
                    'release',
                        'revision',
                            'directory' => GraphEdges::$edgeMethod($this->nodeID, ...$flags),
                'content' => throw new Exception('No Edges. Contents are leaves.')
            };

        }catch (RequestException $e){
            $this->addErrors($e->getCode()." : " . match ($e->getCode()){
                    400 => "An invalid swhID identifier: $this->nodeID",
                    404 => "Requested swhID was not found in SWH",
                    default => $e->getMessage()
                });
            return $e;
        }catch (Exception $e){
            $this->addErrors($e->getMessage());
            return $e;
        }
    }

    /**
     * @param string $targetName
     * @return SwhCoreID|array|Throwable
     * @throws Exception
     */
    public function nodeTargetEdge(string $targetName): SwhCoreID|Array|Throwable
    {
        try {
            $nodeEdges = $this->nodeEdges();

            if($nodeEdges instanceof Throwable){
                return $nodeEdges;
            }

                /** @var Collection $nodeEdges */
            $nodeEdges = Formatting::reCastTo($nodeEdges, HTTPClient::RESPONSE_TYPE_COLLECT);

            $targetEdge = $nodeEdges->firstOrFail(fn ($val, $key) => $key === $targetName);

            return is_string($targetEdge)
                ? new SwhCoreID($targetEdge)
                : $targetEdge;

        }catch(ItemNotFoundException | TypeError $e){
            $this->addErrors(match (true){
                $e instanceof ItemNotFoundException => "Target Edge: $targetName was not found in any edge",
                $e instanceof TypeError => "Target Edge $targetName has no valid SWHID"
            });
            return $e;
        }

    }

    /**
     * @param string|array|null $target
     * @return SwhCoreID|stdClass|Throwable
     * @throws Exception
     */
    public function nodeTraversal(string|array $target = null): SwhCoreID|stdClass|Throwable
    {
        if($this->nodeInitials === Formatting::SWH_OBJECT_TYPES[Formatting::SWH_CONTENT]){
            throw new Exception('No forward traversal from content nodes possible.');
        }

        if($this->nodeInitials !== Formatting::SWH_OBJECT_TYPES[Formatting::SWH_RELEASE] && is_null($target)){
            throw new Exception('Missing propagation target.');
        }

        $traverseMethod = 'traverseFrom'.ucfirst($this->nodeInitials);

        return match ($this->nodeInitials){
            'snp', 'rev', 'dir' => GraphTraversal::$traverseMethod($this->nodeInitials === 'snp' ? $this->nodeDataType : $this->nodeID, $target),
            'rel' => GraphTraversal::traverseFromRelToRev($this->nodeID)
        };
    }

}
