<?php

/**
 * @Author: Ramy-Badr-Ahmed
 * @Desc: LZI -- SWH API Client
 * @Repo: https://github.com/dagstuhl-publishing/swh-client
 */

namespace Module\DataType;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionException;
use stdClass;
use TypeError;

class SwhCoreID extends stdClass implements SwhDataType
{
    private string $swhID;

    /**
     * @param string $id
     */
    public function __construct(string $id)
    {
        if(!$this->EBNF($id)){
            throw new TypeError("Invalid SWH Data Type, regular '".gettype($id)."' type is given of value -> ".$id);
        }

        $this->swhID = $id;

        $newKey = Str::of($id)->explode(self::SEPARATOR['colon'])[2];

        $this->{$newKey} = $this->swhID;
    }

    /**
     * @return string
     */
    public function getSwhid(): string
    {
        return $this->swhID;
    }

    public function getInitials() : string
    {
        return array_keys(get_object_vars($this))[1];
    }

    /**
     * @param string $id
     * @return bool
     */
    public function EBNF(string &$id): bool
    {
        $id = preg_replace('/\s/i',"", $id);

        return match(true){
            Str::substrCount($id, self::SEPARATOR['colon'])!==3 => false,
            !in_array(Str::of($id)->explode(self::SEPARATOR['colon'])[2], self::INITIALS) => false,
            strcasecmp(
                Arr::join(
                    [
                        self::HEADING,
                        self::VERSION,
                        Str::of($id)->explode(self::SEPARATOR['colon'])[2]
                    ],
                    self::COLON),
                Str::substr($id, 0, strrpos($id, self::SEPARATOR['colon'],-1))
            ) !== 0 => false,
            preg_match('/^[a-f0-9]{40}$/i', Str::of($id)->explode(self::SEPARATOR['colon'])[3]) === 0 => false,     // sha1 sha245, blake2s256
            default => true
        };
    }

    /**
     * @param $name
     * @param $value
     * @return void
     * @throws ReflectionException
     */
    public function __set($name, $value)
    {
        if($name === Str::of($this->swhID)->explode(self::COLON)[2]){
            $this->{$name} = $value;
        }
        else{
            throw new ReflectionException('Cannot add new property to SWH Data Type');
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws ReflectionException
     */
    public function __get($name)
    {
        if($name === Str::of($this->swhID)->explode(self::COLON)[2]){
            return $this->{$name};
        }
        else{
            throw new ReflectionException("Undefined SWH Data Type property");
        }
    }
}
