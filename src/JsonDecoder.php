<?php

namespace Karriere\JsonDecoder;

use Karriere\JsonDecoder\Bindings\RawBinding;

class JsonDecoder
{
    /**
     * @var array
     */
    private $transformers = [];

    private $decodePrivateProperties;

    private $decodeProtectedProperties;

    /**
     * JsonDecoder constructor.
     *
     * @param bool $decodePrivateProperties
     * @param bool $decodeProtectedProperties
     */
    public function __construct($decodePrivateProperties = false, $decodeProtectedProperties = false)
    {
        $this->decodePrivateProperties = $decodePrivateProperties;
        $this->decodeProtectedProperties = $decodeProtectedProperties;
    }

    /**
     * registers the given transformer.
     *
     * @param Transformer $transformer
     */
    public function register(Transformer $transformer)
    {
        $this->transformers[$transformer->transforms()] = $transformer;
    }

    public function decode(string $json, string $classType)
    {
        $data = json_decode($json, true);

        if (json_last_error()) {
            throw new \RuntimeException('JSON decoding error: ' . json_last_error_msg(), 400);
        }

        if (\count($data) === 0) {
            throw new \RuntimeException('JSON decoding error: empty body', 404);
        }

        if (array_key_exists(0, $data)) {
            throw new \RuntimeException('JSON decoding error: no raw JSON object in body', 404);
        }

        return $this->decodeArray($data, $classType);
    }

    public function decodeMultiple(string $json, string $classType)
    {
        $data = json_decode($json, true);

        if (json_last_error()) {
            throw new \RuntimeException('JSON decoding error: ' . json_last_error_msg(), 400);
        }

        if (\count($data) === 0) {
            throw new \RuntimeException('JSON decoding error: empty body', 404);
        }

        if (!array_key_exists(0, $data)) {
            throw new \RuntimeException('JSON decoding error: no properly-formed JSON array in body', 404);
        }

        return array_map(
            function ($element) use ($classType) {
                if (\count($element) === 0) {
                    throw new \RuntimeException('JSON decoding error: empty object in JSON array', 404);
                }
                return $this->decodeArray($element, $classType);
            },
            $data
        );
    }

    /**
     * decodes the given array data into an instance of the given class type.
     *
     * @param $jsonArrayData array
     * @param $classType string
     *
     * @return mixed an instance of $classType
     */
    public function decodeArray($jsonArrayData, $classType)
    {
        $instance = new $classType();

        if (array_key_exists($classType, $this->transformers)) {
            return $this->transform($this->transformers[$classType], $jsonArrayData, $instance);
        } else {
            return $this->transformRaw($jsonArrayData, $instance);
        }
    }

    public function decodesPrivateProperties()
    {
        return $this->decodePrivateProperties;
    }

    public function decodesProtectedProperties()
    {
        return $this->decodeProtectedProperties;
    }

    private function transform($transformer, $jsonArrayData, $instance)
    {
        // if (empty($jsonArrayData)) {
        //     return null;
        // }

        $classBindings = new ClassBindings($this);
        $transformer->register($classBindings);

        return $classBindings->decode($jsonArrayData, $instance);
    }

    protected function transformRaw($jsonArrayData, $instance)
    {
        // if (empty($jsonArrayData)) {
        //     return null;
        // }

        $classBindings = new ClassBindings($this);

        foreach (array_keys($jsonArrayData) as $property) {
            $classBindings->register(new RawBinding($property));
        }

        return $classBindings->decode($jsonArrayData, $instance);
    }
}
