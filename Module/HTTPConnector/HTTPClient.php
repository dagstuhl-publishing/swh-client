<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\HTTPConnector;

use Module\DataType\SwhCoreID;
use Module\Logging\Logger;
use Module\Globals\HTTP;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Throwable;
use TypeError;
use Exception;

abstract class HTTPClient
{
    use Logger;
    private const API_Version = "/api/1/";
    protected const API_ENDPOINTS = [
        "origin"       => ['expects' => 'URL' , 'route' => self::API_Version . "origin/~/get/"],
        "visit"        => ['expects' => 'URL' , 'route' => self::API_Version . "origin/~/visit/~"],
        "visits"       => ['expects' => 'URL' , 'route' => self::API_Version . "origin/~/visits/~"],
        "save"         => ['expects' => 'URL' , 'route' => self::API_Version . "origin/save/~/url/~/"],
        "saveWithID"   => ['expects' => 'int' , 'route' => self::API_Version . "origin/save/~/"],
        "resolve"      => ['expects' => 'SWHID', 'route' => self::API_Version . "resolve/~/"],
        "snapshot"     => ['expects' => 'SHA1', 'route' => self::API_Version . "snapshot/~/"],
        "release"      => ['expects' => 'SHA1', 'route' => self::API_Version . "release/~/"],

        "revision"     => ['expects' => 'SHA1', 'route' => self::API_Version . "revision/~/"],
        "revisionLog"  => ['expects' => 'SHA1', 'route' => self::API_Version . "revision/~/log/~"],
        "revisionPath" => ['expects' => 'SHA1', 'route' => self::API_Version . "revision/~/directory/~/"],

        "directoryPath"=> ['expects' => 'SHA1', 'route' => self::API_Version . "directory/~/~/"],
        "directory"    => ['expects' => 'SHA1', 'route' => self::API_Version . "directory/~/"],
        "content"      => ['expects' => 'SHA1', 'route' => self::API_Version . "content/sha1_git:~/"],   // todo: sha256, blake2s256
    ];
    private const SUPPORTED_METHODS = ['get', 'post', 'head'];
    public const SUPPORTED_OPTIONS = ['delay', 'debug'];
    protected const CLIENT_OPTIONS = ['responseType', 'apiURL'];
    public const LOG_OPTIONS = ['isVerbose', 'fileDatestamp'];
    protected const PENDING_REQUEST_OPTIONS = ['connectTimeout', 'timeout', 'retry', 'sleepMilliseconds', 'serverType'];
    protected static array $serverErrorCodes = [500, 501, 502, 503, 504, 505, 506, 507, 508, 510, 511];

    public const RESPONSE_TYPE_ARRAY = 'json';
    public const RESPONSE_TYPE_OBJECT = 'object';
    public const RESPONSE_TYPE_COLLECT = 'collect';

    public static string $responseType = self::RESPONSE_TYPE_ARRAY;
    public static string $serverType = 'production';
    public static ?string $apiURL = Null;
    private static int $connectTimeout;
    private static int $timeout;
    private static int $retry;
    private static int $sleepMilliseconds;
    private static array $swhConfigs;
    protected PendingRequest $HTTPRequest;

    /**
     * @param string $method
     * @param string $on
     * @param Collection $append2Url
     * @param ...$options
     * @return Response|iterable|Throwable
     */
    abstract protected function invokeEndpoint(string $method, string $on, Collection $append2Url, ...$options): Response|iterable|Throwable;

    abstract protected static function request(string $method, string $uri, ...$options): Response;

    /**
     * @param ...$options
     * @return void
     */
    public static function setOptions(...$options) : void
    {
        $irrelevantOptions = array_diff(array_keys($options), array_merge(self::CLIENT_OPTIONS, self::PENDING_REQUEST_OPTIONS, self::LOG_OPTIONS));
        if($irrelevantOptions){
            self::addLogs("Undefined Option(s). Ignoring: ".implode(", ", $irrelevantOptions));
            //return;
        }
        if(isset($options['responseType'])){
            self::$responseType = match($options['responseType']){
                'collect'       => self::RESPONSE_TYPE_COLLECT,
                'object'        => self::RESPONSE_TYPE_OBJECT,
                default         => self::RESPONSE_TYPE_ARRAY
            };
        }
        if(isset($options['apiURL'])){
            self::$apiURL = $options['apiURL'];
        }

        $setPendingRequestOptions = Arr::only($options, self::PENDING_REQUEST_OPTIONS);

        if(!empty($setPendingRequestOptions)){
            foreach ($setPendingRequestOptions as $key => $value){
                self::${$key} = $value;
            }
        }

        self::setLogOptions(...$options);
    }

    public function __construct()
    {
        self::$swhConfigs = require 'swhConfigs.php';

        self::$apiURL = self::$apiURL ?? self::$swhConfigs[self::$serverType]['api-url'];

        self::openLog();

        $this->HTTPRequest = HTTP::withToken(self::$swhConfigs[self::$serverType]['token'])
            ->connectTimeout(self::$connectTimeout ?? 5)
            ->timeout(self::$timeout ?? 5)
            ->throw(function ($response, $e) {
                if($response->serverError()){
                    throw new Exception("Server-side Error Status", $response->status());
                }
            })
            ->retry(self::$retry ?? 5, self::$sleepMilliseconds ?? 5000,
                function ($e, $request) {
                    $retryMessage = "Retrying ?";

                    if(isset($e) && !$e instanceof GuzzleRequestException) {
                        switch (true) {
                            case $e instanceof ConnectionException || $e->response->serverError():

                                self::addLogs($retryMessage . " : Yes. Reason --> {$e->getMessage()}");
                                return true;
                            case $e->response->status() === 406:

                                self::addLogs($retryMessage . " : Yes. Reason --> {$e->getMessage()}");
                                $request->acceptJson();
                                return true;
                            case $e->response->status() === 403:

                                self::$serverType = 'staging';

                                if(self::$apiURL === self::$swhConfigs[self::$serverType]['api-url']) {
                                    self::addLogs($retryMessage . " : Yes. Reason --> {$e->getMessage()}");

                                    $request->withToken(self::$swhConfigs[self::$serverType]['token']);
                                    return true;
                                }
                                break;
                            case $e instanceof RequestException || $e->response->clientError():

                                self::addLogs($retryMessage . " : No. Reason --> {$e->getMessage()}");
                                return false;
                        }
                    }
                    return false;
                })
            ->accept('application/json')
            ->withOptions([
                'debug' => false,
                'allow_redirects' => ['max' => 1, 'strict'=> true, 'protocols' => ['https'], 'track_redirects' => true],
                'force_ip_resolve' => 'v4',
                'http_errors' => false,
                'verify' => true,
                'headers' => ['User-Agent' => 'Dagstuhl-LZI/1.0'],
                'decode_content' => 'gzip',
                'version' => '1.1'
            ]);
    }

    /**
     * @param string $method
     * @param string $on
     * @param Collection $append2Url
     * @return Void
     * @throws Exception
     */
    protected function prepareForInvoke(string $method, string $on, Collection &$append2Url): Void
    {
        if(isset(parse_url($on)["host"])){
            $append2Url = Null;
            return;
        }
        if(!in_array($method, self::SUPPORTED_METHODS)){
            throw new Exception("Method Mismatch. Unsupported HTTP method passed. Supported methods: ".implode(", ", self::SUPPORTED_METHODS), 980);
        }

        if(self::isValidEndpoint($on) && self::isValidPattern($on, $append2Url, [self::class, $on==='resolve' ? 'isExpectedSwhIdPattern' : 'isExpectedPattern'])){
            $this->addLogs('Tests passed without errors, proceeding...');
        }
        if(self::API_ENDPOINTS[$on]['expects'] ==='URL'){
            $append2Url[0] = preg_replace('#/$#','', $append2Url[0]);

            if($on==='save') $append2Url = $append2Url->reverse();

        }
    }

    /**
     * @param string $endPoint
     * @return bool
     * @throws Exception
     */
    private static function isValidEndpoint(string $endPoint): bool
    {
        if(Arr::exists(self::API_ENDPOINTS, $endPoint) === false) {
            throw new Exception("Error in isValidEndpoint(): Unrecognised Endpoint", 980);
        }
        return true;
    }
    /**
     * @param string $endPoint
     * @param Collection $append2Url
     * @param callable $patternCallback
     * @return bool
     * @throws Exception
     */
    private static function isValidPattern(string $endPoint, Collection $append2Url, callable $patternCallback) : bool
    {
        if($append2Url->count() !== Str::substrCount(self::API_ENDPOINTS[$endPoint]['route'], '~')){
            throw new Exception("Error in isValidPattern(): Incompatible URL substitution pattern", 980);
        }
        return $patternCallback($endPoint, $append2Url);
    }

    /**
     * @param string $endPoint
     * @param Collection $append2Url
     * @return bool
     * @throws Exception
     * @throws Throwable
     */
    private static function isExpectedPattern(string $endPoint, Collection $append2Url) : bool
    {
        if($endPoint === 'saveWithID'){
            throw_unless(is_int($append2Url[0]), new Exception("Validation failed in isExpectedPattern(): The route '$endPoint' expects Integer", 980));
            return true;
        }

        $validator = new Validator(new Translator(new ArrayLoader(), 'en'), $append2Url->toArray(),
            [
                0 => ['url', 'max:255', 'regex: /^[a-f0-9]{40}(?:\/\??\S*)?$/i']     // ex: SHA1(/?branches_from=v2.6.37-rc6&branches_count=1000)?  SHA1(/license)? // includes all other queries
            ],
            [
                'url'   => ':input is a non-valid URL',
                'max'   => ':input too long',
                'regex' => ':input is not a valid sha_1',
            ],
        );

        $errors = $validator->errors()->all();
        $rulesKey = $validator->failed()[0];

        switch (true){
            case count($errors)>=2:
                throw ValidationException::withMessages([implode('/', array_keys($rulesKey)) => "Validation failed in isExpectedPattern(): Non-valid '{$validator->getData()[0]}' URL/SHA_1"]);

            case Arr::has($rulesKey , 'Url'):
                self::addLogs("Validator Note: Non-valid URL. Pass: '$endPoint' endpoint expects --> ". self::API_ENDPOINTS[$endPoint]['expects']);

                if(self::API_ENDPOINTS[$endPoint]['expects'] === 'URL'){
                    throw ValidationException::withMessages([implode(array_keys($rulesKey)) => "Validation failed in isExpectedPattern(): The route '$endPoint' doesn't expect SHA1 entry. $errors[0]"]);
                };
                break;

            case Arr::has($rulesKey , "Regex"):
                self::addLogs("Validator Note: Non-valid SHA1. Pass: '$endPoint' endpoint expects --> ". self::API_ENDPOINTS[$endPoint]['expects']);

                if(self::API_ENDPOINTS[$endPoint]['expects'] === 'SHA1'){
                    throw ValidationException::withMessages([implode(array_keys($rulesKey)) => "Validation failed in isExpectedPattern(): The route '$endPoint' doesn't expect URL entry.'{$validator->getData()[0]}' is a non-valid SHA1"]);
                };
                break;
        }
        return true;
    }

    /**
     * @param string $endPoint
     * @param Collection $append2Url
     * @return bool
     * @throws Exception
     */
    private static function isExpectedSwhIdPattern(string $endPoint, Collection $append2Url): bool
    {
        try{
            $swhID = Str::of($append2Url->toArray()[0])->match('/^([^;]+)/')->value();
            new SwhCoreID($swhID);
            return true;
        }catch (TypeError $e){

            self::addLogs("Validator Note: Non-valid SWHID. Pass: '$endPoint' endpoint expects --> ". self::API_ENDPOINTS[$endPoint]['expects']);

            throw new Exception('Validation failed in isExpectedSwhIdPattern(): provided swhID seems incorrectly formatted. Correct format is
             --> swh:1:swhInitials:40-bit-hexString', 980);
        }
    }
}
